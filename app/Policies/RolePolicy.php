<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\RbacGuard;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('rbac.manage_roles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('rbac.manage_roles');
    }

    public function update(User $user, Role $role): bool
    {
        return RbacGuard::actorMayManageRole($user, $role->name);
    }

    public function delete(User $user, Role $role): bool
    {
        return RbacGuard::actorMayManageRole($user, $role->name)
            && ! in_array($role->name, RbacGuard::PROTECTED_ROLES, true);
    }
}
