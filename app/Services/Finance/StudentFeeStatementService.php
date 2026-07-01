<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Enrolment;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\PaymentSlip;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Builds a parent-facing fee statement: per-item charged / paid / balance for
 * a student in an academic session. Paid amounts come from verified slips
 * only (FeePayment rows); pending slips are listed separately.
 */
class StudentFeeStatementService
{
    /**
     * @return array{
     *     student_id: string,
     *     academic_session_id: string,
     *     academic_session_name: string|null,
     *     lines: list<array{fee_type: string, total_charged: string, total_paid: string, balance: string}>,
     *     totals: array{total_charged: string, total_paid: string, balance: string},
     *     pending_slips: list<array{id: string, slip_number: string, total_amount: string, status: string}>
     * }
     */
    public function build(Student $student, string $academicSessionId): array
    {
        $enrolment = Enrolment::withoutGlobalScope(SchoolScope::class)
            ->with('academicSession')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $academicSessionId)
            ->where('status', 'active')
            ->first();

        $structures = $this->feeStructuresFor($student, $academicSessionId, $enrolment);
        $paidByType = $this->paidAmountsByFeeType($student->id, $academicSessionId);

        $lines = $structures->map(function (FeeStructure $structure) use ($paidByType) {
            $charged = number_format((float) $structure->amount, 2, '.', '');
            $paid = number_format((float) ($paidByType[$structure->fee_type] ?? 0), 2, '.', '');
            $balance = bcsub($charged, $paid, 2);
            if (bccomp($balance, '0', 2) < 0) {
                $balance = '0.00';
            }

            return [
                'fee_type' => $structure->fee_type,
                'total_charged' => $charged,
                'total_paid' => $paid,
                'balance' => $balance,
            ];
        })->values()->all();

        $totalCharged = $this->sumColumn($lines, 'total_charged');
        $totalPaid = $this->sumColumn($lines, 'total_paid');
        $totalBalance = bcsub($totalCharged, $totalPaid, 2);
        if (bccomp($totalBalance, '0', 2) < 0) {
            $totalBalance = '0.00';
        }

        $pendingSlips = PaymentSlip::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->orderByDesc('created_at')
            ->get(['id', 'slip_number', 'total_amount', 'status'])
            ->map(fn (PaymentSlip $slip) => [
                'id' => $slip->id,
                'slip_number' => $slip->slip_number,
                'total_amount' => number_format((float) $slip->total_amount, 2, '.', ''),
                'status' => $slip->status,
            ])
            ->values()
            ->all();

        return [
            'student_id' => $student->id,
            'academic_session_id' => $academicSessionId,
            'academic_session_name' => $enrolment?->academicSession?->name,
            'lines' => $lines,
            'totals' => [
                'total_charged' => $totalCharged,
                'total_paid' => $totalPaid,
                'balance' => $totalBalance,
            ],
            'pending_slips' => $pendingSlips,
        ];
    }

    /**
     * Fee types the student may allocate against in a given session.
     *
     * @return list<string>
     */
    public function allowedFeeTypes(Student $student, string $academicSessionId): array
    {
        $enrolment = Enrolment::withoutGlobalScope(SchoolScope::class)
            ->where('student_id', $student->id)
            ->where('academic_session_id', $academicSessionId)
            ->where('status', 'active')
            ->first();

        return $this->feeStructuresFor($student, $academicSessionId, $enrolment)
            ->pluck('fee_type')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{fee_type: string, total_charged: string, total_paid: string, balance: string}>  $lines
     */
    private function sumColumn(array $lines, string $key): string
    {
        return array_reduce(
            $lines,
            fn (string $carry, array $line) => bcadd($carry, $line[$key], 2),
            '0.00'
        );
    }

    /**
     * @return Collection<int, FeeStructure>
     */
    private function feeStructuresFor(Student $student, string $academicSessionId, ?Enrolment $enrolment): Collection
    {
        if ($enrolment === null) {
            return collect();
        }

        $residenceType = $enrolment->residence_type ?? $student->residence_type;

        return FeeStructure::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $student->school_id)
            ->where('academic_session_id', $academicSessionId)
            ->where('class_id', $enrolment->class_id)
            ->where('is_active', true)
            ->orderBy('fee_type')
            ->get()
            ->filter(fn (FeeStructure $structure) => $this->appliesToResidence($structure, $residenceType))
            ->values();
    }

    private function appliesToResidence(FeeStructure $structure, string $residenceType): bool
    {
        return match ($structure->applicable_to) {
            'day_only' => $residenceType === 'day',
            'boarding_only' => $residenceType === 'boarding',
            default => true,
        };
    }

    /**
     * @return array<string, float>
     */
    private function paidAmountsByFeeType(string $studentId, string $academicSessionId): array
    {
        return FeePayment::query()
            ->where('academic_session_id', $academicSessionId)
            ->whereHas('paymentSlip', fn ($query) => $query
                ->where('student_id', $studentId)
                ->where('status', 'verified'))
            ->get()
            ->groupBy('fee_type')
            ->map(fn (Collection $payments) => $payments->sum(fn (FeePayment $p) => (float) $p->amount))
            ->all();
    }
}
