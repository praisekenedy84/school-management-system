<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-0008 follow-up. `EnsureFrontendRequestsAreStateful` (added by
 * statefulApi()) builds its OWN nested sub-pipeline of the 'web' group —
 * EncryptCookies, StartSession, VerifyCsrfToken, AuthenticateSession — and
 * runs the ENTIRE thing to completion BEFORE calling onward to any
 * route-level middleware, including InitializeTenancyFromSession. That
 * ordering is hardcoded inside Sanctum's middleware class itself, so it
 * can NOT be fixed via Laravel's middleware-priority list (which only
 * reorders middleware within one flat array — AuthenticateSession never
 * appears in that array; it's buried inside Sanctum's own method body).
 *
 * AuthenticateSession resolves `$request->user()` (the 'web' guard) to
 * check the session against the user's current password hash — for a
 * session that already carries a tenant user's id, that query needs
 * tenancy initialized first, or it hits the central schema instead of the
 * tenant's (no `users` table there: SQLSTATE[42P01]).
 *
 * Fix: register THIS class as config('sanctum.middleware.authenticate_session')
 * instead of Sanctum's own class directly. It initializes tenancy from the
 * session (mirroring InitializeTenancyFromSession's logic exactly — by the
 * time this runs, StartSession has already run inside the same
 * sub-pipeline, so the session is readable) and only then delegates to the
 * real AuthenticateSession, preserving its stale-session security check.
 */
class InitializeTenancyBeforeAuthenticatingSession
{
    public function __construct(private InitializeTenancyFromSession $initializeTenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->initializeTenancy->handle(
            $request,
            fn (Request $request) => app(AuthenticateSession::class)->handle($request, $next)
        );
    }
}
