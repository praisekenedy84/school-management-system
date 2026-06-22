<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every `/api/v1/platform/*` route. Deliberately checks the
 * `platform` guard specifically, not generic `auth:sanctum` — `sanctum.guard`
 * also includes `web`, so a plain tenant user (even a tenant_admin) would
 * otherwise pass. This check stays correct even mid-impersonation, when the
 * `web` guard also has a user logged in alongside the still-authenticated
 * `platform` guard in the same session — see App\Services\Platform\
 * ImpersonationService.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('platform')->check(), 403, 'Platform Admin access only.');

        return $next($request);
    }
}
