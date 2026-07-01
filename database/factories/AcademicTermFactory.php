<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    protected $model = AcademicTerm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'name' => fake()->randomElement(['Term I', 'Term II', 'Term III']),
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AcademicTerm $term) {
            if ($term->school_id === null && $term->academic_session_id !== null) {
                $term->school_id = AcademicSession::find($term->academic_session_id)?->school_id;
            }
        });
    }
}
