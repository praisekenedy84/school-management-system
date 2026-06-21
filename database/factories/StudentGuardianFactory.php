<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentGuardian>
 */
class StudentGuardianFactory extends Factory
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
            'guardian_id' => User::factory(),
            'relationship' => fake()->randomElement(['mother', 'father', 'guardian']),
            'is_primary' => true,
        ];
    }
}
