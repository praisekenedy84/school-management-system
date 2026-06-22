<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * Phase 0 scaffolding policy. `school_admin` and `tenant_admin` can do
 * everything; staff can view but not mutate. `view` is additionally scoped
 * for `parent` — a guardian may only view their own wards
 * (`students.view_own_children`, RULES.md §5) — to prevent cross-family
 * data leakage. `viewAny` only gates whether the endpoint is reachable at
 * all; per-row scoping for parents happens in `StudentController::index`.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole('parent')) {
            return $user->wards()->whereKey($student->id)->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }
}
