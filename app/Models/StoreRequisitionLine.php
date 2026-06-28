<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreRequisitionLine extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_requisition_id',
        'inventory_item_id',
        'requested_quantity',
        'issued_quantity',
        'unit',
        'line_notes',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'issued_quantity' => 'decimal:3',
            'is_closed' => 'bool',
        ];
    }

    public function storeRequisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function remainingQuantity(): string
    {
        return bcsub((string) $this->requested_quantity, (string) $this->issued_quantity, 3);
    }

    public function isComplete(): bool
    {
        if ($this->is_closed) {
            return true;
        }

        return bccomp($this->remainingQuantity(), '0', 3) <= 0;
    }

    public function estimatedLineValue(): ?string
    {
        if (! $this->relationLoaded('inventoryItem') || $this->inventoryItem === null) {
            return null;
        }

        return bcmul((string) $this->requested_quantity, (string) $this->inventoryItem->unit_cost, 2);
    }
}
