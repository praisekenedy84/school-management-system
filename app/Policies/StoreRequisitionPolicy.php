<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreRequisition;
use App\Models\User;

/**
 * PRD §2 / §10: kitchen_staff sees own requisitions; storekeeper sees all.
 */
class StoreRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewRequisitions($user);
    }

    public function view(User $user, StoreRequisition $storeRequisition): bool
    {
        if (! $this->canViewRequisitions($user)) {
            return false;
        }

        return $this->canViewAllRequisitions($user)
            || $storeRequisition->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.create_requisitions');
    }

    public function update(User $user, StoreRequisition $storeRequisition): bool
    {
        if ($storeRequisition->status !== 'draft') {
            return false;
        }

        if ($user->hasRole(['tenant_admin', 'school_admin'])) {
            return true;
        }

        if ($user->hasPermissionTo('stores.create_requisitions')) {
            return $storeRequisition->requested_by === $user->id
                || $user->hasPermissionTo('stores.approve_requisitions');
        }

        return false;
    }

    public function delete(User $user, StoreRequisition $storeRequisition): bool
    {
        return $this->update($user, $storeRequisition);
    }

    public function submit(User $user, StoreRequisition $storeRequisition): bool
    {
        return $storeRequisition->status === 'draft'
            && $storeRequisition->requested_by === $user->id
            && ($user->hasRole(['tenant_admin', 'school_admin'])
                || $user->hasPermissionTo('stores.create_requisitions'));
    }

    public function approve(User $user, StoreRequisition $storeRequisition): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.approve_requisitions');
    }

    public function reject(User $user, StoreRequisition $storeRequisition): bool
    {
        return $this->approve($user, $storeRequisition);
    }

    public function issue(User $user, StoreRequisition $storeRequisition): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.issue_requisitions');
    }

    public function closeLine(User $user, StoreRequisition $storeRequisition): bool
    {
        return $this->issue($user, $storeRequisition);
    }

    public function addToPurchase(User $user, StoreRequisition $storeRequisition): bool
    {
        if (! $storeRequisition->canAddToPurchase()) {
            return false;
        }

        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.create_purchase_requests');
    }

    public function cancel(User $user, StoreRequisition $storeRequisition): bool
    {
        if (! $storeRequisition->isCancellable()) {
            return false;
        }

        if ($user->hasRole(['tenant_admin', 'school_admin'])) {
            return true;
        }

        if ($storeRequisition->isDraft()) {
            return $storeRequisition->requested_by === $user->id
                && $user->hasPermissionTo('stores.create_requisitions');
        }

        if ($storeRequisition->status === 'submitted') {
            return $storeRequisition->requested_by === $user->id
                || $user->hasPermissionTo('stores.approve_requisitions');
        }

        return false;
    }

    private function canViewRequisitions(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.view_requisitions');
    }

    private function canViewAllRequisitions(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin'])
            || $user->hasPermissionTo('stores.approve_requisitions')
            || $user->hasPermissionTo('stores.issue_requisitions');
    }
}
