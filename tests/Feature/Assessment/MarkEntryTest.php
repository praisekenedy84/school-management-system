<?php

declare(strict_types=1);

namespace Tests\Feature\Assessment;

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
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * ResultController@store/@index + EnterMarkRequest + MarkEntryService +
 * ResultRecordPolicy. The append-only/versioned invariant (RULES.md §1/§3)
 * is the core thing under test here:
 *  - first entry => version 1, is_published=false.
 *  - re-entering while unpublished => same row, same id, same version.
 *  - re-entering AFTER publish => new row, version+1, is_published=false,
 *    and the OLD published row is untouched.
 */
class MarkEntryTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private Subject $subject;

    private AcademicSession $session;

    private Assessment $assessment;

    private Student $student;

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
        $this->student = Student::factory()->create(['school_id' => $this->school->id]);
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

    private function assignedTeacher(): User
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
        ]);

        return $teacher;
    }

    public function test_export_returns_an_excel_file(): void
    {
        ResultRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'subject_id' => $this->subject->id,
            'assessment_id' => $this->assessment->id,
        ]);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/results/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    // ---- First entry -> version 1 ------------------------------------------------

    public function test_first_entry_creates_version_1_unpublished(): void
    {
        $teacher = $this->assignedTeacher();
        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 75,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.version', 1);
        $response->assertJsonPath('data.is_published', false);

        $this->assertSame(1, ResultRecord::count());

        $record = ResultRecord::first();
        $this->assertSame(1, $record->version);
        $this->assertFalse($record->is_published);
        $this->assertSame('75.00', (string) $record->score);
    }

    // ---- Re-entering while unpublished updates the SAME row ---------------------

    public function test_reentering_while_unpublished_updates_same_row(): void
    {
        $teacher = $this->assignedTeacher();
        $this->actingAs($teacher);

        $first = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 60,
        ]);
        $first->assertCreated();
        $originalId = $first->json('data.id');

        $second = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 82,
        ]);
        $second->assertCreated();

        $this->assertSame(1, ResultRecord::count());

        $record = ResultRecord::first();
        $this->assertSame($originalId, $record->id);
        $this->assertSame(1, $record->version);
        $this->assertSame('82.00', (string) $record->score);
    }

    // ---- Correction AFTER publish creates a NEW versioned row -------------------

    public function test_correction_after_publish_creates_new_version_and_leaves_old_row_untouched(): void
    {
        $teacher = $this->assignedTeacher();
        $admin = $this->admin();

        $this->actingAs($teacher);
        $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 70,
        ])->assertCreated();

        // Publish via the real endpoint (academic_director/school_admin/tenant_admin only).
        $this->actingAs($admin);
        $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"))
            ->assertOk();

        $publishedRecord = ResultRecord::first();
        $this->assertTrue($publishedRecord->is_published);
        $publishedAt = $publishedRecord->published_at;
        $publishedBy = $publishedRecord->published_by;

        // Now enter a correction.
        $this->actingAs($teacher);
        $correction = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 95,
        ]);
        $correction->assertCreated();
        $correction->assertJsonPath('data.version', 2);
        $correction->assertJsonPath('data.is_published', false);

        $this->assertSame(2, ResultRecord::count());

        $oldRow = ResultRecord::find($publishedRecord->id);
        $newRow = ResultRecord::where('version', 2)
            ->where('student_id', $this->student->id)
            ->where('assessment_id', $this->assessment->id)
            ->first();

        $this->assertNotNull($oldRow);
        $this->assertNotNull($newRow);
        $this->assertNotSame($oldRow->id, $newRow->id);

        // Old row completely untouched.
        $this->assertTrue($oldRow->is_published);
        $this->assertSame('70.00', (string) $oldRow->score);
        $this->assertSame(1, $oldRow->version);
        $this->assertEquals($publishedAt, $oldRow->published_at);
        $this->assertSame($publishedBy, $oldRow->published_by);

        // New row is the draft correction.
        $this->assertFalse($newRow->is_published);
        $this->assertSame('95.00', (string) $newRow->score);
        $this->assertSame(2, $newRow->version);
        $this->assertNull($newRow->published_at);
    }

    /**
     * Structural backstop (ResultRecord::booted()): even a direct model
     * call — bypassing MarkEntryService/ResultPublishingService entirely —
     * must not be able to mutate score/grade/version on an already-
     * published row. RULES.md §1/§3: never overwrite in place.
     */
    public function test_model_level_guard_blocks_mutating_a_published_row_directly(): void
    {
        $resultRecord = ResultRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'subject_id' => $this->subject->id,
            'assessment_id' => $this->assessment->id,
            'score' => 70,
            'version' => 1,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        $resultRecord->update(['score' => 999]);
    }

    // ---- Ownership: teacher without a matching TeacherAssignment -----------------

    public function test_teacher_without_matching_assignment_is_rejected_with_422(): void
    {
        $unassignedTeacher = User::factory()->create(['school_id' => $this->school->id]);
        $unassignedTeacher->assignRole('teacher');

        $this->actingAs($unassignedTeacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 50,
        ]);

        // EnterMarkRequest::withValidator() adds a validation error on
        // assessment_id rather than throwing AuthorizationException — 422,
        // not 403 (mirrors CreateAssignmentRequest's ownership pattern).
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assessment_id']);
        $this->assertSame(0, ResultRecord::count());
    }

    public function test_rejects_a_student_from_a_different_school(): void
    {
        $admin = $this->admin();

        $otherSchool = School::factory()->create();
        $outsideStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $outsideStudent->id,
            'assessment_id' => $this->assessment->id,
            'score' => 50,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_id']);
        $this->assertSame(0, ResultRecord::count());
    }

    public function test_role_without_create_permission_is_forbidden(): void
    {
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');

        $this->actingAs($parent);

        $response = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 50,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, ResultRecord::count());
    }

    // ---- score > max_score is rejected --------------------------------------------

    public function test_score_exceeding_max_score_is_rejected(): void
    {
        $teacher = $this->assignedTeacher();
        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 150, // max_score is 100
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['score']);
        $this->assertSame(0, ResultRecord::count());
    }

    // ---- index: latest-version-only vs all_versions -------------------------------

    public function test_index_returns_latest_version_only_by_default_and_all_with_all_versions_param(): void
    {
        $teacher = $this->assignedTeacher();
        $admin = $this->admin();

        $this->actingAs($teacher);
        $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 70,
        ])->assertCreated();

        $this->actingAs($admin);
        $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"))
            ->assertOk();

        $this->actingAs($teacher);
        $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 95,
        ])->assertCreated();

        $this->assertSame(2, ResultRecord::count());

        // Default: latest-version-only.
        $default = $this->getJson($this->tenantUrl(
            "/api/v1/results?student_id={$this->student->id}&assessment_id={$this->assessment->id}"
        ));
        $default->assertOk();
        $default->assertJsonCount(1, 'data');
        $default->assertJsonPath('data.0.version', 2);

        // ?all_versions=1: both versions.
        $all = $this->getJson($this->tenantUrl(
            "/api/v1/results?student_id={$this->student->id}&assessment_id={$this->assessment->id}&all_versions=1"
        ));
        $all->assertOk();
        $all->assertJsonCount(2, 'data');
        $versions = collect($all->json('data'))->pluck('version')->sort()->values();
        $this->assertSame([1, 2], $versions->all());
    }

    public function test_parent_only_sees_published_results_for_their_own_ward(): void
    {
        $teacher = $this->assignedTeacher();
        $admin = $this->admin();
        $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);
        $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $this->student->id,
            'assessment_id' => $this->assessment->id,
            'score' => 70,
        ])->assertCreated();
        $this->postJson($this->tenantUrl('/api/v1/results'), [
            'student_id' => $otherStudent->id,
            'assessment_id' => $this->assessment->id,
            'score' => 60,
        ])->assertCreated();

        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        $parent->wards()->attach($this->student->id, ['relationship' => 'father', 'is_primary' => true]);

        $this->actingAs($parent);

        // Unpublished: parent sees nothing yet, even for their own ward.
        $beforePublish = $this->getJson($this->tenantUrl('/api/v1/results'));
        $beforePublish->assertOk();
        $beforePublish->assertJsonCount(0, 'data');

        $this->actingAs($admin);
        $this->postJson($this->tenantUrl("/api/v1/assessments/{$this->assessment->id}/publish"))->assertOk();

        $this->actingAs($parent);

        // Explicitly requesting the other family's student_id returns
        // nothing — the ward scope and the requested filter are ANDed, so
        // a non-ward id can never be used to pull someone else's result.
        $forOtherStudent = $this->getJson($this->tenantUrl("/api/v1/results?student_id={$otherStudent->id}"));
        $forOtherStudent->assertOk();
        $forOtherStudent->assertJsonCount(0, 'data');

        // No filter at all: parent sees their own ward's published result.
        $unfiltered = $this->getJson($this->tenantUrl('/api/v1/results'));
        $unfiltered->assertOk();
        $unfiltered->assertJsonCount(1, 'data');
        $unfiltered->assertJsonPath('data.0.student_id', $this->student->id);
    }
}
