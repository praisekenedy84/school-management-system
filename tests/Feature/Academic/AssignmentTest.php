<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\Enrolment;
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
 * AssignmentController + CreateAssignmentRequest + AssignmentPolicy +
 * AssignmentVisibilityService — PROJECT-PLAN Phase 1 exit-criteria-critical:
 * "an assigned teacher publishes an assignment visible to that class +
 * guardians".
 *
 * Ownership: CreateAssignmentRequest::withValidator() rejects (422, field
 * `teacher_assignment_id`) a non-admin teacher creating an assignment under
 * a `teacher_assignment_id` they do not own — this is a FORM REQUEST
 * validation failure (422), NOT a policy/403, confirmed by reading the
 * source (validator->errors()->add(...), no AuthorizationException).
 *
 * Visibility (AssignmentVisibilityService::canView / scopeVisible):
 *  - tenant_admin / school_admin: always true.
 *  - the owning teacher (teacher_assignments.teacher_id === user.id): true.
 *  - a guardian of a student with an ACTIVE enrolment in the assignment's
 *    class: true (status must be 'active' — see scopeVisible/canView).
 *  - everyone else: false.
 *
 * AssignmentPolicy::view() delegates straight to canView() with no fallback,
 * so an unauthorized `show` is a Gate denial -> 403 (not 404), since the
 * route model binding itself succeeds (the row exists, $this->authorize()
 * is what fails). This test asserts 403 and documents that explicitly.
 */
class AssignmentTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private ClassRoom $classRoom;

    private AcademicSession $session;

    private Subject $subject;

    private User $owningTeacher;

    private TeacherAssignment $teacherAssignment;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->school = School::factory()->create();
        $this->classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $this->subject = Subject::factory()->create(['school_id' => $this->school->id]);

        $this->owningTeacher = User::factory()->create(['school_id' => $this->school->id]);
        $this->owningTeacher->assignRole('teacher');

        $this->teacherAssignment = TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $this->owningTeacher->id,
            'class_id' => $this->classRoom->id,
            'subject_id' => $this->subject->id,
            'academic_session_id' => $this->session->id,
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

    private function enrolStudentInClass(?string $status = 'active'): Student
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'status' => $status,
        ]);

        return $student;
    }

    public function test_export_returns_an_excel_file(): void
    {
        Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/assignments/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    // ---- Creation / ownership ------------------------------------------------

    public function test_teacher_can_create_assignment_under_their_own_teacher_assignment(): void
    {
        $this->actingAs($this->owningTeacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/assignments'), [
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'title' => 'Algebra homework',
            'description' => 'Chapter 4 exercises.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Algebra homework');
        $response->assertJsonPath('data.teacher_assignment_id', $this->teacherAssignment->id);

        $this->assertDatabaseHas('assignments', [
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'title' => 'Algebra homework',
            'created_by' => $this->owningTeacher->id,
        ]);
    }

    public function test_teacher_cannot_create_assignment_under_someone_elses_teacher_assignment(): void
    {
        $otherTeacher = User::factory()->create(['school_id' => $this->school->id]);
        $otherTeacher->assignRole('teacher');

        $this->actingAs($otherTeacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/assignments'), [
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'title' => 'Stolen assignment',
        ]);

        // CreateAssignmentRequest::withValidator() adds a validation error on
        // teacher_assignment_id rather than throwing AuthorizationException —
        // this is a 422, not a 403.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['teacher_assignment_id']);

        $this->assertDatabaseMissing('assignments', ['title' => 'Stolen assignment']);
    }

    public function test_admin_can_create_assignment_under_any_teacher_assignment(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/assignments'), [
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'title' => 'Admin-created assignment',
        ]);

        $response->assertCreated();
    }

    public function test_create_requires_teacher_assignment_id_and_title(): void
    {
        $this->actingAs($this->owningTeacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/assignments'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['teacher_assignment_id', 'title']);
    }

    public function test_role_without_create_permission_is_forbidden(): void
    {
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');

        $this->actingAs($parent);

        $response = $this->postJson($this->tenantUrl('/api/v1/assignments'), [
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'title' => 'Should not be created',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('assignments', ['title' => 'Should not be created']);
    }

    // ---- Publish --------------------------------------------------------------

    public function test_publish_sets_published_at(): void
    {
        $assignment = Assignment::factory()->draft()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);
        $this->assertNull($assignment->published_at);

        $this->actingAs($this->owningTeacher);

        $response = $this->patchJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}/publish"));

        $response->assertOk();
        $this->assertNotNull($response->json('data.published_at'));
        $this->assertTrue($response->json('data.is_published'));

        $assignment->refresh();
        $this->assertNotNull($assignment->published_at);
    }

    public function test_unrelated_teacher_cannot_publish(): void
    {
        $assignment = Assignment::factory()->draft()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $otherTeacher = User::factory()->create(['school_id' => $this->school->id]);
        $otherTeacher->assignRole('teacher');

        $this->actingAs($otherTeacher);

        $response = $this->patchJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}/publish"));

        $response->assertStatus(403);

        $assignment->refresh();
        $this->assertNull($assignment->published_at);
    }

    // ---- Visibility matrix (show) ---------------------------------------------

    public function test_owning_teacher_sees_assignment_via_show(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $this->actingAs($this->owningTeacher);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertOk();
        $response->assertJsonPath('data.id', $assignment->id);
    }

    public function test_school_admin_sees_assignment_via_show(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertOk();
    }

    public function test_tenant_admin_sees_assignment_via_show(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');

        $this->actingAs($tenantAdmin);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertOk();
    }

    public function test_guardian_of_enrolled_student_sees_assignment_via_show(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $student = $this->enrolStudentInClass();
        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertOk();
        $response->assertJsonPath('data.id', $assignment->id);
    }

    /**
     * Drafts (published_at null) must not be visible to guardians — only to
     * the owning teacher and admins. See AssignmentVisibilityService docblock.
     */
    public function test_guardian_of_enrolled_student_cannot_show_draft_assignment(): void
    {
        $assignment = Assignment::factory()->draft()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $student = $this->enrolStudentInClass();
        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertStatus(403);
    }

    public function test_owning_teacher_sees_their_own_draft_assignment_via_show(): void
    {
        $assignment = Assignment::factory()->draft()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $this->actingAs($this->owningTeacher);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertOk();
    }

    public function test_guardian_of_enrolled_student_does_not_see_draft_assignment_in_index(): void
    {
        $assignment = Assignment::factory()->draft()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $student = $this->enrolStudentInClass();
        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl('/api/v1/assignments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($assignment->id, $ids);
    }

    public function test_unrelated_teacher_cannot_show_assignment(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $otherTeacher = User::factory()->create(['school_id' => $this->school->id]);
        $otherTeacher->assignRole('teacher');

        $this->actingAs($otherTeacher);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        // AssignmentPolicy::view() delegates to canView(), which returns
        // false here -> Gate denial -> 403 (the route binding itself
        // succeeds; it's the authorize() call that fails). Not a 404.
        $response->assertStatus(403);
    }

    public function test_guardian_of_non_enrolled_student_cannot_show_assignment(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        // A different student, NOT enrolled in $this->classRoom.
        $otherClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $unrelatedStudent = Student::factory()->create(['school_id' => $this->school->id]);
        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $unrelatedStudent->id,
            'class_id' => $otherClass->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $guardian_id = $guardian->id;
        $unrelatedStudent->guardians()->attach($guardian_id, ['relationship' => 'father', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertStatus(403);
    }

    public function test_guardian_of_student_with_non_active_enrolment_cannot_show_assignment(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        // Enrolled in the right class, but the enrolment is not 'active'
        // (e.g. promoted out) — canView()/scopeVisible() both filter on
        // status === 'active'.
        $student = $this->enrolStudentInClass(status: 'promoted');
        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"));

        $response->assertStatus(403);
    }

    // ---- Visibility matrix (index) --------------------------------------------

    public function test_owning_teacher_sees_assignment_in_index(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $this->actingAs($this->owningTeacher);

        $response = $this->getJson($this->tenantUrl('/api/v1/assignments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($assignment->id));
    }

    public function test_guardian_of_enrolled_student_sees_assignment_in_index(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $student = $this->enrolStudentInClass();
        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl('/api/v1/assignments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($assignment->id));
    }

    public function test_unrelated_teacher_does_not_see_assignment_in_index(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $otherTeacher = User::factory()->create(['school_id' => $this->school->id]);
        $otherTeacher->assignRole('teacher');

        $this->actingAs($otherTeacher);

        $response = $this->getJson($this->tenantUrl('/api/v1/assignments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($assignment->id));
    }

    public function test_guardian_of_non_enrolled_student_does_not_see_assignment_in_index(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $otherClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $unrelatedStudent = Student::factory()->create(['school_id' => $this->school->id]);
        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $unrelatedStudent->id,
            'class_id' => $otherClass->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $unrelatedStudent->guardians()->attach($guardian->id, ['relationship' => 'father', 'is_primary' => true]);

        $this->actingAs($guardian);

        $response = $this->getJson($this->tenantUrl('/api/v1/assignments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($assignment->id));
    }

    public function test_school_admin_sees_assignment_in_index(): void
    {
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $this->teacherAssignment->id,
            'created_by' => $this->owningTeacher->id,
        ]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/assignments'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($assignment->id));
    }
}
