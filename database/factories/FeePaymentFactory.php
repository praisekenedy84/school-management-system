<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\PaymentReceipt;
use App\Models\PaymentSlip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePayment>
 */
class FeePaymentFactory extends Factory
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
            'receipt_id' => PaymentReceipt::factory(),
            'fee_type' => fake()->randomElement(['Tuition', 'Boarding', 'Transport', 'Examination']),
            'amount' => fake()->randomElement([50000, 100000, 250000, 500000]),
            'academic_session_id' => AcademicSession::factory(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * PaymentSlip (falling back to the PaymentReceipt) rather than creating
     * an unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (FeePayment $feePayment) {
            if ($feePayment->school_id !== null) {
                return;
            }

            if ($feePayment->payment_slip_id !== null) {
                $feePayment->school_id = PaymentSlip::find($feePayment->payment_slip_id)?->school_id;
            }

            if ($feePayment->school_id === null && $feePayment->receipt_id !== null) {
                $feePayment->school_id = PaymentReceipt::find($feePayment->receipt_id)?->school_id;
            }
        });
    }
}
