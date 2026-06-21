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
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Mirrors SchemaIsolationTest for the Phase 1 SIS/Academic tenant tables.
 * These tables have NO `tenant_id` column — isolation is the Postgres
 * schema itself. We create rows while initialized on Tenant A, switch to a
 * completely different Tenant B, and assert the rows are simply absent.
 */
class AcademicSchemaIsolationTest extends TestCase
{
    use CreatesTenant;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_sis_and_academic_rows_created_in_tenant_a_are_invisible_from_tenant_b(): void
    {
        $tenantA = $this->createAndInitializeTenant();

        $school = School::factory()->create();
        $classRoom = ClassRoom::factory()->create(['school_id' => $school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $school->id]);
        $subject = Subject::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create(['school_id' => $school->id]);
        $teacher = User::factory()->create(['school_id' => $school->id]);

        Enrolment::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
        ]);

        $teacherAssignment = TeacherAssignment::factory()->create([
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        Assignment::factory()->create([
            'school_id' => $school->id,
            'teacher_assignment_id' => $teacherAssignment->id,
            'created_by' => $teacher->id,
        ]);

        $this->assertSame(1, Enrolment::count());
        $this->assertSame(1, Subject::count());
        $this->assertSame(1, TeacherAssignment::count());
        $this->assertSame(1, Assignment::count());

        tenancy()->end();

        $this->createAndInitializeTenant();

        // Same Eloquent models, same table names, different Postgres schema.
        $this->assertSame(0, Enrolment::count());
        $this->assertSame(0, Subject::count());
        $this->assertSame(0, TeacherAssignment::count());
        $this->assertSame(0, Assignment::count());

        tenancy()->end();
    }
}
