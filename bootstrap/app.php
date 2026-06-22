<?php

use App\Http\Middleware\InitializeTenancyFromSession;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // No central routes/api.php: every API route is tenant-scoped and
        // lives in routes/tenant.php instead (see its docblock). The 'api'
        // middleware group itself (used there) still comes from
        // statefulApi() below regardless of this key.
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        // ADR-0008 follow-up #2. `DatabaseSessionHandler::write()` (the
        // 'database' session driver) unconditionally tries to resolve
        // Auth::guard()->user() to stamp `sessions.user_id` on EVERY
        // session save, on EVERY request — not just authenticated ones,
        // and not just /api/v1/* ones. The two routes that use the plain
        // 'web' group directly (routes/tenant.php: /sanctum/csrf-cookie
        // and the SPA catch-all `/{any?}`) never initialized tenancy at
        // all, so that resolution hit the central schema's `users` table
        // (which doesn't exist there) on literally any page load/refresh.
        // InitializeTenancyBeforeAuthenticatingSession (config/sanctum.php)
        // only covers the /api/v1/* path, which goes through Sanctum's own
        // separately-hardcoded nested sub-pipeline — this covers the
        // other one. A no-op when the session has no tenant_id yet.
        $middleware->appendToGroup('web', InitializeTenancyFromSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API-first (CLAUDE.md): every /api error response should be
        // friendly JSON, never a raw exception message or stack trace —
        // even if APP_DEBUG is ever left on by accident. ValidationException
        // (422) is deliberately untouched below: its {message, errors} shape
        // is already correct and every frontend page reads `errors`.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // A missing model (findOrFail) and a genuinely unknown route both
        // arrive here as NotFoundHttpException — Laravel's own
        // prepareException() converts ModelNotFoundException to this before
        // any render callback runs. The original message leaks the Eloquent
        // class name and the literal id ("No query results for model
        // [App\Models\Student] <uuid>"), which is internal detail no end
        // user (or parent, or teacher) should ever see.
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The requested record could not be found.',
                ], 404);
            }
        });

        // A denied Policy/Gate check without an explicit ->status() is
        // converted to this by prepareException().
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Your session has expired. Please log in again.',
                ], 401);
            }
        });

        // A CSRF token mismatch is converted to a plain HttpException(419)
        // by prepareException() — catch that status specifically; every
        // other HttpException-family exception (e.g. throttle's 429)
        // already carries a fine end-user message and is left alone below.
        $exceptions->render(function (HttpException $e, $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 419) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                ], 419);
            }
        });

        // Safety net: anything that reaches here is an exception nobody
        // planned for (a bug, a third-party failure, a guard like
        // ResultRecord's append-only check being tripped). Laravel already
        // logs it via report() before this runs — never echo its message,
        // class, file, or trace back to the client, regardless of
        // APP_DEBUG. Deliberate HTTP exceptions (404/403/401/419/429/abort)
        // and ValidationException are handled above or by Laravel's
        // defaults and must pass through untouched.
        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface || $e instanceof ValidationException) {
                return null;
            }

            return response()->json([
                'message' => 'Something went wrong on our end. Please try again, and contact support if the problem continues.',
            ], 500);
        });
    })->create();
