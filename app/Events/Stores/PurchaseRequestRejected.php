<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestRejected implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
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
            'action' => 'purchase_request.rejected',
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $this->purchaseRequest->id,
            'changes' => [
                'rejection_reason' => $this->purchaseRequest->rejection_reason,
            ],
        ];
    }
}
