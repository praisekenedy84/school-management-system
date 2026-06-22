<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Events\Platform\ImpersonationEnded;
use App\Events\Platform\ImpersonationStarted;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Full read+write impersonation (confirmed with the user — not a read-only
 * "view as"): a Platform Admin logs in as the target user on the `web`
 * guard, in the same session, without logging themselves out of the
 * `platform` guard. Every write made while impersonating is still
 * attributable, since EnsurePlatformAdmin checks the `platform` guard
 * specifically and the audit trail captures both identities.
 *
 * Mirrors AuthController::login's existing pattern: doesn't call
 * tenancy()->end() after start() succeeds, because every subsequent request
 * re-initializes tenancy from the session via InitializeTenancyFromSession,
 * same as a normal tenant login.
 */
class ImpersonationService
{
    public function start(string $tenantId, string $userId, PlatformAdmin $admin): User
    {
        $tenant = Tenant::find($tenantId);

        abort_if($tenant === null, 404, 'Tenant not found.');

        tenancy()->initialize($tenant);

        // findOrFail (or anything else here) throwing must not leave
        // tenancy initialized to this tenant for the rest of the request —
        // success deliberately leaves it initialized (see docblock above),
        // but failure should not.
        try {
            $target = User::findOrFail($userId);
        } catch (\Throwable $e) {
            tenancy()->end();

            throw $e;
        }

        Auth::guard('web')->login($target);

        Session::put('tenant_id', $tenant->getTenantKey());
        Session::put('impersonation', [
            'platform_admin_id' => $admin->id,
            'platform_admin_name' => $admin->name,
            'started_at' => now()->toISOString(),
        ]);

        ImpersonationStarted::dispatch($target, $admin);

        return $target;
    }

    public function stop(): PlatformAdmin
    {
        $impersonation = Session::get('impersonation');

        abort_if($impersonation === null, 409, 'Not currently impersonating.');

        $tenant = Tenant::find(Session::get('tenant_id'));

        if ($tenant !== null) {
            tenancy()->initialize($tenant);

            ImpersonationEnded::dispatch(Auth::guard('web')->user(), $impersonation);

            tenancy()->end();
        }

        Auth::guard('web')->logout();
        Session::forget(['tenant_id', 'impersonation']);

        return Auth::guard('platform')->user();
    }
}
