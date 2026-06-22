<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AuditableEvent;
use App\Models\PaymentSlip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Fired after a slip is rejected (SKILLS Recipe E / ARCHITECTURE.md §5). No
 * ledger change accompanies a rejection. NotifyParentWithReason is still not
 * wired (no notification engine yet); LogAudit now is, via the
 * `AuditableEvent` contract. The PaymentSlipLog row written in the same
 * transaction remains the detailed per-tenant audit trail.
 */
class PaymentSlipRejected implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentSlip $paymentSlip,
    ) {}

    public function toAuditLog(): array
    {
        $actor = Auth::user();

        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'action' => 'payment_slip.rejected',
            'subject_type' => PaymentSlip::class,
            'subject_id' => $this->paymentSlip->id,
            'changes' => ['rejection_category' => $this->paymentSlip->rejection_category],
        ];
    }
}
