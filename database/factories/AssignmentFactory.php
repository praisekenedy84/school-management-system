<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_assignment_id' => TeacherAssignment::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'due_at' => fake()->dateTimeBetween('now', '+3 weeks'),
            'published_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` and `created_by`
     * (the teacher) from the parent TeacherAssignment rather than creating
     * an unrelated School/User, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Assignment $assignment) {
            if ($assignment->teacher_assignment_id === null) {
                return;
            }

            $teacherAssignment = TeacherAssignment::find($assignment->teacher_assignment_id);

            if ($teacherAssignment === null) {
                return;
            }

            if ($assignment->school_id === null) {
                $assignment->school_id = $teacherAssignment->school_id;
            }

            if ($assignment->created_by === null) {
                $assignment->created_by = $teacherAssignment->teacher_id;
            }
        });
    }

    /**
     * Unpublished (draft) assignment.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
}
