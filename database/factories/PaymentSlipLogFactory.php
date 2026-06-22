<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentSlip;
use App\Models\PaymentSlipLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSlipLog>
 */
class PaymentSlipLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_slip_id' => PaymentSlip::factory(),
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => 'pending',
            'performed_by' => User::factory(),
            'performer_role' => 'parent',
            'changes' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * PaymentSlip rather than creating an unrelated School, so the row
     * stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PaymentSlipLog $log) {
            if ($log->school_id !== null) {
                return;
            }

            if ($log->payment_slip_id !== null) {
                $log->school_id = PaymentSlip::find($log->payment_slip_id)?->school_id;
            }
        });
    }

    /**
     * A "verified" transition log entry.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'verified',
            'from_status' => 'pending',
            'to_status' => 'verified',
            'performer_role' => 'finance_manager',
        ]);
    }

    /**
     * A "rejected" transition log entry.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'rejected',
            'from_status' => 'pending',
            'to_status' => 'rejected',
            'performer_role' => 'finance_manager',
        ]);
    }
}
