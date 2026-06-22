<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

/**
 * `tenant_admin`/`school_admin` can do everything; `academic_director`
 * can also mutate (RoleAndPermissionSeeder grants them
 * `academic.manage_subjects`, RULES.md §5). Every other role can view but
 * not mutate.
 */
class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director']);
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director']);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director']);
    }
}
