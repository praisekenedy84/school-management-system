<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['CRDB Bank Transfer', 'NMB Bank Transfer', 'M-Pesa', 'Tigo Pesa', 'Direct Cash']),
            'type' => fake()->randomElement(['bank_transfer', 'cash_deposit', 'mobile_money', 'cheque', 'direct_cash']),
            'bank_name' => fake()->randomElement(['CRDB', 'NMB', 'NBC', null]),
            'account_number' => fake()->bothify('##########'),
            'account_name' => fake()->company(),
            'branch_code' => fake()->bothify('BR-###'),
            'swift_code' => fake()->bothify('????TZTZ'),
            'payment_instructions' => fake()->sentence(12),
            'is_active' => true,
        ];
    }
}
