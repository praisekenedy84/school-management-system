<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\FeeStructure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'class_id' => ClassRoom::factory(),
            'fee_type' => fake()->randomElement(['Tuition', 'Boarding', 'Transport', 'Examination', 'Uniform']),
            'amount' => fake()->randomElement([50000, 100000, 250000, 500000, 1000000]),
            'is_mandatory' => true,
            'applicable_to' => fake()->randomElement(['all', 'day_only', 'boarding_only']),
            'installment_allowed' => false,
            'installment_count' => null,
            'due_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * AcademicSession (falling back to the ClassRoom) rather than creating
     * an unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (FeeStructure $feeStructure) {
            if ($feeStructure->school_id !== null) {
                return;
            }

            if ($feeStructure->academic_session_id !== null) {
                $feeStructure->school_id = AcademicSession::find($feeStructure->academic_session_id)?->school_id;
            }

            if ($feeStructure->school_id === null && $feeStructure->class_id !== null) {
                $feeStructure->school_id = ClassRoom::find($feeStructure->class_id)?->school_id;
            }
        });
    }
}
