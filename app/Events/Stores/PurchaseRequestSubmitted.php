<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestSubmitted implements AuditableEvent
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
            'action' => 'purchase_request.submitted',
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $this->purchaseRequest->id,
            'changes' => [
                'request_number' => $this->purchaseRequest->request_number,
                'status' => $this->purchaseRequest->status,
            ],
        ];
    }
}
