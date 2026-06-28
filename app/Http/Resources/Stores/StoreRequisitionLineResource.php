<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreRequisitionLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item' => InventoryItemResource::make($this->whenLoaded('inventoryItem')),
            'requested_quantity' => (string) $this->requested_quantity,
            'issued_quantity' => (string) $this->issued_quantity,
            'remaining_quantity' => $this->remainingQuantity(),
            'estimated_line_value' => $this->estimatedLineValue(),
            'unit' => $this->unit,
            'line_notes' => $this->line_notes,
            'is_closed' => $this->is_closed,
        ];
    }
}
