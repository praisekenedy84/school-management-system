<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->numberBetween(2023, 2026);

        return [
            'school_id' => School::factory(),
            'name' => "{$startYear}/".($startYear + 1),
            'start_date' => "{$startYear}-01-01",
            'end_date' => ($startYear + 1).'-12-31',
            'is_current' => false,
        ];
    }
}
