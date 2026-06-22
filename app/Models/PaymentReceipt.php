<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PaymentReceiptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned. Sequential, immutable receipt
 * (RCP-YYYYMMDD-NNNN) generated only after a PaymentSlip is verified
 * (docs/prd-financial-module.md §3 "Receipt rules"). IMMUTABLE ONCE
 * CREATED per RULES.md / docs/prd-financial-module.md — no update endpoint
 * should ever target this model. Corrections happen by issuing a new,
 * separately-numbered receipt tied to a new/corrected slip, never by
 * mutating this row.
 */
class PaymentReceipt extends Model
{
    /** @use HasFactory<PaymentReceiptFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'payment_slip_id',
        'receipt_number',
        'amount_in_words',
        'payment_details',
        'qr_code_path',
        'file_path',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_details' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function paymentSlip(): BelongsTo
    {
        return $this->belongsTo(PaymentSlip::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'receipt_id');
    }
}
