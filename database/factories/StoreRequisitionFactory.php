<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StoreRequisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreRequisition>
 */
class StoreRequisitionFactory extends Factory
{
    protected $model = StoreRequisition::class;

    public function definition(): array
    {
        return [
            'requisition_number' => 'REQ-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'requested_by' => User::factory(),
            'purpose' => fake()->sentence(8),
            'needed_by' => now()->addDays(fake()->numberBetween(1, 7))->toDateString(),
            'status' => 'draft',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (StoreRequisition $requisition) {
            if ($requisition->school_id !== null) {
                return;
            }

            if ($requisition->requested_by !== null) {
                $requisition->school_id = User::find($requisition->requested_by)?->school_id;
            }
        });
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted']);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
