<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'subject_id' => Subject::factory(),
            'name' => fake()->unique()->randomElement([
                'Midterm Exam', 'End of Term Exam', 'Quiz 1', 'Quiz 2', 'Mock Exam', 'CAT 1',
            ]),
            'category' => 'mid_term_exam',
            'weight' => fake()->randomElement(['10.00', '20.00', '30.00', '40.00']),
            'max_score' => '100.00',
            'created_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Subject rather than creating an unrelated School, so the row stays
     * internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Assessment $assessment) {
            if ($assessment->school_id !== null) {
                return;
            }

            if ($assessment->subject_id !== null) {
                $assessment->school_id = Subject::find($assessment->subject_id)?->school_id;
            }
        });
    }
}
