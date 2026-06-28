<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\StoreRequisition;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreRequisitionIssued implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly StoreRequisition $storeRequisition,
        public readonly bool $isPartial,
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
            'action' => 'store_requisition.issued',
            'subject_type' => StoreRequisition::class,
            'subject_id' => $this->storeRequisition->id,
            'changes' => [
                'status' => $this->storeRequisition->status,
                'is_partial' => $this->isPartial,
                'lines' => $this->storeRequisition->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'issued_quantity' => (string) $line->issued_quantity,
                    'requested_quantity' => (string) $line->requested_quantity,
                    'is_closed' => $line->is_closed,
                ])->all(),
            ],
        ];
    }
}
