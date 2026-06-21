<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\ReportCard;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Mirrors AcademicSchemaIsolationTest for the Phase 2 Attendance/Assessment
 * tenant tables. These tables have NO `tenant_id` column — isolation is the
 * Postgres schema itself. We create rows while initialized on Tenant A,
 * switch to a completely different Tenant B, and assert the rows are simply
 * absent. One representative test per RULES.md §9 — doesn't need to be
 * exhaustive per-model again given AcademicSchemaIsolationTest already
 * proves the mechanism.
 */
class AttendanceAssessmentSchemaIsolationTest extends TestCase
{
    use CreatesTenant;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_attendance_and_assessment_rows_created_in_tenant_a_are_invisible_from_tenant_b(): void
    {
        $this->createAndInitializeTenant();

        $school = School::factory()->create();
        $classRoom = ClassRoom::factory()->create(['school_id' => $school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $school->id]);
        $subject = Subject::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create(['school_id' => $school->id]);

        AttendanceRecord::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
        ]);

        $assessment = Assessment::factory()->create([
            'school_id' => $school->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
        ]);

        ResultRecord::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'assessment_id' => $assessment->id,
        ]);

        ReportCard::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
        ]);

        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame(1, Assessment::count());
        $this->assertSame(1, ResultRecord::count());
        $this->assertSame(1, ReportCard::count());

        tenancy()->end();

        $this->createAndInitializeTenant();

        // Same Eloquent models, same table names, different Postgres schema.
        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame(0, Assessment::count());
        $this->assertSame(0, ResultRecord::count());
        $this->assertSame(0, ReportCard::count());

        tenancy()->end();
    }
}
