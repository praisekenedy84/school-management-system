<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestAmended implements AuditableEvent
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
            'action' => 'purchase_request.amended',
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $this->purchaseRequest->id,
            'changes' => [
                'amendment_notes' => $this->purchaseRequest->amendment_notes,
                'lines' => $this->purchaseRequest->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'amended_quantity' => $line->amended_quantity !== null ? (string) $line->amended_quantity : null,
                    'amended_unit_cost' => $line->amended_unit_cost !== null ? (string) $line->amended_unit_cost : null,
                ])->all(),
            ],
        ];
    }
}
