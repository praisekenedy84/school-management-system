<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\PurchaseRequest;
use App\Models\StoreRequisition;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreRequisitionAddedToPurchase implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly StoreRequisition $storeRequisition,
        public readonly PurchaseRequest $purchaseRequest,
        public readonly string $mode,
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
            'action' => 'store_requisition.added_to_purchase',
            'subject_type' => StoreRequisition::class,
            'subject_id' => $this->storeRequisition->id,
            'changes' => [
                'purchase_request_id' => $this->purchaseRequest->id,
                'mode' => $this->mode,
            ],
        ];
    }
}
