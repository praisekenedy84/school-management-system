<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-0008: replaces subdomain-based tenant identification. The session
 * (not the host) carries which tenant the logged-in user belongs to —
 * AuthController::login puts `tenant_id` there on success. Must run before
 * `auth:sanctum` on every protected tenant route: auth resolution queries
 * the tenant's own `users` table, which only exists once tenancy is
 * initialized.
 */
class InitializeTenancyFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Requests Sanctum doesn't consider "from the frontend" (no
        // Referer/Origin matching config('sanctum.stateful')) never get the
        // 'web' middleware group applied, so there is no session store on
        // the request at all — nothing to read, nothing to initialize.
        if (! $request->hasSession()) {
            return $next($request);
        }

        $tenantId = $request->session()->get('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            abort_if($tenant === null, 419, 'Your session refers to a tenant that no longer exists. Please log in again.');

            tenancy()->initialize($tenant);
        }

        return $next($request);
    }
}
