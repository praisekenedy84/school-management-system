<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item' => InventoryItemResource::make($this->whenLoaded('inventoryItem')),
            'item_name' => $this->item_name,
            'unit' => $this->unit,
            'requested_quantity' => (string) $this->requested_quantity,
            'estimated_unit_cost' => (string) $this->estimated_unit_cost,
            'amended_quantity' => $this->amended_quantity !== null ? (string) $this->amended_quantity : null,
            'amended_unit_cost' => $this->amended_unit_cost !== null ? (string) $this->amended_unit_cost : null,
            'effective_quantity' => $this->effectiveQuantity(),
            'effective_unit_cost' => $this->effectiveUnitCost(),
            'estimated_line_total' => $this->estimatedLineTotal(),
            'effective_line_total' => $this->effectiveLineTotal(),
            'line_notes' => $this->line_notes,
        ];
    }
}
