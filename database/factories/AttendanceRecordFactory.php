<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'class_id' => ClassRoom::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'attendance_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'period' => fake()->randomElement(['morning', 'afternoon', null]),
            'status' => fake()->randomElement(['present', 'absent', 'late', 'excused']),
            'note' => null,
            'recorded_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Student (falling back to the ClassRoom) rather than creating an
     * unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (AttendanceRecord $attendanceRecord) {
            if ($attendanceRecord->school_id !== null) {
                return;
            }

            if ($attendanceRecord->student_id !== null) {
                $attendanceRecord->school_id = Student::find($attendanceRecord->student_id)?->school_id;
            }

            if ($attendanceRecord->school_id === null && $attendanceRecord->class_id !== null) {
                $attendanceRecord->school_id = ClassRoom::find($attendanceRecord->class_id)?->school_id;
            }
        });
    }

    /**
     * Mark the attendance as absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
        ]);
    }
}
