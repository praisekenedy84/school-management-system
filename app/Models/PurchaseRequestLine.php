<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_request_id',
        'inventory_item_id',
        'item_name',
        'unit',
        'requested_quantity',
        'estimated_unit_cost',
        'estimated_total',
        'line_notes',
        'amended_quantity',
        'amended_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'estimated_unit_cost' => 'decimal:2',
            'estimated_total' => 'decimal:2',
            'amended_quantity' => 'decimal:3',
            'amended_unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function effectiveQuantity(): string
    {
        return (string) ($this->amended_quantity ?? $this->requested_quantity);
    }

    public function effectiveUnitCost(): string
    {
        return (string) ($this->amended_unit_cost ?? $this->estimated_unit_cost);
    }

    public function estimatedLineTotal(): string
    {
        if ($this->estimated_total !== null) {
            return (string) $this->estimated_total;
        }

        return bcmul((string) $this->requested_quantity, (string) $this->estimated_unit_cost, 2);
    }

    public function effectiveLineTotal(): string
    {
        return bcmul($this->effectiveQuantity(), $this->effectiveUnitCost(), 2);
    }
}
