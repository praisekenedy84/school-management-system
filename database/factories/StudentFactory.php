<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'admission_number' => fake()->unique()->bothify('ADM-####??'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-4 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'residence_type' => fake()->randomElement(['day', 'boarding']),
            'status' => 'active',
            'admitted_at' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'photo_path' => null,
        ];
    }
}
