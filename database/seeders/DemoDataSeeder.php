<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\FeeStructure;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\InventoryItem;
use App\Models\MealPlan;
use App\Models\PaymentMethod;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Stream;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Finance\PaymentSlipSubmissionService;
use App\Services\Finance\PaymentSlipVerificationService;
use App\Services\Hostel\HostelAllocationService;
use App\Services\Hostel\HostelLeaveService;
use App\Services\Stores\StoreRequisitionService;
use Illuminate\Database\Seeder;

/**
 * Realistic walk-through data for the demo tenant so every module (SIS,
 * academics, attendance, assessment, finance, hostel, the Phase 5
 * dashboards) has something to show on first login. Reuses the real
 * services (submission/verification, hostel allocation/leave) wherever one
 * exists, instead of poking rows in directly, so the data is exactly as
 * consistent as data created through the app would be.
 *
 * Intended to run against a freshly migrated tenant schema (`tenants:seed`
 * right after `tenants:migrate-fresh`). Re-runs on an existing demo tenant
 * skip duplicate fixtures but still back-fill missing slices (e.g. stores).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();

        if ($school === null) {
            return;
        }

        $school->update(['hostel_available' => true]);

        // Already seeded (e.g. re-run `tenants:seed` on an existing demo tenant).
        // Still back-fill newer module slices (stores) when missing.
        if (AcademicSession::query()->where('school_id', $school->id)->where('name', '2026/2027')->exists()) {
            $this->createStoresDataIfMissing($school);

            return;
        }

        $session = AcademicSession::factory()->create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $classes = $this->createClasses($school);
        $subjects = $this->createSubjects($school, $classes);
        $teachers = $this->createStaff($school);
        $this->assignTeachers($classes, $subjects, $session, $teachers);

        $students = $this->createStudents($school, $classes, $session);
        $this->createGuardians($students);
        $this->createAttendance($school, $students, $teachers['class_teacher']);
        $this->createAssessmentsAndResults($subjects, $session, $teachers);
        $this->createFinanceData($school, $classes, $session, $students, $teachers);
        $this->createHostelData($school, $session, $students, $teachers['hostel_manager']);
        $this->createStoresData($school, $teachers);
    }

    /** @return array<string, ClassRoom> */
    private function createClasses(School $school): array
    {
        $classes = [];

        foreach (['Form 1', 'Form 2', 'Form 3'] as $i => $name) {
            $class = ClassRoom::factory()->create([
                'school_id' => $school->id,
                'name' => $name,
                'level' => $i + 1,
            ]);
            Stream::factory()->create(['school_id' => $school->id, 'class_id' => $class->id, 'name' => 'Blue']);
            $classes[$name] = $class;
        }

        return $classes;
    }

    /** @param  array<string, ClassRoom>  $classes
     * @return array<int, Subject> */
    private function createSubjects(School $school, array $classes): array
    {
        $definitions = [
            'Mathematics' => 'MATH',
            'English' => 'ENG',
            'Kiswahili' => 'KIS',
            'Physics' => 'PHY',
            'Chemistry' => 'CHE',
            'Biology' => 'BIO',
        ];

        $subjects = [];

        foreach ($definitions as $name => $code) {
            $subjects[] = Subject::factory()->create([
                'school_id' => $school->id,
                'name' => $name,
                'code' => $code,
            ]);
        }

        foreach ($classes as $class) {
            $class->subjects()->sync(array_map(fn ($subject) => $subject->id, $subjects));
        }

        return $subjects;
    }

    /** @return array<string, User> staff keyed by role */
    private function createStaff(School $school): array
    {
        $staff = [];

        $names = [
            'class_teacher' => 'Grace Mwangi',
            'academic_director' => 'Daniel Kessy',
            'finance_manager' => 'Amina Hassan',
            'accountant' => 'Joseph Mollel',
            'hostel_manager' => 'Esther Nyerere',
            'storekeeper' => 'John Mwanga',
            'kitchen_staff' => 'Neema Saidi',
        ];

        foreach ($names as $role => $name) {
            $user = User::factory()->create([
                'school_id' => $school->id,
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@demo.sms.test',
            ]);
            $user->assignRole($role);
            $staff[$role] = $user;
        }

        $staff['teachers'] = collect(['Peter Mushi', 'Sarah Kileo', 'Michael Onyango'])
            ->map(function (string $name) use ($school) {
                $user = User::factory()->create([
                    'school_id' => $school->id,
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)).'@demo.sms.test',
                ]);
                $user->assignRole('teacher');

                return $user;
            })
            ->push($staff['class_teacher'])
            ->values();

        return $staff;
    }

    /**
     * @param  array<string, ClassRoom>  $classes
     * @param  array<int, Subject>  $subjects
     * @param  array<string, mixed>  $teachers
     */
    private function assignTeachers(array $classes, array $subjects, AcademicSession $session, array $teachers): void
    {
        $pool = $teachers['teachers'];
        $i = 0;

        foreach ($classes as $class) {
            foreach ($subjects as $subject) {
                TeacherAssignment::factory()->create([
                    'school_id' => $class->school_id,
                    'teacher_id' => $pool[$i % $pool->count()]->id,
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'academic_session_id' => $session->id,
                ]);
                $i++;
            }
        }
    }

    /**
     * @param  array<string, ClassRoom>  $classes
     * @return array<int, Student>
     */
    private function createStudents(School $school, array $classes, AcademicSession $session): array
    {
        $students = [];
        $genders = ['male', 'female'];
        $residences = ['day', 'day', 'boarding', 'boarding'];

        $i = 0;
        foreach ($classes as $class) {
            for ($n = 0; $n < 4; $n++) {
                $residence = $residences[$n % count($residences)];
                $student = Student::factory()->create([
                    'school_id' => $school->id,
                    'gender' => $genders[$i % 2],
                    'residence_type' => $residence,
                    'status' => 'active',
                ]);

                $student->enrolments()->create([
                    'school_id' => $school->id,
                    'class_id' => $class->id,
                    'academic_session_id' => $session->id,
                    'residence_type' => $residence,
                    'status' => 'active',
                    'enrolled_at' => $session->start_date,
                ]);

                $students[] = $student;
                $i++;
            }
        }

        return $students;
    }

    /** @param  array<int, Student>  $students */
    private function createGuardians(array $students): void
    {
        foreach (array_chunk($students, 2) as $siblings) {
            $parent = User::factory()->withoutSchool()->create([
                'name' => fake()->name(),
                'email' => 'parent.'.fake()->unique()->numerify('####').'@demo.sms.test',
            ]);
            $parent->assignRole('parent');

            foreach ($siblings as $i => $student) {
                StudentGuardian::factory()->create([
                    'student_id' => $student->id,
                    'guardian_id' => $parent->id,
                    'relationship' => $i === 0 ? 'mother' : 'father',
                    'is_primary' => $i === 0,
                ]);
            }
        }
    }

    /** @param  array<int, Student>  $students */
    private function createAttendance(School $school, array $students, User $classTeacher): void
    {
        foreach ($students as $student) {
            $enrolment = $student->enrolments()->first();

            foreach ([now()->toDateString(), now()->subDay()->toDateString()] as $date) {
                $student->attendanceRecords()->create([
                    'school_id' => $school->id,
                    'class_id' => $enrolment->class_id,
                    'academic_session_id' => $enrolment->academic_session_id,
                    'attendance_date' => $date,
                    'status' => fake()->randomElement(['present', 'present', 'present', 'absent', 'late']),
                    'recorded_by' => $classTeacher->id,
                ]);
            }
        }
    }

    /**
     * @param  array<int, Subject>  $subjects
     * @param  array<string, mixed>  $teachers
     */
    private function createAssessmentsAndResults(array $subjects, AcademicSession $session, array $teachers): void
    {
        $director = $teachers['academic_director'];

        foreach ($subjects as $subject) {
            $assessment = $subject->assessments()->create([
                'school_id' => $subject->school_id,
                'academic_session_id' => $session->id,
                'name' => 'Midterm Exam',
                'weight' => '40.00',
                'max_score' => '100.00',
                'created_by' => $director->id,
            ]);

            // Mark + publish results for students enrolled in classes this
            // subject is attached to.
            foreach ($subject->classRooms as $class) {
                foreach ($class->enrolments()->where('academic_session_id', $session->id)->get() as $enrolment) {
                    ResultRecord::create([
                        'school_id' => $subject->school_id,
                        'student_id' => $enrolment->student_id,
                        'academic_session_id' => $session->id,
                        'subject_id' => $subject->id,
                        'assessment_id' => $assessment->id,
                        'score' => fake()->randomFloat(2, 45, 98),
                        'grade' => fake()->randomElement(['A', 'B', 'C']),
                        'version' => 1,
                        'is_published' => true,
                        'published_by' => $director->id,
                        'published_at' => now(),
                        'entered_by' => $director->id,
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, ClassRoom>  $classes
     * @param  array<int, Student>  $students
     * @param  array<string, mixed>  $teachers
     */
    private function createFinanceData(School $school, array $classes, AcademicSession $session, array $students, array $teachers): void
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'school_id' => $school->id,
            'name' => 'CRDB Bank Transfer',
            'type' => 'bank_transfer',
            'bank_name' => 'CRDB',
        ]);

        foreach ($classes as $class) {
            FeeStructure::factory()->create([
                'school_id' => $school->id,
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'fee_type' => 'Tuition',
                'amount' => 500000,
                'applicable_to' => 'all',
                'created_by' => $teachers['finance_manager']->id,
            ]);
            FeeStructure::factory()->create([
                'school_id' => $school->id,
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'fee_type' => 'Boarding',
                'amount' => 300000,
                'applicable_to' => 'boarding_only',
                'created_by' => $teachers['finance_manager']->id,
            ]);
        }

        $submission = app(PaymentSlipSubmissionService::class);
        $verification = app(PaymentSlipVerificationService::class);

        $slips = [];
        foreach (array_slice($students, 0, 6) as $student) {
            $parentId = $student->guardians()->first()?->id ?? $teachers['finance_manager']->id;

            $slips[] = $submission->submit([
                'student_id' => $student->id,
                'payment_method_id' => $paymentMethod->id,
                'bank_name' => 'CRDB',
                'branch_name' => 'Samora Avenue Branch',
                'teller_number' => 'TLR-'.fake()->unique()->numerify('######'),
                'transaction_reference' => 'REF-'.fake()->unique()->numerify('########'),
                'depositor_name' => fake()->name(),
                'deposit_date' => now()->subDays(fake()->numberBetween(1, 10))->toDateString(),
                'total_amount' => 500000,
                'currency' => 'TZS',
                'allocation' => [
                    ['fee_type' => 'Tuition', 'amount' => 500000, 'academic_session_id' => $session->id],
                ],
            ], [], $parentId, null);
        }

        // Verify the first three, reject the fourth, leave the rest pending.
        $verification->verify($slips[0], $teachers['finance_manager']->id, 'Verified against the CRDB bank statement during demo data seeding.', null);
        $verification->verify($slips[1], $teachers['accountant']->id, 'Confirmed against mobile banking statement during demo data seeding.', null);
        $verification->verify($slips[2], $teachers['finance_manager']->id, 'Teller number matched bank confirmation during demo data seeding.', null);
        $verification->reject($slips[3], $teachers['accountant']->id, 'unclear_image', 'The uploaded slip image was too blurry to confirm the teller number and deposit date.', null);
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function createHostelData(School $school, AcademicSession $session, array $students, User $hostelManager): void
    {
        $boys = Hostel::factory()->create(['school_id' => $school->id, 'name' => 'Kilimanjaro House', 'gender' => 'male']);
        $girls = Hostel::factory()->create(['school_id' => $school->id, 'name' => 'Serengeti House', 'gender' => 'female']);

        $boysRooms = HostelRoom::factory()->count(2)->create(['school_id' => $school->id, 'hostel_id' => $boys->id, 'capacity' => 4]);
        $girlsRooms = HostelRoom::factory()->count(2)->create(['school_id' => $school->id, 'hostel_id' => $girls->id, 'capacity' => 4]);

        MealPlan::factory()->create(['school_id' => $school->id, 'hostel_id' => $boys->id, 'name' => 'Standard Meals']);
        MealPlan::factory()->create(['school_id' => $school->id, 'hostel_id' => $girls->id, 'name' => 'Standard Meals']);

        $allocationService = app(HostelAllocationService::class);
        $leaveService = app(HostelLeaveService::class);

        $boardingStudents = array_values(array_filter($students, fn (Student $s) => $s->residence_type === 'boarding'));
        $firstAllocation = null;

        foreach ($boardingStudents as $student) {
            $rooms = $student->gender === 'male' ? $boysRooms : $girlsRooms;
            $room = $rooms->first(fn (HostelRoom $r) => $r->activeOccupantCount() < $r->capacity);

            if ($room === null) {
                continue;
            }

            $allocation = $allocationService->allocate([
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'academic_session_id' => $session->id,
            ], $hostelManager->id);

            $firstAllocation ??= $allocation;
        }

        if ($firstAllocation !== null) {
            $parentId = $firstAllocation->student->guardians()->first()?->id;

            $leaveService->request([
                'hostel_allocation_id' => $firstAllocation->id,
                'reason' => 'Family function over the weekend.',
                'depart_at' => now()->addDays(3)->toDateString(),
                'return_at' => now()->addDays(5)->toDateString(),
            ], $parentId);
        }
    }

    /** @param  array<string, User>  $staff */
    private function createStoresData(School $school, array $staff): void
    {
        $storekeeper = $staff['storekeeper'];
        $kitchenStaff = $staff['kitchen_staff'];

        $rice = InventoryItem::factory()->create([
            'school_id' => $school->id,
            'name' => 'Rice',
            'category' => 'grains',
            'unit' => 'kg',
            'current_quantity' => '120.000',
            'reorder_level' => '20.000',
            'unit_cost' => '2500.00',
            'created_by' => $storekeeper->id,
        ]);

        $beans = InventoryItem::factory()->create([
            'school_id' => $school->id,
            'name' => 'Beans',
            'category' => 'grains',
            'unit' => 'kg',
            'current_quantity' => '15.000',
            'reorder_level' => '20.000',
            'unit_cost' => '1800.00',
            'created_by' => $storekeeper->id,
        ]);

        $requisitionService = app(StoreRequisitionService::class);

        $requisition = $requisitionService->createDraft([
            'school_id' => $school->id,
            'purpose' => 'Monday lunch service',
            'needed_by' => now()->addDay()->toDateString(),
            'lines' => [
                ['inventory_item_id' => $rice->id, 'requested_quantity' => '25'],
                ['inventory_item_id' => $beans->id, 'requested_quantity' => '10'],
            ],
        ], $kitchenStaff->id);

        $requisitionService->submit($requisition);
    }

    private function createStoresDataIfMissing(School $school): void
    {
        if (InventoryItem::query()->where('school_id', $school->id)->exists()) {
            return;
        }

        $storekeeper = User::query()
            ->where('school_id', $school->id)
            ->where('email', 'john.mwanga@demo.sms.test')
            ->first();

        if ($storekeeper === null) {
            $storekeeper = User::factory()->create([
                'school_id' => $school->id,
                'name' => 'John Mwanga',
                'email' => 'john.mwanga@demo.sms.test',
            ]);
            $storekeeper->assignRole('storekeeper');
        } elseif (! $storekeeper->hasRole('storekeeper')) {
            $storekeeper->assignRole('storekeeper');
        }

        $kitchenStaff = User::query()
            ->where('school_id', $school->id)
            ->where('email', 'neema.saidi@demo.sms.test')
            ->first();

        if ($kitchenStaff === null) {
            $kitchenStaff = User::factory()->create([
                'school_id' => $school->id,
                'name' => 'Neema Saidi',
                'email' => 'neema.saidi@demo.sms.test',
            ]);
            $kitchenStaff->assignRole('kitchen_staff');
        } elseif (! $kitchenStaff->hasRole('kitchen_staff')) {
            $kitchenStaff->assignRole('kitchen_staff');
        }

        $this->createStoresData($school, [
            'storekeeper' => $storekeeper,
            'kitchen_staff' => $kitchenStaff,
        ]);
    }
}
