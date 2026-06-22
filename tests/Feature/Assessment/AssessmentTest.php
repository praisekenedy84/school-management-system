<?php

declare(strict_types=1);

namespace Tests\Feature\Assessment;

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * AssessmentController + AssessmentRequest + AssessmentPolicy. CRUD on
 * assessment *definitions* (not the unrelated Assignment/homework model).
 * `academic_director` create/update is a REAL rule (RULES.md §5
 * `assessment.manage_grading`), tested explicitly below, not assumed.
 */
class AssessmentTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private Subject $subject;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->school = School::factory()->create();
        $this->subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
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

    public function test_export_returns_an_excel_file(): void
    {
        Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
        ]);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/assessments/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    // ---- Happy path: create / show / update / delete --------------------------

    public function test_create_happy_path(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Midterm Exam',
            'weight' => 30,
            'max_score' => 100,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Midterm Exam');
        $response->assertJsonPath('data.subject_id', $this->subject->id);

        $this->assertDatabaseHas('assessments', [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Midterm Exam',
        ]);
    }

    public function test_academic_director_can_create_assessment(): void
    {
        $director = $this->academicDirector();
        $this->actingAs($director);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Director-created Quiz',
            'weight' => 10,
            'max_score' => 50,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('assessments', ['name' => 'Director-created Quiz']);
    }

    public function test_academic_director_can_update_assessment(): void
    {
        $assessment = Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Original Name',
        ]);

        $director = $this->academicDirector();
        $this->actingAs($director);

        $response = $this->putJson($this->tenantUrl("/api/v1/assessments/{$assessment->id}"), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Updated Name',
            'weight' => 20,
            'max_score' => 100,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');

        $assessment->refresh();
        $this->assertSame('Updated Name', $assessment->name);
    }

    public function test_show_returns_assessment(): void
    {
        $assessment = Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
        ]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl("/api/v1/assessments/{$assessment->id}"));

        $response->assertOk();
        $response->assertJsonPath('data.id', $assessment->id);
    }

    public function test_delete_restricted_to_admins(): void
    {
        $assessment = Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
        ]);

        $director = $this->academicDirector();
        $this->actingAs($director);

        $forbidden = $this->deleteJson($this->tenantUrl("/api/v1/assessments/{$assessment->id}"));
        $forbidden->assertStatus(403);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $deleted = $this->deleteJson($this->tenantUrl("/api/v1/assessments/{$assessment->id}"));
        $deleted->assertNoContent();
        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
    }

    // ---- Authorization failure --------------------------------------------------

    public function test_role_without_create_permission_is_forbidden(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Should not be created',
            'weight' => 10,
            'max_score' => 100,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('assessments', ['name' => 'Should not be created']);
    }

    // ---- Cross-school rejection -------------------------------------------------

    public function test_create_rejects_subject_from_a_different_school(): void
    {
        $admin = $this->admin();

        $otherSchool = School::factory()->create();
        $outsideSubject = Subject::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $outsideSubject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Cross-school Assessment',
            'weight' => 10,
            'max_score' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject_id']);
        $this->assertDatabaseMissing('assessments', ['name' => 'Cross-school Assessment']);
    }

    public function test_create_rejects_academic_session_from_a_different_school(): void
    {
        $admin = $this->admin();

        $otherSchool = School::factory()->create();
        $outsideSession = AcademicSession::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $outsideSession->id,
            'name' => 'Cross-school Assessment 2',
            'weight' => 10,
            'max_score' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject_id']);
        $this->assertDatabaseMissing('assessments', ['name' => 'Cross-school Assessment 2']);
    }

    // ---- Uniqueness -------------------------------------------------------------

    public function test_duplicate_name_for_same_subject_and_session_is_rejected(): void
    {
        Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Midterm Exam',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Midterm Exam',
            'weight' => 10,
            'max_score' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
        $this->assertSame(
            1,
            Assessment::where('subject_id', $this->subject->id)
                ->where('academic_session_id', $this->session->id)
                ->where('name', 'Midterm Exam')
                ->count()
        );
    }

    public function test_same_name_allowed_for_a_different_subject(): void
    {
        Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Midterm Exam',
        ]);

        $otherSubject = Subject::factory()->create(['school_id' => $this->school->id]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $otherSubject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Midterm Exam',
            'weight' => 10,
            'max_score' => 100,
        ]);

        $response->assertCreated();
    }

    // ---- weight/max_score bounds --------------------------------------------------

    public function test_weight_above_100_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Bad Weight',
            'weight' => 150,
            'max_score' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['weight']);
    }

    public function test_weight_below_0_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Negative Weight',
            'weight' => -5,
            'max_score' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['weight']);
    }

    public function test_max_score_below_1_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), [
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
            'name' => 'Bad Max Score',
            'weight' => 10,
            'max_score' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['max_score']);
    }

    public function test_missing_required_fields_fails_validation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assessments'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject_id', 'academic_session_id', 'name', 'weight', 'max_score']);
    }
}
