<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseFulfillmentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_request_line_id' => $this->purchase_request_line_id,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item' => InventoryItemResource::make($this->whenLoaded('inventoryItem')),
            'requested_quantity' => (string) $this->requested_quantity,
            'received_quantity' => (string) $this->received_quantity,
            'requested_unit_cost' => (string) $this->requested_unit_cost,
            'actual_unit_cost' => (string) $this->actual_unit_cost,
            'line_notes' => $this->line_notes,
        ];
    }
}
