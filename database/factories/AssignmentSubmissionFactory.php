<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_id' => Student::factory(),
            'submitted_at' => now(),
            'content' => fake()->paragraph(),
            'file_path' => null,
            'feedback' => null,
            'grade' => null,
            'graded_by' => null,
            'graded_at' => null,
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Assignment (falling back to the Student) rather than creating an
     * unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (AssignmentSubmission $submission) {
            if ($submission->school_id !== null) {
                return;
            }

            if ($submission->assignment_id !== null) {
                $submission->school_id = Assignment::find($submission->assignment_id)?->school_id;
            }

            if ($submission->school_id === null && $submission->student_id !== null) {
                $submission->school_id = Student::find($submission->student_id)?->school_id;
            }
        });
    }

    /**
     * Graded submission state.
     */
    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'feedback' => fake()->sentence(),
            'grade' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'graded_at' => now(),
        ]);
    }
}
