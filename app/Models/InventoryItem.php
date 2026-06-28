<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use BelongsToSchool, HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'sku',
        'category',
        'unit',
        'current_quantity',
        'reorder_level',
        'unit_cost',
        'currency',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return bccomp((string) $this->current_quantity, (string) $this->reorder_level, 3) <= 0;
    }

    public function lineValue(): string
    {
        return bcmul((string) $this->current_quantity, (string) $this->unit_cost, 2);
    }

    /** Estimated cost to restock up to reorder level (zero when not below reorder). */
    public function restockValue(): string
    {
        $shortfall = bcsub((string) $this->reorder_level, (string) $this->current_quantity, 3);

        if (bccomp($shortfall, '0', 3) <= 0) {
            return '0.00';
        }

        return bcmul($shortfall, (string) $this->unit_cost, 2);
    }
}
