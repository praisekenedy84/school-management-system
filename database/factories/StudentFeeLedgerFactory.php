<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentFeeLedger>
 *
 * Deliberately does NOT set `balance` in `definition()` — it is a Postgres
 * STORED GENERATED column (total_assessed - total_discounts - total_paid).
 * It is excluded from $fillable on the model, so even if a caller passed
 * `balance` into `StudentFeeLedger::factory()->create(['balance' => ...])`,
 * Eloquent would silently drop the unlisted key on `fill()`/mass-assignment;
 * Postgres itself would also reject a raw UPDATE/INSERT naming a generated
 * column. `create()` simply lets Postgres compute it from the other three
 * totals on insert.
 */
class StudentFeeLedgerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAssessed = fake()->randomElement([500000, 750000, 1000000, 1500000]);
        $totalPaid = fake()->randomElement([0, 250000, 500000, $totalAssessed]);

        return [
            'student_id' => Student::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'fee_details' => [
                ['fee_type' => 'Tuition', 'amount' => $totalAssessed, 'is_paid' => $totalPaid >= $totalAssessed],
            ],
            'total_assessed' => $totalAssessed,
            'total_discounts' => 0,
            'total_paid' => $totalPaid,
            'payment_status' => match (true) {
                $totalPaid <= 0 => 'unpaid',
                $totalPaid < $totalAssessed => 'partially_paid',
                $totalPaid === $totalAssessed => 'fully_paid',
                default => 'overpaid',
            },
            'last_payment_date' => $totalPaid > 0 ? fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d') : null,
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * Student (falling back to the AcademicSession) rather than creating an
     * unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (StudentFeeLedger $ledger) {
            if ($ledger->school_id !== null) {
                return;
            }

            if ($ledger->student_id !== null) {
                $ledger->school_id = Student::find($ledger->student_id)?->school_id;
            }

            if ($ledger->school_id === null && $ledger->academic_session_id !== null) {
                $ledger->school_id = AcademicSession::find($ledger->academic_session_id)?->school_id;
            }
        });
    }
}
