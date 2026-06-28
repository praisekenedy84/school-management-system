<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\PurchaseFulfillment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestFulfilled implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PurchaseFulfillment $purchaseFulfillment,
        public readonly ?User $actor,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'actor_email' => $this->actor?->email,
            'action' => 'purchase_request.fulfilled',
            'subject_type' => PurchaseFulfillment::class,
            'subject_id' => $this->purchaseFulfillment->id,
            'changes' => [
                'fulfillment_number' => $this->purchaseFulfillment->fulfillment_number,
                'purchase_request_id' => $this->purchaseFulfillment->purchase_request_id,
                'total_cost' => (string) $this->purchaseFulfillment->total_cost,
            ],
        ];
    }
}
