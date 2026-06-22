<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\FeePaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. Per-fee-type breakdown of a verified slip's
 * allocation, linked to the receipt that evidences it — the normalized
 * counterpart to PaymentSlip::allocation (JSONB). Used for ledger updates
 * and per-fee-type reporting (docs/prd-financial-module.md §5).
 */
class FeePayment extends Model
{
    /** @use HasFactory<FeePaymentFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'payment_slip_id',
        'receipt_id',
        'fee_type',
        'amount',
        'academic_session_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function paymentSlip(): BelongsTo
    {
        return $this->belongsTo(PaymentSlip::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'receipt_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
