<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * RBAC guardrails for tenant self-service role/permission customization.
 */
final class RbacGuard
{
    /** @var list<string> */
    public const PROTECTED_ROLES = ['super_admin', 'tenant_admin'];

    /** @var list<string> */
    public const SCHOOL_ADMIN_BLOCKED_ROLES = ['super_admin', 'tenant_admin'];

    /** Permissions a school_admin may never grant to any role or user. */
    public static function tenantLevelPermissions(): array
    {
        return array_values(array_filter(
            array_keys(config('permission-catalog', [])),
            fn (string $name) => str_starts_with($name, 'tenant.')
        ));
    }

    public static function actorMayManageRole(User $actor, string $roleName): bool
    {
        if (in_array($roleName, self::PROTECTED_ROLES, true)) {
            return $actor->hasRole(['tenant_admin', 'super_admin']);
        }

        return $actor->hasPermissionTo('rbac.manage_roles');
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string> permissions the actor is allowed to assign
     */
    public static function filterAssignablePermissions(User $actor, array $permissions): array
    {
        if ($actor->hasRole(['tenant_admin', 'super_admin'])) {
            return $permissions;
        }

        $blocked = self::tenantLevelPermissions();

        return array_values(array_filter(
            $permissions,
            fn (string $name) => ! in_array($name, $blocked, true)
        ));
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function assertActorMayAssignPermissions(User $actor, array $permissions): void
    {
        $filtered = self::filterAssignablePermissions($actor, $permissions);

        if (count($filtered) !== count($permissions)) {
            abort(403, 'You cannot assign tenant-level permissions.');
        }
    }
}
