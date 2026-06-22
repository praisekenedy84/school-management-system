<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Events\PaymentSlipSubmitted;
use App\Models\PaymentSlip;
use App\Models\PaymentSlipLog;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SKILLS Recipe D — records parent-submitted payment evidence. "Record,
 * don't transact": this only persists a structured slip + its evidence and
 * raises the lifecycle to `pending`. No money moves; no ledger changes (that
 * happens only on verification).
 *
 * Everything below runs inside ONE DB::transaction so a failed file write,
 * number collision, or log insert rolls the whole thing back — there is never
 * a half-created slip without its first audit log row.
 */
class PaymentSlipSubmissionService
{
    /**
     * @param  array<string, mixed>  $data  validated SubmitPaymentSlipRequest payload (sans files)
     * @param  list<UploadedFile>  $attachments
     */
    public function submit(array $data, array $attachments, string $submittedBy, ?string $ip): PaymentSlip
    {
        // Derive school_id from the STUDENT, not the acting user: a parent's
        // own school_id may be null or differ; the slip belongs to the
        // student's campus. Read without SchoolScope so a tenant_admin
        // (null school_id) still resolves the right school — authorization
        // already confirmed the caller may submit for this student.
        $student = Student::withoutGlobalScope(SchoolScope::class)->findOrFail($data['student_id']);
        $schoolId = $student->school_id;

        $depositDate = Carbon::parse($data['deposit_date']);
        // The SLP number's own date is the SUBMISSION date (now), not the
        // deposit_date — `SLP-YYYYMMDD-NNNN` is "the Nth slip created on that
        // day". The count below and the date-part MUST use the same date or
        // the NNNN could collide across two distinct deposit dates submitted
        // on the same day.
        $submittedOn = Carbon::now();

        return DB::transaction(function () use ($data, $attachments, $submittedBy, $ip, $schoolId, $depositDate, $submittedOn) {
            // Serialize slip-number allocation for this (school, day) so two
            // concurrent submissions can't compute the same NNNN. The
            // xact-scoped advisory lock auto-releases on commit/rollback;
            // hashtext() maps the composite key to the bigint the lock API
            // wants. Counting AFTER acquiring the lock is what makes
            // COUNT(*)+1 race-free here.
            $slipNumber = $this->nextSlipNumber($schoolId, $submittedOn);

            $storedAttachments = $this->storeAttachments($attachments, $schoolId, $submittedOn);

            // Normalize allocation amounts to 2dp strings (money never float).
            $allocation = array_map(function (array $line) {
                return [
                    'fee_type' => $line['fee_type'],
                    'amount' => number_format((float) $line['amount'], 2, '.', ''),
                    'academic_session_id' => $line['academic_session_id'],
                ];
            }, $data['allocation']);

            $slip = PaymentSlip::create([
                'school_id' => $schoolId,
                'slip_number' => $slipNumber,
                'student_id' => $data['student_id'],
                'submitted_by' => $submittedBy,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'teller_number' => $data['teller_number'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'depositor_name' => $data['depositor_name'],
                'deposit_date' => $depositDate->toDateString(),
                'total_amount' => number_format((float) $data['total_amount'], 2, '.', ''),
                'currency' => $data['currency'] ?? 'TZS',
                'allocation' => $allocation,
                'slip_attachments' => $storedAttachments,
                'status' => 'pending',
                'submission_ip' => $ip,
                'notes' => $data['notes'] ?? null,
            ]);

            PaymentSlipLog::create([
                'school_id' => $schoolId,
                'payment_slip_id' => $slip->id,
                'action' => 'submitted',
                'from_status' => null,
                'to_status' => 'pending',
                'performed_by' => $submittedBy,
                'performer_role' => $this->performerRole($submittedBy),
                'changes' => [
                    'total_amount' => $slip->total_amount,
                    'allocation' => $allocation,
                    'slip_number' => $slipNumber,
                ],
                'ip_address' => $ip,
            ]);

            PaymentSlipSubmitted::dispatch($slip);

            return $slip;
        });
    }

    /**
     * Sequential `SLP-YYYYMMDD-NNNN`, per school per DAY. MUST be called
     * inside the transaction (the advisory lock is xact-scoped).
     */
    private function nextSlipNumber(string $schoolId, Carbon $date): string
    {
        $datePart = $date->format('Ymd');

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["slip:{$schoolId}:{$datePart}"]);

        // Count includes soft-deleted slips on purpose: a sequence number
        // already issued must never be reused even if that slip was later
        // soft-deleted (slip_number is UNIQUE and soft-delete keeps the row).
        $count = PaymentSlip::withoutGlobalScope(SchoolScope::class)
            ->withTrashed()
            ->where('school_id', $schoolId)
            ->whereDate('created_at', $date->toDateString())
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return "SLP-{$datePart}-{$sequence}";
    }

    /**
     * Stores each upload under the tenant/school-scoped path
     * (ARCHITECTURE.md §7). Thumbnails are a deferred no-op this pass: we
     * record the same path as thumbnail_path so the JSON shape is stable;
     * real image resizing (Intervention Image) is a follow-up.
     *
     * @param  list<UploadedFile>  $attachments
     * @return list<array<string, mixed>>
     */
    private function storeAttachments(array $attachments, string $schoolId, Carbon $date): array
    {
        $tenantKey = (string) tenant()->getTenantKey();
        $year = $date->format('Y');
        $month = $date->format('m');

        $originalDir = "payment-slips/original/{$tenantKey}/{$year}/{$month}";
        $thumbnailDir = "payment-slips/thumbnails/{$tenantKey}/{$year}/{$month}";

        $stored = [];

        foreach ($attachments as $file) {
            $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
            $filename = Str::uuid()->toString().'.'.$extension;

            $path = $file->storeAs($originalDir, $filename);

            // Deferred: real thumbnail generation. Copy the original into the
            // thumbnails dir so a thumbnail_path always resolves to a file;
            // swap for an actual resize later.
            $thumbnailPath = "{$thumbnailDir}/{$filename}";
            Storage::makeDirectory($thumbnailDir);
            Storage::copy($path, $thumbnailPath);

            $stored[] = [
                'file_path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'file_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        return $stored;
    }

    private function performerRole(string $userId): ?string
    {
        $user = User::find($userId);

        return $user?->getRoleNames()->first();
    }
}
