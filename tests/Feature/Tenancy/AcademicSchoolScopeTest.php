<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Mirrors SchoolScopeTest for the Phase 1 SIS/Academic models. All four use
 * BelongsToSchool (Enrolment, Subject, TeacherAssignment, Assignment) so the
 * campus boundary (RULES.md §1/§9) must hold the same way: a school-A-scoped
 * user's default Eloquent queries never return school-B rows within the same
 * tenant schema.
 */
class AcademicSchoolScopeTest extends TestCase
{
    use CreatesTenant;

    private School $schoolA;

    private School $schoolB;

    private Enrolment $enrolmentA;

    private Enrolment $enrolmentB;

    private Subject $subjectA;

    private Subject $subjectB;

    private TeacherAssignment $teacherAssignmentA;

    private TeacherAssignment $teacherAssignmentB;

    private Assignment $assignmentA;

    private Assignment $assignmentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAndInitializeTenant();

        $this->schoolA = School::factory()->create(['name' => 'Campus A']);
        $this->schoolB = School::factory()->create(['name' => 'Campus B']);

        $studentA = Student::factory()->create(['school_id' => $this->schoolA->id]);
        $studentB = Student::factory()->create(['school_id' => $this->schoolB->id]);

        $classA = ClassRoom::factory()->create(['school_id' => $this->schoolA->id]);
        $classB = ClassRoom::factory()->create(['school_id' => $this->schoolB->id]);

        $sessionA = AcademicSession::factory()->create(['school_id' => $this->schoolA->id]);
        $sessionB = AcademicSession::factory()->create(['school_id' => $this->schoolB->id]);

        $this->enrolmentA = Enrolment::factory()->create([
            'school_id' => $this->schoolA->id,
            'student_id' => $studentA->id,
            'class_id' => $classA->id,
            'academic_session_id' => $sessionA->id,
        ]);
        $this->enrolmentB = Enrolment::factory()->create([
            'school_id' => $this->schoolB->id,
            'student_id' => $studentB->id,
            'class_id' => $classB->id,
            'academic_session_id' => $sessionB->id,
        ]);

        $this->subjectA = Subject::factory()->create(['school_id' => $this->schoolA->id]);
        $this->subjectB = Subject::factory()->create(['school_id' => $this->schoolB->id]);

        $teacherA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $teacherB = User::factory()->create(['school_id' => $this->schoolB->id]);

        $this->teacherAssignmentA = TeacherAssignment::factory()->create([
            'school_id' => $this->schoolA->id,
            'teacher_id' => $teacherA->id,
            'class_id' => $classA->id,
            'subject_id' => $this->subjectA->id,
            'academic_session_id' => $sessionA->id,
        ]);
        $this->teacherAssignmentB = TeacherAssignment::factory()->create([
            'school_id' => $this->schoolB->id,
            'teacher_id' => $teacherB->id,
            'class_id' => $classB->id,
            'subject_id' => $this->subjectB->id,
            'academic_session_id' => $sessionB->id,
        ]);

        $this->assignmentA = Assignment::factory()->create([
            'school_id' => $this->schoolA->id,
            'teacher_assignment_id' => $this->teacherAssignmentA->id,
            'created_by' => $teacherA->id,
        ]);
        $this->assignmentB = Assignment::factory()->create([
            'school_id' => $this->schoolB->id,
            'teacher_assignment_id' => $this->teacherAssignmentB->id,
            'created_by' => $teacherB->id,
        ]);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_school_scoped_user_only_sees_their_own_campus_enrolments(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $enrolments = Enrolment::all();

        $this->assertCount(1, $enrolments);
        $this->assertSame($this->enrolmentA->id, $enrolments->first()->id);
        $this->assertNull(Enrolment::find($this->enrolmentB->id));
    }

    public function test_school_scoped_user_only_sees_their_own_campus_subjects(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $subjects = Subject::all();

        $this->assertCount(1, $subjects);
        $this->assertSame($this->subjectA->id, $subjects->first()->id);
        $this->assertNull(Subject::find($this->subjectB->id));
    }

    public function test_school_scoped_user_only_sees_their_own_campus_teacher_assignments(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $assignments = TeacherAssignment::all();

        $this->assertCount(1, $assignments);
        $this->assertSame($this->teacherAssignmentA->id, $assignments->first()->id);
        $this->assertNull(TeacherAssignment::find($this->teacherAssignmentB->id));
    }

    public function test_school_scoped_user_only_sees_their_own_campus_assignments(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $assignments = Assignment::all();

        $this->assertCount(1, $assignments);
        $this->assertSame($this->assignmentA->id, $assignments->first()->id);
        $this->assertNull(Assignment::find($this->assignmentB->id));
    }

    public function test_tenant_wide_user_with_null_school_id_sees_every_campus(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $this->actingAs($tenantAdmin);

        $this->assertCount(2, Enrolment::all());
        $this->assertCount(2, Subject::all());
        $this->assertCount(2, TeacherAssignment::all());
        $this->assertCount(2, Assignment::all());
    }

    public function test_unauthenticated_context_is_unscoped(): void
    {
        Auth::logout();
        $this->assertNull(Auth::user());

        $this->assertCount(2, Enrolment::all());
        $this->assertCount(2, Subject::all());
        $this->assertCount(2, TeacherAssignment::all());
        $this->assertCount(2, Assignment::all());
    }
}
