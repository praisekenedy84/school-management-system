<?php

declare(strict_types=1);

namespace Tests\Feature\Assessment;

use App\Events\ResultsPublished;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * POST /api/v1/assessments/{assessment}/publish — ResultPublishController +
 * ResultPublishingService + ResultRecordPolicy::publish(). Gated to
 * academic_director|school_admin|tenant_admin per PRD §5.5; a teacher who
 * entered the marks cannot publish their own work.
 */
class ResultPublishTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private Subject $subject;

    private AcademicSession $session;

    private Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->school = School::factory()->create();
        $this->subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $this->assessment = Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'max_score' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        Auth::guard('web')->logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    private function tenantUrl(string $path): string
    {
        return "http://{$this->tenantId}.sms.test{$path}";
    }

    private function admin(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('school_admin');

        return $user;
    }

    private function academicDirector(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('academic_director');

        return $user;
    }

    private function teacher(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('teacher');

        return $user;
    }

    private function draftResultRecords(int $count = 3): array
    {
        $teacher = $this->teacher();

        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $student = Student::factory()->create(['school_id' => $this->school->id]);
            $records[] = ResultRecord::factory()->create([
                'school_id' => $this->school->id,
                'student_id' => $student->id,
                'subject_id' => $this->subject->id,
                'academic_session_id' => $this->session->id,
                'assessment_id' => $this->assessment->id,
                'score' => 60,
                'version' => 1,
                'is_published' => false,
                'entered_by' => $teacher->id,
            ]);
        }

        return $records;
    }

    // ---- Gated by role -----------------------------------------------------------

    public function test_academic_director_can_publish(): void
    {
        $this->draftResultRecords();
        $director = $this->academicDirector();
        $this->actingAs($director);

        $response = $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"));

        $response->assertOk();
    }

    public function test_school_admin_can_publish(): void
    {
        $this->draftResultRecords();
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"));

        $response->assertOk();
    }

    public function test_teacher_who_entered_marks_cannot_publish_their_own_work(): void
    {
        $teacher = $this->teacher();

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        ResultRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'assessment_id' => $this->assessment->id,
            'version' => 1,
            'is_published' => false,
            'entered_by' => $teacher->id,
        ]);

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"));

        $response->assertStatus(403);

        $this->assertFalse(ResultRecord::where('assessment_id', $this->assessment->id)->first()->is_published);
    }

    // ---- Publishing flips ALL latest-version records ------------------------------

    public function test_publishing_flips_all_latest_version_records_for_the_assessment(): void
    {
        $records = $this->draftResultRecords(3);
        $director = $this->academicDirector();
        $this->actingAs($director);

        $response = $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        foreach ($records as $record) {
            $record->refresh();
            $this->assertTrue($record->is_published);
            $this->assertSame($director->id, $record->published_by);
            $this->assertNotNull($record->published_at);
        }
    }

    // ---- Event dispatched (faked) --------------------------------------------------

    public function test_publishing_dispatches_results_published_event(): void
    {
        $this->draftResultRecords(2);
        Event::fake([ResultsPublished::class]);

        $director = $this->academicDirector();
        $this->actingAs($director);

        $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"))
            ->assertOk();

        Event::assertDispatched(ResultsPublished::class, function (ResultsPublished $event) {
            return $event->assessment->id === $this->assessment->id
                && $event->resultRecords->count() === 2;
        });
    }

    // ---- Real DB state change without faking ---------------------------------------

    public function test_publishing_actually_changes_db_state_without_event_fake(): void
    {
        $records = $this->draftResultRecords(2);
        $director = $this->academicDirector();
        $this->actingAs($director);

        foreach ($records as $record) {
            $this->assertFalse($record->is_published);
        }

        $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"))
            ->assertOk();

        foreach ($records as $record) {
            $record->refresh();
            $this->assertTrue($record->is_published);
            $this->assertNotNull($record->published_at);
            $this->assertSame($director->id, $record->published_by);
        }
    }

    // ---- Publishing again after a post-publish correction --------------------------

    public function test_republishing_after_correction_does_not_touch_old_published_version(): void
    {
        $teacher = $this->teacher();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $version1 = ResultRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'assessment_id' => $this->assessment->id,
            'score' => 60,
            'version' => 1,
            'is_published' => false,
            'entered_by' => $teacher->id,
        ]);

        $director = $this->academicDirector();
        $this->actingAs($director);

        // First publish.
        $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"))
            ->assertOk();

        $version1->refresh();
        $this->assertTrue($version1->is_published);
        $firstPublishedAt = $version1->published_at;

        // Correction creates version 2 (mirrors MarkEntryTest's flow via the
        // real endpoint, using an assigned teacher).
        TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
        ]);

        $this->actingAs($teacher);
        $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 88,
        ])->assertCreated();

        $version2 = ResultRecord::where('assessment_id', $this->assessment->id)
            ->where('version', 2)
            ->first();
        $this->assertNotNull($version2);
        $this->assertFalse($version2->is_published);

        // Re-publish: should publish version 2 only; version 1 untouched.
        $this->actingAs($director);
        $republish = $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"));
        $republish->assertOk();
        $republish->assertJsonCount(1, 'data');
        $republish->assertJsonPath('data.0.id', $version2->id);

        $version1->refresh();
        $version2->refresh();

        // Old published row completely untouched.
        $this->assertTrue($version1->is_published);
        $this->assertSame('60.00', (string) $version1->score);
        $this->assertEquals($firstPublishedAt, $version1->published_at);

        // New row now published.
        $this->assertTrue($version2->is_published);
        $this->assertSame('88.00', (string) $version2->score);
        $this->assertNotNull($version2->published_at);
    }
}
