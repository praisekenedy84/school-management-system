<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hostel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hostel>
 */
class HostelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lastName().' House',
            'gender' => fake()->randomElement(['male', 'female', 'mixed']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
