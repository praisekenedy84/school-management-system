<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AuditableEvent;
use App\Models\PaymentReceipt;
use App\Models\PaymentSlip;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Fired once a slip is verified AND its receipt has been generated, inside
 * the same DB transaction (docs/prd-financial-module.md §9: "emit
 * PaymentSlipVerified on commit"). GenerateReceipt/UpdateLedger run
 * synchronously inside the verification transaction itself (the receipt is
 * part of the verify response, so it can't be deferred to a listener);
 * NotifyParent/SyncHostelStatus are still not wired (no notification engine
 * yet). LogAudit now is, via the `AuditableEvent` contract, feeding the
 * cross-tenant Platform Admin activity log.
 */
class PaymentSlipVerified implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentSlip $paymentSlip,
        public readonly PaymentReceipt $receipt,
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
            'action' => 'payment_slip.verified',
            'subject_type' => PaymentSlip::class,
            'subject_id' => $this->paymentSlip->id,
            'changes' => ['receipt_number' => $this->receipt->receipt_number],
        ];
    }
}
