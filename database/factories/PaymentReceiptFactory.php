<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentReceipt;
use App\Models\PaymentSlip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentReceipt>
 */
class PaymentReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_slip_id' => PaymentSlip::factory()->verified(),
            'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'amount_in_words' => 'Five hundred thousand shillings only',
            'payment_details' => [
                'total_amount' => 500000,
                'currency' => 'TZS',
            ],
            'qr_code_path' => null,
            'file_path' => null,
            'generated_by' => User::factory(),
            'generated_at' => now(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * PaymentSlip rather than creating an unrelated School, so the row
     * stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PaymentReceipt $receipt) {
            if ($receipt->school_id !== null) {
                return;
            }

            if ($receipt->payment_slip_id !== null) {
                $receipt->school_id = PaymentSlip::find($receipt->payment_slip_id)?->school_id;
            }
        });
    }
}
