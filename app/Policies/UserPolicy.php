<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

class UserPolicy
{
    /**
     * @param  'teacher'|'parent'|null  $role
     */
    public function lookup(User $user, ?string $role = null): bool
    {
        return match ($role) {
            'teacher' => $user->can('create', TeacherAssignment::class),
            'parent' => $user->hasRole(['tenant_admin', 'school_admin']),
            default => false,
        };
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $user->hasPermissionTo('users.manage_roles');
    }

    public function updateRoles(User $user, User $target): bool
    {
        if (! $user->hasPermissionTo('users.manage_roles')) {
            return false;
        }

        if ($user->hasRole('school_admin') && ! $user->hasRole(['tenant_admin', 'super_admin'])) {
            return $target->school_id !== null
                && $target->school_id === $user->school_id
                && ! $target->hasRole(['tenant_admin', 'super_admin']);
        }

        return true;
    }

    public function updatePermissions(User $user, User $target): bool
    {
        if (! $user->hasPermissionTo('rbac.manage_roles')) {
            return false;
        }

        return $this->updateRoles($user, $target);
    }
}
