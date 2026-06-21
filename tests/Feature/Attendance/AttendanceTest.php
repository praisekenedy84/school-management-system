<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Events\AttendanceRecorded;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
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
 * AttendanceController + RecordAttendanceRequest + AttendanceService +
 * AttendanceRecordPolicy. POST /api/v1/attendance is idempotent by design
 * (SKILLS Recipe G) — re-submitting the same (student_id, attendance_date,
 * period) batch hits `updateOrCreate` and must update rows in place, not
 * duplicate or error.
 */
class AttendanceTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private ClassRoom $classRoom;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->school = School::factory()->create();
        $this->classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
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

    private function teacher(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('teacher');

        return $user;
    }

    /**
     * A teacher (or class_teacher) WITH a TeacherAssignment for
     * $this->classRoom + $this->session — required since
     * RecordAttendanceRequest now checks class-ownership for non-admin
     * roles (recording attendance for a class you don't teach is rejected).
     */
    private function teacherAssignedToClass(string $role = 'teacher'): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole($role);

        TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $user->id,
            'class_id' => $this->classRoom->id,
            'subject_id' => Subject::factory()->create(['school_id' => $this->school->id])->id,
            'academic_session_id' => $this->session->id,
        ]);

        return $user;
    }

    // ---- Happy path -----------------------------------------------------------

    public function test_record_attendance_batch_happy_path(): void
    {
        Event::fake([AttendanceRecorded::class]);

        $teacher = $this->teacherAssignedToClass();
        $studentA = Student::factory()->create(['school_id' => $this->school->id]);
        $studentB = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'period' => 'morning',
            'records' => [
                ['student_id' => $studentA->id, 'status' => 'present'],
                ['student_id' => $studentB->id, 'status' => 'absent', 'note' => 'Sick'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $studentA->id,
            'class_id' => $this->classRoom->id,
            'attendance_date' => '2026-06-15',
            'period' => 'morning',
            'status' => 'present',
            'recorded_by' => $teacher->id,
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $studentB->id,
            'status' => 'absent',
            'note' => 'Sick',
        ]);

        Event::assertDispatched(AttendanceRecorded::class, function (AttendanceRecorded $event) {
            return $event->classId === $this->classRoom->id
                && $event->academicSessionId === $this->session->id
                && $event->attendanceDate === '2026-06-15'
                && $event->period === 'morning'
                && $event->records->count() === 2;
        });
    }

    // ---- Idempotency ------------------------------------------------------------

    public function test_resubmitting_same_batch_updates_existing_rows_instead_of_duplicating(): void
    {
        $teacher = $this->teacherAssignedToClass();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);

        $first = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'period' => 'morning',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);
        $first->assertCreated();

        $this->assertSame(1, AttendanceRecord::count());
        $originalId = AttendanceRecord::first()->id;

        // Resubmit the SAME (student, date, period) with a DIFFERENT status —
        // must update the same row, not insert a second one.
        $second = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'period' => 'morning',
            'records' => [
                ['student_id' => $student->id, 'status' => 'late', 'note' => 'Traffic'],
            ],
        ]);
        $second->assertCreated();

        $this->assertSame(1, AttendanceRecord::count());

        $record = AttendanceRecord::first();
        $this->assertSame($originalId, $record->id);
        $this->assertSame('late', $record->status);
        $this->assertSame('Traffic', $record->note);
    }

    // ---- Cross-school rejection -------------------------------------------------

    public function test_rejects_class_from_a_different_school(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $otherSchool = School::factory()->create();
        $outsideClass = ClassRoom::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $outsideClass->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
        $this->assertDatabaseMissing('attendance_records', ['class_id' => $outsideClass->id]);
    }

    public function test_rejects_academic_session_from_a_different_school(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $otherSchool = School::factory()->create();
        $outsideSession = AcademicSession::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $outsideSession->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_rejects_a_student_from_a_different_school(): void
    {
        $admin = $this->admin();

        $otherSchool = School::factory()->create();
        $outsideStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $outsideStudent->id, 'status' => 'present'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['records']);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_teacher_without_a_matching_teacher_assignment_cannot_record_attendance_for_the_class(): void
    {
        $teacher = $this->teacher(); // no TeacherAssignment for $this->classRoom/$this->session
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
        $this->assertSame(0, AttendanceRecord::count());
    }

    // ---- Role checks --------------------------------------------------------------

    public function test_class_teacher_can_record_attendance(): void
    {
        $classTeacher = $this->teacherAssignedToClass('class_teacher');
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($classTeacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);

        $response->assertCreated();
    }

    public function test_academic_director_can_record_attendance(): void
    {
        $director = User::factory()->create(['school_id' => $this->school->id]);
        $director->assignRole('academic_director');
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($director);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);

        $response->assertCreated();
    }

    public function test_role_without_attendance_take_permission_is_forbidden(): void
    {
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($parent);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, AttendanceRecord::count());
    }

    // ---- GET index ------------------------------------------------------------

    public function test_index_returns_records_for_class_date_and_period(): void
    {
        $teacher = $this->teacher();
        $studentA = Student::factory()->create(['school_id' => $this->school->id]);
        $studentB = Student::factory()->create(['school_id' => $this->school->id]);

        AttendanceRecord::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'student_id' => $studentA->id,
            'attendance_date' => '2026-06-15',
            'period' => 'morning',
            'status' => 'present',
        ]);
        AttendanceRecord::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'student_id' => $studentB->id,
            'attendance_date' => '2026-06-15',
            'period' => 'afternoon', // different period -> excluded
            'status' => 'present',
        ]);
        AttendanceRecord::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-16', // different date -> excluded
            'period' => 'morning',
            'status' => 'present',
        ]);

        $this->actingAs($teacher);

        $response = $this->getJson($this->tenantUrl(
            "/api/v1/attendance?class_id={$this->classRoom->id}&attendance_date=2026-06-15&period=morning"
        ));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.student_id', $studentA->id);
    }

    // ---- Validation ------------------------------------------------------------

    public function test_invalid_status_value_is_rejected(): void
    {
        $teacher = $this->teacher();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'attendance_date' => '2026-06-15',
            'records' => [
                ['student_id' => $student->id, 'status' => 'not_a_real_status'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['records.0.status']);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_missing_required_fields_fails_validation(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/attendance'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id', 'academic_session_id', 'attendance_date', 'records']);
    }
}
