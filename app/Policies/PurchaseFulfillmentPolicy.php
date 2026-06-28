<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseFulfillment;
use App\Models\User;

/**
 * PRD §3.5: fulfillments are append-only; finance creates, storekeeper reads.
 */
class PurchaseFulfillmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, PurchaseFulfillment $purchaseFulfillment): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.fulfill_purchases');
    }

    public function update(User $user, PurchaseFulfillment $purchaseFulfillment): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseFulfillment $purchaseFulfillment): bool
    {
        return false;
    }

    private function canView(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.create_purchase_requests')
            || $user->hasPermissionTo('stores.approve_purchases')
            || $user->hasPermissionTo('stores.fulfill_purchases');
    }
}
