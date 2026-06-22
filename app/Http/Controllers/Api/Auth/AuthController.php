<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\PlatformAdminResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\TenantUserDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ADR-0008: single domain, credential-based tenant routing. `login` doesn't
 * know the tenant up front — it looks the email up in the central
 * tenant_user_directory, initializes that tenant, then authenticates
 * against its `users` table. An email absent from the directory is tried
 * against the central `platform` guard instead (Platform Admin — the one
 * login type not scoped to a tenant).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $directoryEntry = TenantUserDirectory::where('email', $request->string('email'))->first();

        // An email present in the tenant directory is never tried against
        // the platform guard, even on a wrong password — without this, a
        // tenant user's email + a Platform Admin's password would
        // authenticate them AS that Platform Admin (the directory and
        // platform_admins tables are independently unique, nothing stops
        // the same email existing in both).
        if ($directoryEntry) {
            $tenant = Tenant::find($directoryEntry->tenant_id);

            if ($tenant) {
                tenancy()->initialize($tenant);

                $credentials = $request->only('email', 'password') + ['is_active' => true];

                if (Auth::attempt($credentials, $request->boolean('remember'))) {
                    $request->session()->regenerate();
                    $request->session()->put('tenant_id', $tenant->getTenantKey());

                    return new UserResource($request->user());
                }

                tenancy()->end();
            }

            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], 422);
        }

        $platformCredentials = $request->only('email', 'password') + ['is_active' => true];

        if (Auth::guard('platform')->attempt($platformCredentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return new PlatformAdminResource(Auth::guard('platform')->user());
        }

        return response()->json([
            'message' => 'These credentials do not match our records.',
        ], 422);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        Auth::guard('platform')->logout();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request)
    {
        // A Platform Admin impersonating a tenant user has BOTH the 'web'
        // and 'platform' guards authenticated in the same session (see
        // App\Services\Platform\ImpersonationService) — check this branch
        // first so /me reflects the impersonated identity, not the admin's.
        if (Auth::guard('web')->check() && $request->hasSession() && $request->session()->has('impersonation')) {
            return (new UserResource(Auth::guard('web')->user()))
                ->additional(['impersonation' => $request->session()->get('impersonation')]);
        }

        // Platform Admin is never tenant-scoped, so check that guard next
        // — `Auth::guard(...)->check()` resolves from the already-bound
        // guard instance and doesn't require a session store on $request
        // (relevant for actingAs()-driven tests, which set the guard's
        // user directly without going through the real cookie/session flow).
        if (Auth::guard('platform')->check()) {
            return new PlatformAdminResource(Auth::guard('platform')->user());
        }

        return new UserResource($request->user());
    }
}
