<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultRecord>
 */
class ResultRecordFactory extends Factory
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
            'assessment_id' => Assessment::factory(),
            'score' => fake()->randomFloat(2, 0, 100),
            'grade' => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'version' => 1,
            'is_published' => false,
            'published_by' => null,
            'published_at' => null,
            'entered_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` and
     * `academic_session_id`/`subject_id` from the parent Assessment
     * (falling back to the Student for `school_id`), so the row stays
     * internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ResultRecord $resultRecord) {
            $assessment = $resultRecord->assessment_id !== null
                ? Assessment::find($resultRecord->assessment_id)
                : null;

            if ($resultRecord->academic_session_id === null && $assessment !== null) {
                $resultRecord->academic_session_id = $assessment->academic_session_id;
            }

            if ($resultRecord->subject_id === null && $assessment !== null) {
                $resultRecord->subject_id = $assessment->subject_id;
            }

            if ($resultRecord->school_id === null && $assessment !== null) {
                $resultRecord->school_id = $assessment->school_id;
            }

            if ($resultRecord->school_id === null && $resultRecord->student_id !== null) {
                $resultRecord->school_id = Student::find($resultRecord->student_id)?->school_id;
            }
        });
    }

    /**
     * Published state — sets `is_published`, `published_by`, `published_at`.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_by' => User::factory(),
            'published_at' => now(),
        ]);
    }

    /**
     * A corrected version of a prior result (bumps `version`).
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }
}
