<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hostel;
use App\Models\User;

/**
 * RULES.md §5: hostel_manager holds hostel.manage_rooms. Same placeholder
 * shape as SubjectPolicy/AssessmentPolicy — viewAny/view open, mutations
 * restricted.
 */
class HostelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Hostel $hostel): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }

    public function update(User $user, Hostel $hostel): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }

    public function delete(User $user, Hostel $hostel): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }
}
