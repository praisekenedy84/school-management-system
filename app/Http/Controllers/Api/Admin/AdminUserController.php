<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Events\Tenant\UserRolesChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAnyAdmin', User::class);

        $query = User::query()->with('roles')->orderBy('name');

        if ($request->user()->school_id !== null) {
            $query->where('school_id', $request->user()->school_id);
        }

        return AdminUserResource::collection($query->get());
    }

    public function roles(Request $request)
    {
        $this->authorize('viewAnyAdmin', User::class);

        $all = Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name');

        if ($request->user()->hasRole(['tenant_admin', 'super_admin'])) {
            $names = $all->reject(fn (string $name) => $name === 'super_admin')->values();
        } else {
            $names = $all->reject(fn (string $name) => in_array($name, ['tenant_admin', 'super_admin'], true))->values();
        }

        return response()->json(['data' => $names]);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user)
    {
        $roles = $request->validated('roles');
        $user->syncRoles($roles);

        UserRolesChanged::dispatch($user->fresh(), $roles, Auth::user());

        return AdminUserResource::make($user->fresh()->load('roles'));
    }
}
