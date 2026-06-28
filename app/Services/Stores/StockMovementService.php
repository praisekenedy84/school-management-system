<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Stores\InventoryLowStock;
use App\Models\InventoryItem;
use App\Models\Scopes\SchoolScope;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Append-only stock ledger. Every in/out mutates inventory_items inside
 * lockForUpdate (PaymentSlipVerificationService concurrency pattern).
 */
class StockMovementService
{
    public function __construct(
        private readonly InventoryCostService $costs,
    ) {}

    /**
     * Record stock-in: increment quantity, recalculate weighted-average cost.
     *
     * @throws ValidationException
     */
    public function recordIn(
        string $inventoryItemId,
        string $quantity,
        string $unitCost,
        string $reason,
        ?string $referenceType,
        ?string $referenceId,
        string $performedBy,
        ?string $notes = null,
        ?Carbon $performedAt = null,
    ): StockMovement {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $item = InventoryItem::withoutGlobalScope(SchoolScope::class)
            ->whereKey($inventoryItemId)
            ->lockForUpdate()
            ->firstOrFail();

        $oldQty = (string) $item->current_quantity;
        $oldCost = (string) $item->unit_cost;
        $newQty = bcadd($oldQty, $quantity, 3);
        $newCost = $this->costs->weightedAverage($oldQty, $oldCost, $quantity, $unitCost);

        $item->update([
            'current_quantity' => $newQty,
            'unit_cost' => $newCost,
        ]);

        return StockMovement::create([
            'school_id' => $item->school_id,
            'inventory_item_id' => $item->id,
            'direction' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => $newQty,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'performed_by' => $performedBy,
            'performed_at' => $performedAt ?? now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Record stock-out: decrement quantity, freeze unit cost at transaction time.
     *
     * @throws ValidationException when insufficient stock (default: block negative)
     */
    public function recordOut(
        string $inventoryItemId,
        string $quantity,
        string $reason,
        ?string $referenceType,
        ?string $referenceId,
        string $performedBy,
        ?string $notes = null,
        ?Carbon $performedAt = null,
        bool $allowNegative = false,
    ): StockMovement {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $item = InventoryItem::withoutGlobalScope(SchoolScope::class)
            ->whereKey($inventoryItemId)
            ->lockForUpdate()
            ->firstOrFail();

        $oldQty = (string) $item->current_quantity;

        if (! $allowNegative && bccomp($quantity, $oldQty, 3) > 0) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock for {$item->name}. Available: {$oldQty} {$item->unit}.",
            ]);
        }

        $newQty = bcsub($oldQty, $quantity, 3);
        $unitCost = (string) $item->unit_cost;

        $item->update(['current_quantity' => $newQty]);

        $movement = StockMovement::create([
            'school_id' => $item->school_id,
            'inventory_item_id' => $item->id,
            'direction' => 'out',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => $newQty,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'performed_by' => $performedBy,
            'performed_at' => $performedAt ?? now(),
            'created_at' => now(),
        ]);

        $item->refresh();

        if ($item->is_active && $item->isLowStock()) {
            InventoryLowStock::dispatch($item, Auth::user());
        }

        return $movement;
    }
}
