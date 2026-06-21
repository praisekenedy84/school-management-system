<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Enrolment;
use App\Models\User;

/**
 * Phase 0/1 scaffolding policy. `school_admin` and `tenant_admin` can do
 * everything; every other role can view but not mutate. This is a
 * placeholder — the full scoped-permission RBAC matrix (RULES.md §5)
 * replaces these checks later.
 */
class EnrolmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Enrolment $enrolment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function update(User $user, Enrolment $enrolment): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, Enrolment $enrolment): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }
}
