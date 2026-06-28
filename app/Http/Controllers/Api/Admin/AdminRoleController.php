<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Events\Tenant\RolePermissionsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateRoleRequest;
use App\Http\Requests\Admin\SyncRolePermissionsRequest;
use App\Http\Requests\Admin\SyncUserPermissionsRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\PermissionCatalogResource;
use App\Http\Resources\RoleResource;
use App\Models\User;
use App\Services\Admin\RoleManagementService;
use App\Support\RbacGuard;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    public function index(RoleManagementService $service)
    {
        $this->authorize('viewAny', Role::class);

        return RoleResource::collection($service->listRoles());
    }

    public function permissions(RoleManagementService $service)
    {
        $this->authorize('viewAny', Role::class);

        return PermissionCatalogResource::collection($service->listPermissionCatalog());
    }

    public function store(CreateRoleRequest $request, RoleManagementService $service)
    {
        $role = $service->createRole(
            $request->user(),
            $request->validated('name'),
            $request->validated('permissions'),
        );

        RolePermissionsChanged::dispatch($role->name, $request->validated('permissions'), Auth::user());

        return RoleResource::make([
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
            'is_protected' => false,
        ])->response()->setStatusCode(201);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, string $role, RoleManagementService $service)
    {
        $roleModel = Role::findByName($role, 'web');
        $permissions = $request->validated('permissions');

        $service->syncRolePermissions($request->user(), $roleModel, $permissions);

        RolePermissionsChanged::dispatch($roleModel->name, $permissions, Auth::user());

        return RoleResource::make([
            'name' => $roleModel->name,
            'permissions' => $permissions,
            'is_protected' => in_array($roleModel->name, RbacGuard::PROTECTED_ROLES, true),
        ]);
    }

    public function destroy(string $role, RoleManagementService $service)
    {
        $roleModel = Role::findByName($role, 'web');
        $this->authorize('delete', $roleModel);

        $service->deleteRole(Auth::user(), $roleModel);

        return response()->noContent();
    }

    public function syncUserPermissions(SyncUserPermissionsRequest $request, User $user, RoleManagementService $service)
    {
        $permissions = $request->validated('permissions');
        $updated = $service->syncDirectPermissions($request->user(), $user, $permissions);

        return AdminUserResource::make($updated);
    }
}
