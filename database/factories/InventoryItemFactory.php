<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->words(2, true),
            'sku' => fake()->optional()->bothify('SKU-####'),
            'category' => fake()->randomElement(['grains', 'vegetables', 'cleaning', 'dairy', 'spices']),
            'unit' => fake()->randomElement(['kg', 'L', 'pcs', 'bag', 'crate']),
            'current_quantity' => fake()->randomFloat(3, 0, 500),
            'reorder_level' => fake()->randomFloat(3, 5, 50),
            'unit_cost' => fake()->randomElement([500, 1000, 2500, 5000, 10000]),
            'currency' => 'TZS',
            'is_active' => true,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InventoryItem $item) {
            if ($item->school_id !== null && $item->created_by !== null) {
                return;
            }

            if ($item->created_by !== null) {
                $item->school_id ??= User::find($item->created_by)?->school_id;
            }
        });
    }

    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            $reorderLevel = fake()->randomFloat(3, 10, 50);

            return [
                'reorder_level' => $reorderLevel,
                'current_quantity' => fake()->randomFloat(3, 0, $reorderLevel),
            ];
        });
    }
}
