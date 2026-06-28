<?php

declare(strict_types=1);

namespace App\Events\Stores;

use App\Contracts\AuditableEvent;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryItemChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'updated'|'deactivated'  $action
     */
    public function __construct(
        public readonly InventoryItem $inventoryItem,
        public readonly string $action,
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
            'action' => "inventory_item.{$this->action}",
            'subject_type' => InventoryItem::class,
            'subject_id' => $this->inventoryItem->id,
            'changes' => [
                'name' => $this->inventoryItem->name,
                'sku' => $this->inventoryItem->sku,
            ],
        ];
    }
}
