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
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Mirrors AcademicSchoolScopeTest for the Phase 2 Attendance/Assessment
 * models. All four use BelongsToSchool (AttendanceRecord, Assessment,
 * ResultRecord, ReportCard) so the campus boundary (RULES.md §1/§9) must
 * hold the same way: a school-A-scoped user's default Eloquent queries
 * never return school-B rows within the same tenant schema.
 */
class AttendanceAssessmentSchoolScopeTest extends TestCase
{
    use CreatesTenant;

    private School $schoolA;

    private School $schoolB;

    private AttendanceRecord $attendanceA;

    private AttendanceRecord $attendanceB;

    private Assessment $assessmentA;

    private Assessment $assessmentB;

    private ResultRecord $resultA;

    private ResultRecord $resultB;

    private ReportCard $reportCardA;

    private ReportCard $reportCardB;

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

        $subjectA = Subject::factory()->create(['school_id' => $this->schoolA->id]);
        $subjectB = Subject::factory()->create(['school_id' => $this->schoolB->id]);

        $this->attendanceA = AttendanceRecord::factory()->create([
            'school_id' => $this->schoolA->id,
            'student_id' => $studentA->id,
            'class_id' => $classA->id,
            'academic_session_id' => $sessionA->id,
        ]);
        $this->attendanceB = AttendanceRecord::factory()->create([
            'school_id' => $this->schoolB->id,
            'student_id' => $studentB->id,
            'class_id' => $classB->id,
            'academic_session_id' => $sessionB->id,
        ]);

        $this->assessmentA = Assessment::factory()->create([
            'school_id' => $this->schoolA->id,
            'subject_id' => $subjectA->id,
            'academic_session_id' => $sessionA->id,
        ]);
        $this->assessmentB = Assessment::factory()->create([
            'school_id' => $this->schoolB->id,
            'subject_id' => $subjectB->id,
            'academic_session_id' => $sessionB->id,
        ]);

        $this->resultA = ResultRecord::factory()->create([
            'school_id' => $this->schoolA->id,
            'student_id' => $studentA->id,
            'subject_id' => $subjectA->id,
            'academic_session_id' => $sessionA->id,
            'assessment_id' => $this->assessmentA->id,
        ]);
        $this->resultB = ResultRecord::factory()->create([
            'school_id' => $this->schoolB->id,
            'student_id' => $studentB->id,
            'subject_id' => $subjectB->id,
            'academic_session_id' => $sessionB->id,
            'assessment_id' => $this->assessmentB->id,
        ]);

        $this->reportCardA = ReportCard::factory()->create([
            'school_id' => $this->schoolA->id,
            'student_id' => $studentA->id,
            'academic_session_id' => $sessionA->id,
        ]);
        $this->reportCardB = ReportCard::factory()->create([
            'school_id' => $this->schoolB->id,
            'student_id' => $studentB->id,
            'academic_session_id' => $sessionB->id,
        ]);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_school_scoped_user_only_sees_their_own_campus_attendance_records(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $records = AttendanceRecord::all();

        $this->assertCount(1, $records);
        $this->assertSame($this->attendanceA->id, $records->first()->id);
        $this->assertNull(AttendanceRecord::find($this->attendanceB->id));
    }

    public function test_school_scoped_user_only_sees_their_own_campus_assessments(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $assessments = Assessment::all();

        $this->assertCount(1, $assessments);
        $this->assertSame($this->assessmentA->id, $assessments->first()->id);
        $this->assertNull(Assessment::find($this->assessmentB->id));
    }

    public function test_school_scoped_user_only_sees_their_own_campus_result_records(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $results = ResultRecord::all();

        $this->assertCount(1, $results);
        $this->assertSame($this->resultA->id, $results->first()->id);
        $this->assertNull(ResultRecord::find($this->resultB->id));
    }

    public function test_school_scoped_user_only_sees_their_own_campus_report_cards(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($userA);

        $reportCards = ReportCard::all();

        $this->assertCount(1, $reportCards);
        $this->assertSame($this->reportCardA->id, $reportCards->first()->id);
        $this->assertNull(ReportCard::find($this->reportCardB->id));
    }

    public function test_tenant_wide_user_with_null_school_id_sees_every_campus(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $this->actingAs($tenantAdmin);

        $this->assertCount(2, AttendanceRecord::all());
        $this->assertCount(2, Assessment::all());
        $this->assertCount(2, ResultRecord::all());
        $this->assertCount(2, ReportCard::all());
    }

    public function test_unauthenticated_context_is_unscoped(): void
    {
        Auth::logout();
        $this->assertNull(Auth::user());

        $this->assertCount(2, AttendanceRecord::all());
        $this->assertCount(2, Assessment::all());
        $this->assertCount(2, ResultRecord::all());
        $this->assertCount(2, ReportCard::all());
    }
}
