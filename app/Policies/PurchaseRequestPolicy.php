<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

/**
 * PRD §2: storekeeper creates/submits; finance approves/amends/fulfills.
 */
class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.create_purchase_requests');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($purchaseRequest->status !== 'draft') {
            return false;
        }

        return $this->create($user);
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->update($user, $purchaseRequest);
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $purchaseRequest->status === 'draft' && $this->create($user);
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.approve_purchases');
    }

    public function amend(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->approve($user, $purchaseRequest);
    }

    public function reject(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->approve($user, $purchaseRequest);
    }

    public function fulfill(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.fulfill_purchases');
    }

    private function canView(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.create_purchase_requests')
            || $user->hasPermissionTo('stores.approve_purchases')
            || $user->hasPermissionTo('stores.fulfill_purchases');
    }
}
