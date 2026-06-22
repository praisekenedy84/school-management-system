<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hostel;
use App\Models\MealPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlan>
 */
class MealPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'name' => fake()->randomElement(['Standard Meals', 'Vegetarian', 'Premium Meals']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 20000, 150000),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MealPlan $mealPlan) {
            if ($mealPlan->school_id !== null || $mealPlan->hostel_id === null) {
                return;
            }

            $mealPlan->school_id = Hostel::find($mealPlan->hostel_id)?->school_id;
        });
    }
}
