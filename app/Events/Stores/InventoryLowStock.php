<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryLowStock implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InventoryItem $inventoryItem,
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
            'action' => 'inventory_item.low_stock',
            'subject_type' => InventoryItem::class,
            'subject_id' => $this->inventoryItem->id,
            'changes' => [
                'current_quantity' => (string) $this->inventoryItem->current_quantity,
                'reorder_level' => (string) $this->inventoryItem->reorder_level,
            ],
        ];
    }
}
