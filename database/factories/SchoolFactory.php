<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' School',
            'code' => fake()->unique()->bothify('SCH-####??'),
            'locale' => 'en',
            'currency' => 'TZS',
            'timezone' => 'Africa/Dar_es_Salaam',
            'branding' => [
                'logo_path' => null,
                'primary_color' => fake()->hexColor(),
            ],
            'calendar_type' => 'standard',
            'grading_scale' => [],
            'fee_terms' => [],
            'hostel_available' => false,
            'is_active' => true,
        ];
    }
}
