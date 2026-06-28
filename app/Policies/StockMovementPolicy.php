<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * PRD §2: append-only ledger — read via stores.view_movements only.
 * Mutations happen exclusively through StoreRequisitionService /
 * PurchaseRequestService, not via Eloquent update/delete.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewMovements($user);
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $this->canViewMovements($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    private function canViewMovements(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.view_movements');
    }
}
