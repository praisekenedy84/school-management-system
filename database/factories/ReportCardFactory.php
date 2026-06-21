<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCard>
 */
class ReportCardFactory extends Factory
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
            'academic_session_id' => AcademicSession::factory(),
            'file_path' => 'report-cards/test-tenant/test-school/test-session/'.fake()->uuid().'.pdf',
            'generated_by' => User::factory(),
            'generated_at' => now(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Student rather than creating an unrelated School, so the row stays
     * internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ReportCard $reportCard) {
            if ($reportCard->school_id !== null) {
                return;
            }

            if ($reportCard->student_id !== null) {
                $reportCard->school_id = Student::find($reportCard->student_id)?->school_id;
            }
        });
    }
}
