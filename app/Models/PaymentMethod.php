<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned. Per-school configuration of accepted payment
 * channels (bank transfer, mobile money, cash, ...) referenced by
 * PaymentSlip::payment_method_id.
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'type',
        'bank_name',
        'account_number',
        'account_name',
        'branch_code',
        'swift_code',
        'payment_instructions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    public function paymentSlips(): HasMany
    {
        return $this->hasMany(PaymentSlip::class);
    }
}
