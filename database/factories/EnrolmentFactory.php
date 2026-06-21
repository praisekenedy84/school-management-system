<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrolment>
 */
class EnrolmentFactory extends Factory
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
            'residence_type' => fake()->randomElement(['day', 'boarding']),
            'status' => 'active',
            'enrolled_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Student (falling back to the ClassRoom) rather than creating an
     * unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Enrolment $enrolment) {
            if ($enrolment->school_id !== null) {
                return;
            }

            if ($enrolment->student_id !== null) {
                $enrolment->school_id = Student::find($enrolment->student_id)?->school_id;
            }

            if ($enrolment->school_id === null && $enrolment->class_id !== null) {
                $enrolment->school_id = ClassRoom::find($enrolment->class_id)?->school_id;
            }
        });
    }
}
