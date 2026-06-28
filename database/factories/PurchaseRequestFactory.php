<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        return [
            'request_number' => 'PUR-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'requested_by' => User::factory(),
            'title' => fake()->sentence(4),
            'notes' => fake()->optional()->sentence(10),
            'status' => 'draft',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseRequest $request) {
            if ($request->school_id !== null) {
                return;
            }

            if ($request->requested_by !== null) {
                $request->school_id = User::find($request->requested_by)?->school_id;
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
