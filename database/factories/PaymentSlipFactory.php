<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentSlip;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentSlip>
 */
class PaymentSlipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Default `allocation` is realistic fixture data whose amounts sum to
     * `total_amount` — services (not this factory) are responsible for
     * validating that invariant at submission time, but fixtures should not
     * start out broken.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = fake()->randomElement([100000, 250000, 500000, 750000]);

        return [
            'slip_number' => 'SLP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'student_id' => Student::factory(),
            'submitted_by' => User::factory(),
            'payment_method_id' => null,
            'bank_name' => fake()->randomElement(['CRDB', 'NMB', 'NBC']),
            'branch_name' => fake()->city().' Branch',
            'teller_number' => fake()->unique()->numerify('TLR-######'),
            'transaction_reference' => Str::upper(fake()->bothify('REF-########')),
            'depositor_name' => fake()->name(),
            'deposit_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'total_amount' => $totalAmount,
            'currency' => 'TZS',
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => $totalAmount, 'academic_session_id' => null],
            ],
            'slip_attachments' => [],
            'status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'verification_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
            'approval_notes' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'rejection_category' => null,
            'receipt_number' => null,
            'receipt_generated_at' => null,
            'receipt_generated_by' => null,
            'receipt_file_path' => null,
            'submission_ip' => fake()->ipv4(),
            'device_info' => null,
            'notes' => null,
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Student rather than creating an unrelated School, so the row stays
     * internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PaymentSlip $paymentSlip) {
            if ($paymentSlip->school_id !== null) {
                return;
            }

            if ($paymentSlip->student_id !== null) {
                $paymentSlip->school_id = Student::find($paymentSlip->student_id)?->school_id;
            }
        });
    }

    /**
     * Verified state — sets verifier/timestamp; does NOT generate a receipt
     * (that's PaymentReceiptFactory's job, kept as a separate model so
     * tests compose the two explicitly rather than this factory reaching
     * across models).
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
            'verified_by' => User::factory(),
            'verified_at' => now(),
            'verification_notes' => fake()->sentence(10),
        ]);
    }

    /**
     * Rejected state — sets rejecter/timestamp/category/reason.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejected_by' => User::factory(),
            'rejected_at' => now(),
            'rejection_category' => fake()->randomElement(['incorrect_amount', 'unclear_image', 'wrong_details', 'duplicate', 'other']),
            'rejection_reason' => fake()->sentence(15),
        ]);
    }
}
