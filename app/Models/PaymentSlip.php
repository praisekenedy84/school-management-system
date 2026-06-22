<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PaymentSlipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant model, school-owned. Core finance record: parent-submitted evidence
 * of an externally-made payment (RULES.md / docs/prd-financial-module.md —
 * "record, don't transact").
 *
 * APPEND-ONLY / AUDITED per RULES.md §1/§3: status transitions
 * (pending -> under_review -> verified/approved/rejected/disputed/
 * clarification_needed) must go through a Service (e.g.
 * Finance\PaymentSlipService) that writes a matching PaymentSlipLog row and
 * emits the corresponding domain event (PaymentSlipSubmitted,
 * PaymentSlipVerified, PaymentSlipRejected, ...) in the same transaction.
 * Never call a bare `update()` against a slip's status from a controller —
 * that would silently break the audit trail this table exists to support.
 * Soft delete only; never hard delete (RULES.md §3 / §1).
 */
class PaymentSlip extends Model
{
    /** @use HasFactory<PaymentSlipFactory> */
    use BelongsToSchool, HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'slip_number',
        'student_id',
        'submitted_by',
        'payment_method_id',
        'bank_name',
        'branch_name',
        'teller_number',
        'transaction_reference',
        'depositor_name',
        'deposit_date',
        'total_amount',
        'currency',
        'allocation',
        'slip_attachments',
        'status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'rejection_category',
        'receipt_number',
        'receipt_generated_at',
        'receipt_generated_by',
        'receipt_file_path',
        'submission_ip',
        'device_info',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocation' => 'array',
            'slip_attachments' => 'array',
            'device_info' => 'array',
            'deposit_date' => 'date',
            'total_amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'receipt_generated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentSlipLog::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
