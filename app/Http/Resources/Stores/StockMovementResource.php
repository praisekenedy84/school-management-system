<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item' => InventoryItemResource::make($this->whenLoaded('inventoryItem')),
            'direction' => $this->direction,
            'quantity' => (string) $this->quantity,
            'unit_cost' => (string) $this->unit_cost,
            'line_value' => bcmul((string) $this->quantity, (string) $this->unit_cost, 2),
            'balance_after' => (string) $this->balance_after,
            'reason' => $this->reason,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'performed_by' => $this->performed_by,
            'performed_at' => $this->performed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
