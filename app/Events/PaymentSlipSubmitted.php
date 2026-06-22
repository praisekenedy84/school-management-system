<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AuditableEvent;
use App\Models\PaymentSlip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Fired after a parent (or finance staff on a parent's behalf) submits a
 * payment slip (SKILLS Recipe D / ARCHITECTURE.md §5). NotifyFinanceTeam and
 * ConfirmToParent are still not wired (no notification engine yet); LogAudit
 * now is, via the `AuditableEvent` contract. The slip's own PaymentSlipLog
 * row, written in the same transaction, remains the detailed per-tenant
 * audit trail — this event additionally feeds the cross-tenant Platform
 * Admin activity log.
 */
class PaymentSlipSubmitted implements AuditableEvent
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
            'action' => 'payment_slip.submitted',
            'subject_type' => PaymentSlip::class,
            'subject_id' => $this->paymentSlip->id,
            'changes' => null,
        ];
    }
}
