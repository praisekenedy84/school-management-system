<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HostelAllocation;
use App\Models\User;

/**
 * Placeholder shape, same as other Phase 0-3 policies. `viewAny` only
 * gates endpoint reachability — per-row scoping for parents happens in
 * `HostelAllocationController::index` (mirrors `StudentController`).
 * `view` is scoped: staff see any allocation, a `parent` only their own
 * ward's, matching `PaymentSlipPolicy`/`HostelLeaveRequestPolicy`.
 * create/update/delete (allocate/end) restricted to
 * hostel_manager/school_admin/tenant_admin per RULES.md §5
 * hostel.manage_allocations.
 */
class HostelAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HostelAllocation $hostelAllocation): bool
    {
        if ($user->hasRole('parent')) {
            return $user->wards()->whereKey($hostelAllocation->student_id)->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }

    public function update(User $user, HostelAllocation $hostelAllocation): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }

    public function delete(User $user, HostelAllocation $hostelAllocation): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }
}
