<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => $this->category,
            'unit' => $this->unit,
            'current_quantity' => (string) $this->current_quantity,
            'reorder_level' => (string) $this->reorder_level,
            'unit_cost' => (string) $this->unit_cost,
            'line_value' => $this->lineValue(),
            'restock_value' => $this->restockValue(),
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->isLowStock(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
