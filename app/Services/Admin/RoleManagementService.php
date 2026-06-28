<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use App\Support\RbacGuard;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManagementService
{
    /**
     * @return Collection<int, array{name: string, permissions: list<string>, is_protected: bool}>
     */
    public function listRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'is_protected' => in_array($role->name, RbacGuard::PROTECTED_ROLES, true),
            ]);
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    public function listPermissionCatalog(): array
    {
        $catalog = config('permission-catalog', []);

        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name) => [
                'name' => $name,
                'description' => $catalog[$name] ?? $name,
            ])
            ->values()
            ->all();
    }

    public function createRole(User $actor, string $name, array $permissions): Role
    {
        abort_if(in_array($name, RbacGuard::PROTECTED_ROLES, true), 422, 'That role name is reserved.');
        abort_if(Role::where('name', $name)->where('guard_name', 'web')->exists(), 422, 'Role already exists.');

        RbacGuard::assertActorMayAssignPermissions($actor, $permissions);

        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }

    public function syncRolePermissions(User $actor, Role $role, array $permissions): Role
    {
        abort_unless(RbacGuard::actorMayManageRole($actor, $role->name), 403);

        RbacGuard::assertActorMayAssignPermissions($actor, $permissions);

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }

    public function deleteRole(User $actor, Role $role): void
    {
        abort_if(in_array($role->name, RbacGuard::PROTECTED_ROLES, true), 422, 'Protected roles cannot be deleted.');
        abort_unless(RbacGuard::actorMayManageRole($actor, $role->name), 403);

        if ($role->users()->exists()) {
            abort(422, 'Remove this role from all users before deleting it.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function syncDirectPermissions(User $actor, User $target, array $permissions): User
    {
        RbacGuard::assertActorMayAssignPermissions($actor, $permissions);

        $target->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $target->load(['roles', 'permissions']);
    }
}
