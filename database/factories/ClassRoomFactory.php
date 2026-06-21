<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassRoom>
 */
class ClassRoomFactory extends Factory
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
            'name' => 'Form '.fake()->unique()->numberBetween(1, 6),
            'level' => fake()->numberBetween(1, 6),
        ];
    }
}
