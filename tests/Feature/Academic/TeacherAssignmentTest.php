<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * TeacherAssignmentController. `teacher_assignments` has a DB-level UNIQUE
 * constraint `(teacher_id, class_id, subject_id, academic_session_id)`
 * (migration 2026_06_22_000005); TeacherAssignmentRequest mirrors it with a
 * `Rule::unique(...)->where(...)` check so duplicates surface as a clean 422
 * instead of an uncaught QueryException. The request also cross-checks that
 * teacher/class/subject all share one `school_id`.
 */
class TeacherAssignmentTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->school = School::factory()->create();
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

    public function test_create_happy_path(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/teacher-assignments'), [
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.teacher_id', $teacher->id);
        $response->assertJsonPath('data.class_id', $classRoom->id);
        $response->assertJsonPath('data.subject_id', $subject->id);

        $this->assertDatabaseHas('teacher_assignments', [
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);
    }

    /**
     * Without a cross-school check, a school_admin could assign a teacher
     * or class from a DIFFERENT campus in the same tenant — `Rule::exists`
     * alone doesn't constrain school_id.
     */
    public function test_create_rejects_a_class_from_a_different_school(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $otherSchool = School::factory()->create();
        $outsideClass = ClassRoom::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/teacher-assignments'), [
            'teacher_id' => $teacher->id,
            'class_id' => $outsideClass->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
        $this->assertDatabaseMissing('teacher_assignments', [
            'teacher_id' => $teacher->id,
            'class_id' => $outsideClass->id,
        ]);
    }

    /**
     * `teacher_assignments` has a unique composite on (teacher_id, class_id,
     * subject_id, academic_session_id). TeacherAssignmentRequest validates
     * this up front (Rule::unique scoped to the other three columns)
     * instead of letting the DB constraint throw an uncaught QueryException.
     */
    public function test_duplicate_tuple_is_rejected_with_a_clean_validation_error(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/teacher-assignments'), [
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['academic_session_id']);

        $this->assertSame(
            1,
            TeacherAssignment::where('teacher_id', $teacher->id)
                ->where('class_id', $classRoom->id)
                ->where('subject_id', $subject->id)
                ->where('academic_session_id', $session->id)
                ->count()
        );
    }

    public function test_filter_by_teacher_id_and_class_id(): void
    {
        $admin = $this->admin();
        $teacherA = User::factory()->create(['school_id' => $this->school->id]);
        $teacherB = User::factory()->create(['school_id' => $this->school->id]);
        $classA = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $classB = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $target = TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacherA->id,
            'class_id' => $classA->id,
        ]);

        TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacherB->id,
            'class_id' => $classB->id,
        ]);

        $this->actingAs($admin);

        $byTeacher = $this->getJson($this->tenantUrl("/api/v1/teacher-assignments?teacher_id={$teacherA->id}"));
        $byTeacher->assertOk();
        $byTeacher->assertJsonCount(1, 'data');
        $byTeacher->assertJsonPath('data.0.id', $target->id);

        $byClass = $this->getJson($this->tenantUrl("/api/v1/teacher-assignments?class_id={$classA->id}"));
        $byClass->assertOk();
        $byClass->assertJsonCount(1, 'data');
        $byClass->assertJsonPath('data.0.id', $target->id);
    }

    public function test_delete_restricted_to_admins(): void
    {
        $teacherAssignment = TeacherAssignment::factory()->create(['school_id' => $this->school->id]);

        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher);

        $response = $this->deleteJson($this->tenantUrl("/api/v1/teacher-assignments/{$teacherAssignment->id}"));
        $response->assertStatus(403);

        $this->assertDatabaseHas('teacher_assignments', ['id' => $teacherAssignment->id]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/teacher-assignments/{$teacherAssignment->id}"));
        $delete->assertNoContent();

        $this->assertDatabaseMissing('teacher_assignments', ['id' => $teacherAssignment->id]);
    }

    public function test_non_admin_cannot_create_teacher_assignment(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/teacher-assignments'), [
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_fails_validation_with_missing_fields(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/teacher-assignments'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['teacher_id', 'class_id', 'subject_id', 'academic_session_id']);
    }
}
