<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseFulfillmentLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_fulfillment_id',
        'purchase_request_line_id',
        'inventory_item_id',
        'requested_quantity',
        'received_quantity',
        'requested_unit_cost',
        'actual_unit_cost',
        'line_notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'requested_unit_cost' => 'decimal:2',
            'actual_unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function purchaseFulfillment(): BelongsTo
    {
        return $this->belongsTo(PurchaseFulfillment::class);
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
