<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Events\PaymentSlipRejected;
use App\Events\PaymentSlipVerified;
use App\Models\Enrolment;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\PaymentReceipt;
use App\Models\PaymentSlip;
use App\Models\PaymentSlipLog;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\User;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * SKILLS Recipe E — the verify/reject workflow. Verification is the ONLY
 * place the ledger changes and a receipt is born, all inside ONE
 * DB::transaction (docs/prd-financial-module.md §9). "Record, don't
 * transact" still holds: we record that an externally-made payment was
 * confirmed by a human; no money moves.
 */
class PaymentSlipVerificationService
{
    /**
     * Verify a slip: flip status, update the per-session ledger(s), generate
     * the immutable receipt + per-fee-type FeePayment rows, log, emit event.
     *
     * Idempotent guard: a slip that already carries a receipt_number (or has
     * a PaymentReceipt row) is rejected with a 422, never re-verified or
     * double-receipted. Also guards against verifying a non-pending slip
     * (e.g. already rejected).
     *
     * @throws ValidationException on a non-verifiable slip (422, not 500)
     */
    public function verify(PaymentSlip $slip, string $verifiedBy, string $verificationNotes, ?string $ip): PaymentSlip
    {
        if ($slip->receipt_number !== null || $slip->receipt()->exists()) {
            throw ValidationException::withMessages([
                'payment_slip' => 'This slip has already been verified and a receipt issued; it cannot be verified again.',
            ]);
        }

        if (! $slip->isPending() && $slip->status !== 'under_review') {
            throw ValidationException::withMessages([
                'payment_slip' => "A slip with status '{$slip->status}' cannot be verified.",
            ]);
        }

        return DB::transaction(function () use ($slip, $verifiedBy, $verificationNotes, $ip) {
            // Re-read the row FOR UPDATE inside the txn to slam the door on a
            // concurrent second verify request between the guard above and
            // here (the guard alone is check-then-act; this lock makes it
            // atomic). Bypass SchoolScope so a tenant_admin can act too.
            $locked = PaymentSlip::withoutGlobalScope(SchoolScope::class)
                ->whereKey($slip->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->receipt_number !== null) {
                throw ValidationException::withMessages([
                    'payment_slip' => 'This slip has already been verified and a receipt issued; it cannot be verified again.',
                ]);
            }

            $now = now();
            $fromStatus = $locked->status;

            // (b) Ledger update, per academic_session present in allocation.
            $this->applyAllocationToLedgers($locked, Carbon::parse($locked->deposit_date));

            // (c) Receipt: number, PDF, row, FeePayment lines.
            $receiptNumber = $this->nextReceiptNumber($locked->school_id, $now);

            // (a) Slip transition — done before rendering so the receipt PDF
            // reflects verified state; persisted together at commit.
            $locked->fill([
                'status' => 'verified',
                'verified_by' => $verifiedBy,
                'verified_at' => $now,
                'verification_notes' => $verificationNotes,
                'receipt_number' => $receiptNumber,
                'receipt_generated_at' => $now,
                'receipt_generated_by' => $verifiedBy,
            ]);

            $student = Student::withoutGlobalScope(SchoolScope::class)->find($locked->student_id);

            $paymentDetails = [
                'slip_number' => $locked->slip_number,
                'depositor_name' => $locked->depositor_name,
                'bank_name' => $locked->bank_name,
                'teller_number' => $locked->teller_number,
                'deposit_date' => Carbon::parse($locked->deposit_date)->toDateString(),
                'total_amount' => $locked->total_amount,
                'currency' => $locked->currency,
                'allocation' => $locked->allocation,
                'student_name' => $student?->full_name,
                'admission_number' => $student?->admission_number,
            ];

            $pdfPath = $this->renderReceiptPdf($locked, $receiptNumber, $paymentDetails, $now);
            $locked->receipt_file_path = $pdfPath;
            $locked->save();

            $receipt = PaymentReceipt::create([
                'school_id' => $locked->school_id,
                'payment_slip_id' => $locked->id,
                'receipt_number' => $receiptNumber,
                'amount_in_words' => NumberToWords::money((string) $locked->total_amount, (string) $locked->currency),
                'payment_details' => $paymentDetails,
                'qr_code_path' => null, // DEFERRED: no QR package installed yet.
                'file_path' => $pdfPath,
                'generated_by' => $verifiedBy,
                'generated_at' => $now,
            ]);

            // One FeePayment per allocation line (normalized counterpart of
            // the slip's allocation JSONB — docs/prd-financial-module.md §5).
            foreach ((array) $locked->allocation as $line) {
                FeePayment::create([
                    'school_id' => $locked->school_id,
                    'payment_slip_id' => $locked->id,
                    'receipt_id' => $receipt->id,
                    'fee_type' => $line['fee_type'],
                    'amount' => number_format((float) $line['amount'], 2, '.', ''),
                    'academic_session_id' => $line['academic_session_id'],
                ]);
            }

            // (d) Audit log.
            PaymentSlipLog::create([
                'school_id' => $locked->school_id,
                'payment_slip_id' => $locked->id,
                'action' => 'verified',
                'from_status' => $fromStatus,
                'to_status' => 'verified',
                'performed_by' => $verifiedBy,
                'performer_role' => $this->performerRole($verifiedBy),
                'changes' => [
                    'receipt_number' => $receiptNumber,
                    'verification_notes' => $verificationNotes,
                ],
                'ip_address' => $ip,
            ]);

            // (e) Domain event (on the same committed state — listeners not
            // wired yet; see event docblock).
            PaymentSlipVerified::dispatch($locked, $receipt);

            return $locked->load('receipt');
        });
    }

    /**
     * Reject a slip: status only, no ledger/receipt change.
     *
     * @throws ValidationException on a non-rejectable slip
     */
    public function reject(PaymentSlip $slip, string $rejectedBy, string $category, string $reason, ?string $ip): PaymentSlip
    {
        if ($slip->isVerified() || $slip->receipt_number !== null) {
            throw ValidationException::withMessages([
                'payment_slip' => 'A verified slip with a generated receipt cannot be rejected; issue a correction instead.',
            ]);
        }

        if ($slip->isRejected()) {
            throw ValidationException::withMessages([
                'payment_slip' => 'This slip has already been rejected.',
            ]);
        }

        return DB::transaction(function () use ($slip, $rejectedBy, $category, $reason, $ip) {
            $fromStatus = $slip->status;

            $slip->update([
                'status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'rejection_category' => $category,
                'rejection_reason' => $reason,
            ]);

            PaymentSlipLog::create([
                'school_id' => $slip->school_id,
                'payment_slip_id' => $slip->id,
                'action' => 'rejected',
                'from_status' => $fromStatus,
                'to_status' => 'rejected',
                'performed_by' => $rejectedBy,
                'performer_role' => $this->performerRole($rejectedBy),
                'changes' => [
                    'rejection_category' => $category,
                    'rejection_reason' => $reason,
                ],
                'ip_address' => $ip,
            ]);

            PaymentSlipRejected::dispatch($slip);

            return $slip;
        });
    }

    /**
     * Applies each allocation line to the student's ledger for its
     * academic_session. First payment against a session creates the ledger
     * with total_assessed summed from that student's active FeeStructures
     * (class+session); subsequent payments just increment total_paid.
     *
     * CRITICAL: after every create/update we `->refresh()` the ledger before
     * reading `balance` — it's a STORED GENERATED column Eloquent does not
     * re-read on write, so payment_status would be computed from a stale
     * balance otherwise.
     */
    private function applyAllocationToLedgers(PaymentSlip $slip, Carbon $depositDate): void
    {
        // Sum allocation amounts per session (a slip may pay several fee
        // types within one session, or span sessions).
        $perSession = [];
        foreach ((array) $slip->allocation as $line) {
            $sessionId = $line['academic_session_id'];
            $perSession[$sessionId] = bcadd(
                $perSession[$sessionId] ?? '0',
                number_format((float) $line['amount'], 2, '.', ''),
                2
            );
        }

        foreach ($perSession as $sessionId => $amount) {
            $ledger = StudentFeeLedger::withoutGlobalScope(SchoolScope::class)
                ->where('student_id', $slip->student_id)
                ->where('academic_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($ledger === null) {
                $assessed = $this->assessedTotalFor($slip->student_id, $slip->school_id, $sessionId);

                $ledger = StudentFeeLedger::create([
                    'school_id' => $slip->school_id,
                    'student_id' => $slip->student_id,
                    'academic_session_id' => $sessionId,
                    'fee_details' => [],
                    'total_assessed' => $assessed,
                    'total_discounts' => '0.00',
                    'total_paid' => $amount,
                    'payment_status' => 'unpaid', // recomputed below post-refresh
                    'last_payment_date' => $depositDate->toDateString(),
                ]);
            } else {
                $ledger->update([
                    'total_paid' => bcadd((string) $ledger->total_paid, $amount, 2),
                    'last_payment_date' => $depositDate->toDateString(),
                ]);
            }

            // MUST refresh: `balance` is DB-generated and not reflected in the
            // in-memory model after write.
            $ledger->refresh();
            $ledger->update(['payment_status' => $this->statusFromBalance($ledger)]);
        }
    }

    /**
     * Sums active FeeStructure amounts for the student's class in the given
     * session. Falls back to 0 if the student has no enrolment for that
     * session (assessed stays 0 → an overpaid/credit balance, which the
     * status logic surfaces honestly rather than hiding).
     */
    private function assessedTotalFor(string $studentId, string $schoolId, string $sessionId): string
    {
        $enrolment = Enrolment::withoutGlobalScope(SchoolScope::class)
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->latest('enrolled_at')
            ->first();

        if ($enrolment === null) {
            return '0.00';
        }

        $sum = FeeStructure::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $enrolment->class_id)
            ->where('is_active', true)
            ->get()
            ->reduce(fn (string $carry, FeeStructure $fee) => bcadd($carry, (string) $fee->amount, 2), '0');

        return $sum;
    }

    private function statusFromBalance(StudentFeeLedger $ledger): string
    {
        $balance = (string) $ledger->balance;
        $paid = (string) $ledger->total_paid;

        if (bccomp($balance, '0', 2) < 0) {
            return 'overpaid';
        }

        if (bccomp($balance, '0', 2) === 0 && bccomp($paid, '0', 2) > 0) {
            return 'fully_paid';
        }

        if (bccomp($paid, '0', 2) > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }

    /**
     * Sequential `RCP-YYYYMMDD-NNNN`, per school per YEAR (receipt rule:
     * "sequential per school per year"). Same xact-scoped advisory-lock
     * pattern as slip numbering, keyed to school+year. MUST run inside the
     * transaction.
     */
    private function nextReceiptNumber(string $schoolId, Carbon $now): string
    {
        $year = $now->format('Y');

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["receipt:{$schoolId}:{$year}"]);

        $count = PaymentReceipt::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereYear('generated_at', $year)
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return 'RCP-'.$now->format('Ymd')."-{$sequence}";
    }

    /**
     * @param  array<string, mixed>  $paymentDetails
     */
    private function renderReceiptPdf(PaymentSlip $slip, string $receiptNumber, array $paymentDetails, Carbon $now): string
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'receiptNumber' => $receiptNumber,
            'slip' => $slip,
            'paymentDetails' => $paymentDetails,
            'amountInWords' => NumberToWords::money((string) $slip->total_amount, (string) $slip->currency),
            'generatedAt' => $now,
        ]);

        $tenantKey = (string) tenant()->getTenantKey();
        $year = $now->format('Y');
        $directory = "receipts/{$tenantKey}/{$slip->school_id}/{$year}";
        $relativePath = "{$directory}/{$receiptNumber}.pdf";

        Storage::makeDirectory($directory);
        Storage::put($relativePath, $pdf->output());

        return $relativePath;
    }

    private function performerRole(string $userId): ?string
    {
        $user = User::find($userId);

        return $user?->getRoleNames()->first();
    }
}
