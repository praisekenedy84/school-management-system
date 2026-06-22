<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PaymentSlipLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. Append-only audit trail of every PaymentSlip
 * state transition (RULES.md §1/§5). Per
 * database/migrations/tenant/..._create_payment_slip_logs_table.php this
 * table only has `created_at` (no `updated_at`, no soft deletes — there is
 * nothing to "undo" on a pure log). `const UPDATED_AT = null` (rather than
 * `public $timestamps = false`) is the correct fix here: it tells Eloquent
 * there is no `updated_at` column to touch while still letting `created_at`
 * get set automatically on insert, which the migration's
 * `useCurrent()`/HasUuids-style creation flow expects.
 */
class PaymentSlipLog extends Model
{
    /** @use HasFactory<PaymentSlipLogFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * No `updated_at` column exists on this table — it is insert-only.
     */
    const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'payment_slip_id',
        'action',
        'from_status',
        'to_status',
        'performed_by',
        'performer_role',
        'changes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function paymentSlip(): BelongsTo
    {
        return $this->belongsTo(PaymentSlip::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
