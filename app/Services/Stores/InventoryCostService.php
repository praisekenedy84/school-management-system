<?php

declare(strict_types=1);

namespace App\Services\Stores;

/**
 * Weighted-average inventory costing via BCMath (never float).
 */
class InventoryCostService
{
    /**
     * Compute new weighted-average unit cost after a stock-in.
     *
     * new_cost = (old_qty × old_cost + in_qty × in_cost) / (old_qty + in_qty)
     * Returns old_cost when old_qty + in_qty is zero.
     */
    public function weightedAverage(string $oldQty, string $oldCost, string $inQty, string $inCost): string
    {
        $totalQty = bcadd($oldQty, $inQty, 3);

        if (bccomp($totalQty, '0', 3) <= 0) {
            return $inCost;
        }

        if (bccomp($oldQty, '0', 3) <= 0) {
            return $inCost;
        }

        $numerator = bcadd(
            bcmul($oldQty, $oldCost, 4),
            bcmul($inQty, $inCost, 4),
            4
        );

        return bcdiv($numerator, $totalQty, 2);
    }
}
