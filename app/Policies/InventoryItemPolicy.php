<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

/**
 * PRD §2: catalog CRUD gated by stores.manage_catalog; read-only stock
 * visibility via stores.view_stock.
 */
class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewStock($user);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->canViewStock($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog($user);
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->canManageCatalog($user);
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->canManageCatalog($user);
    }

    private function canManageCatalog(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.manage_catalog');
    }

    private function canViewStock(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.manage_catalog')
            || $user->hasPermissionTo('stores.view_stock')
            || $user->hasPermissionTo('stores.view_movements');
    }
}
