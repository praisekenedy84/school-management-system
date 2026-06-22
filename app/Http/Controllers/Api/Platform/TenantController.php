<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateTenantRequest;
use App\Http\Resources\Platform\TenantResource;
use App\Http\Resources\Platform\TenantUserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\TenantProvisioningService;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    public function index()
    {
        return TenantResource::collection(Tenant::orderBy('created_at', 'desc')->get());
    }

    public function store(CreateTenantRequest $request, TenantProvisioningService $service)
    {
        $tenant = $service->create($request->validated(), Auth::guard('platform')->user());

        return TenantResource::make($tenant)->response()->setStatusCode(201);
    }

    /**
     * Users available to impersonate within a tenant. Briefly initializes
     * that tenant's schema to read its `users` table, then ends tenancy —
     * this request never had a tenant initialized for it (no `tenant_id` in
     * a Platform Admin's session), unlike every other authenticated route.
     */
    public function users(string $tenant)
    {
        $tenantModel = Tenant::find($tenant);

        abort_if($tenantModel === null, 404);

        tenancy()->initialize($tenantModel);

        try {
            $users = User::with('roles')->orderBy('name')->get();
        } finally {
            tenancy()->end();
        }

        return TenantUserResource::collection($users);
    }
}
