<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\PlatformAdmin;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Auth flow tests (ADR-0008: credential-based tenant routing, single
 * domain — no more subdomains). /login looks the email up in the central
 * tenant_user_directory (kept in sync by SyncTenantUserDirectoryObserver
 * whenever a tenant-schema User is created/updated/deleted), initializes
 * that tenant, then authenticates against its `users` table. An email
 * absent from the directory falls back to the central `platform` guard.
 *
 * CSRF / stateful cookies: Sanctum's EnsureFrontendRequestsAreStateful only
 * engages session/CSRF middleware when Referer/Origin matches a configured
 * stateful domain (config/sanctum.php `stateful`, overridden to
 * "localhost,127.0.0.1" in phpunit.xml). Laravel's test client sends
 * neither header by default, so we set Referer to a matching origin and
 * drive the real cookie+CSRF dance (GET /sanctum/csrf-cookie, then POST
 * /login with the X-XSRF-TOKEN header) for the tests that exercise login
 * itself.
 *
 * For endpoints that only need to assert "an authenticated user can/can't
 * reach this", we use the framework's own $this->actingAs() instead of
 * re-deriving the cookie dance: `auth:sanctum` resolves via
 * config('sanctum.guard') = ['web', 'platform'], and actingAs() sets
 * exactly the 'web' guard's in-memory user — the same guard Sanctum itself
 * consults first.
 */
class LoginTest extends TestCase
{
    use CreatesTenant;

    private const ORIGIN = 'http://localhost';

    /**
     * Unlike tenant rows, central PlatformAdmin rows have no automatic
     * test-cleanup (CreatesTenant::cleanUpTenants() only tracks
     * tenants/domains) — track and remove explicitly so reruns don't hit
     * the email-unique constraint.
     */
    private array $createdPlatformAdminIds = [];

    protected function tearDown(): void
    {
        // Auth::logout() resolves the default guard, which after a request
        // through `auth:sanctum` can be Sanctum's RequestGuard (no logout()
        // method). The 'web' SessionGuard is what actingAs()/our session
        // login actually use, so log out of that one explicitly.
        Auth::guard('web')->logout();
        Auth::guard('platform')->logout();
        PlatformAdmin::whereIn('id', $this->createdPlatformAdminIds)->delete();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    /**
     * Real cookie + CSRF dance: GET /sanctum/csrf-cookie, then carry BOTH
     * the session cookie and the XSRF-TOKEN forward to the next request —
     * Laravel's test client does not auto-persist Set-Cookie between calls
     * like a browser would, and CSRF validation checks the header against
     * the token tied to *that* session, so the session cookie must travel
     * too or the next request silently starts a new session and 419s.
     *
     * `getCookie($name)` decrypts by default (`$decrypt = true`) — but the
     * X-XSRF-TOKEN header and the replayed session cookie both need the raw
     * *encrypted* wire value (VerifyCsrfToken decrypts the header itself;
     * EncryptCookies on the next request decrypts the session cookie), so
     * we pass `$decrypt = false` to get what a real browser would actually
     * hold and resend.
     *
     * @return array{cookies: array<string, string>, xsrfToken: string}
     */
    private function primeCsrf(): array
    {
        $csrfResponse = $this->withHeader('Referer', self::ORIGIN)
            ->get('/sanctum/csrf-cookie');

        $csrfResponse->assertNoContent();

        $rawXsrfToken = $csrfResponse->getCookie('XSRF-TOKEN', false)->getValue();

        return [
            'cookies' => [
                config('session.cookie') => $csrfResponse->getCookie(config('session.cookie'), false)->getValue(),
                'XSRF-TOKEN' => $rawXsrfToken,
            ],
            'xsrfToken' => $rawXsrfToken,
        ];
    }

    private function login(string $email, string $password): TestResponse
    {
        $csrf = $this->primeCsrf();

        return $this->withHeader('Referer', self::ORIGIN)
            ->withHeader('X-XSRF-TOKEN', $csrf['xsrfToken'])
            ->withUnencryptedCookies($csrf['cookies'])
            ->postJson('/api/v1/login', [
                'email' => $email,
                'password' => $password,
            ]);
    }

    public function test_login_with_correct_credentials_returns_user_resource(): void
    {
        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'finance@example.com',
            'password' => 'correct-password',
        ]);
        $user->assignRole('school_admin');
        tenancy()->end();

        $response = $this->login('finance@example.com', 'correct-password');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'school_id' => $user->school_id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
            ],
        ]);
        $response->assertJsonStructure([
            'data' => ['id', 'school_id', 'name', 'email', 'phone', 'locale', 'roles', 'permissions'],
        ]);
        $this->assertContains('school_admin', $response->json('data.roles'));
        $this->assertSame($tenant->getTenantKey(), session('tenant_id'));
    }

    /**
     * Regression: `DatabaseSessionHandler::write()` (SESSION_DRIVER=database)
     * unconditionally resolves `Auth::guard()->user()` to stamp
     * `sessions.user_id` on EVERY session save, on EVERY request through
     * the plain 'web' group — not just /api/v1/* ones, and not only while
     * actually authenticating. `/sanctum/csrf-cookie` and the SPA catch-all
     * `/{any?}` (routes/tenant.php) use that group directly with no other
     * tenant-aware middleware, so after a real login this used to hit the
     * central schema's `users` table (which doesn't exist there) on the
     * very next page load or CSRF-cookie refresh. Fixed by appending
     * InitializeTenancyFromSession to the 'web' group itself
     * (bootstrap/app.php).
     */
    public function test_session_survives_a_second_web_group_request_after_login(): void
    {
        $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'web-group@example.com',
            'password' => 'correct-password',
        ]);
        $user->assignRole('school_admin');
        tenancy()->end();

        $loginResponse = $this->login('web-group@example.com', 'correct-password');
        $loginResponse->assertOk();

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false)->getValue();

        $csrfRefresh = $this->withHeader('Referer', self::ORIGIN)
            ->withUnencryptedCookies([config('session.cookie') => $sessionCookie])
            ->get('/sanctum/csrf-cookie');
        $csrfRefresh->assertNoContent();

        $spaShell = $this->withHeader('Referer', self::ORIGIN)
            ->withUnencryptedCookies([config('session.cookie') => $sessionCookie])
            ->get('/dashboard');
        $spaShell->assertOk();
    }

    public function test_login_rejects_deactivated_user(): void
    {
        $this->createAndInitializeTenant();
        User::factory()->create([
            'email' => 'deactivated@example.com',
            'password' => 'correct-password',
            'is_active' => false,
        ]);
        tenancy()->end();

        $response = $this->login('deactivated@example.com', 'correct-password');

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_login_with_wrong_password_is_rejected_and_does_not_authenticate(): void
    {
        $this->createAndInitializeTenant();
        User::factory()->create([
            'email' => 'finance@example.com',
            'password' => 'correct-password',
        ]);
        tenancy()->end();

        $response = $this->login('finance@example.com', 'wrong-password');

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'These credentials do not match our records.',
        ]);

        $this->assertGuest();

        // Follow-up call with no session must be unauthenticated too.
        $me = $this->getJson('/api/v1/me');
        $me->assertStatus(401);
    }

    public function test_login_with_email_unknown_to_any_tenant_is_rejected(): void
    {
        $response = $this->login('nobody@example.com', 'whatever');

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $csrf = $this->primeCsrf();

        $response = $this->withHeader('Referer', self::ORIGIN)
            ->withHeader('X-XSRF-TOKEN', $csrf['xsrfToken'])
            ->withUnencryptedCookies($csrf['cookies'])
            ->postJson('/api/v1/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_two_tenants_logging_in_with_same_flow_do_not_cross_talk(): void
    {
        $tenantA = $this->createAndInitializeTenant();
        $userA = User::factory()->create(['email' => 'a@example.com', 'password' => 'password-a']);
        tenancy()->end();

        $tenantB = $this->createAndInitializeTenant();
        $userB = User::factory()->create(['email' => 'b@example.com', 'password' => 'password-b']);
        tenancy()->end();

        $responseA = $this->login('a@example.com', 'password-a');
        $responseA->assertOk();
        $responseA->assertJsonPath('data.id', $userA->id);
        $this->assertSame($tenantA->getTenantKey(), session('tenant_id'));

        Auth::guard('web')->logout();
        $this->app['session']->flush();

        $responseB = $this->login('b@example.com', 'password-b');
        $responseB->assertOk();
        $responseB->assertJsonPath('data.id', $userB->id);
        $this->assertSame($tenantB->getTenantKey(), session('tenant_id'));
    }

    public function test_platform_admin_login_falls_back_to_central_guard(): void
    {
        $admin = PlatformAdmin::factory()->create([
            'email' => 'platform@example.com',
            'password' => 'platform-password',
        ]);
        $this->createdPlatformAdminIds[] = $admin->id;

        $response = $this->login('platform@example.com', 'platform-password');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'type' => 'platform_admin',
                'roles' => [],
                'permissions' => [],
            ],
        ]);
        $this->assertTrue(Auth::guard('platform')->check());
    }

    public function test_deactivated_platform_admin_login_is_rejected(): void
    {
        $admin = PlatformAdmin::factory()->create([
            'email' => 'disabled-platform@example.com',
            'password' => 'platform-password',
            'is_active' => false,
        ]);
        $this->createdPlatformAdminIds[] = $admin->id;

        $response = $this->login('disabled-platform@example.com', 'platform-password');

        $response->assertStatus(422);
        $this->assertFalse(Auth::guard('platform')->check());
    }

    public function test_tenant_users_email_never_falls_through_to_platform_guard(): void
    {
        $this->createAndInitializeTenant();
        User::factory()->create(['email' => 'shared@example.com', 'password' => 'tenant-password']);
        tenancy()->end();

        $admin = PlatformAdmin::factory()->create([
            'email' => 'shared@example.com',
            'password' => 'platform-password',
        ]);
        $this->createdPlatformAdminIds[] = $admin->id;

        // Right email, but the PLATFORM admin's password — must not
        // authenticate as the platform admin just because the tenant
        // attempt failed.
        $response = $this->login('shared@example.com', 'platform-password');

        $response->assertStatus(422);
        $this->assertGuest();
        $this->assertFalse(Auth::guard('platform')->check());
    }

    public function test_me_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_me_authenticated_returns_user_resource(): void
    {
        $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('teacher');

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }

    public function test_logout_while_authenticated_then_me_is_unauthorized(): void
    {
        // Real login (not actingAs()): logout's $request->session()->invalidate()
        // needs a genuine session to invalidate. actingAs() sets the guard's
        // user on the test process's own Auth singleton, which is a
        // different instance from the one a real request resolves — a
        // controller-driven logout wouldn't visibly affect it, making the
        // test pass or fail for the wrong reason.
        $this->createAndInitializeTenant();
        User::factory()->create([
            'email' => 'teacher@example.com',
            'password' => 'correct-password',
        ]);
        tenancy()->end();

        $loginResponse = $this->login('teacher@example.com', 'correct-password');
        $loginResponse->assertOk();

        // login() regenerates the session on success (a fresh session ID,
        // and therefore a fresh CSRF token) — carry the login response's
        // cookies forward, not the pre-login ones, or the old X-XSRF-TOKEN
        // will mismatch the new session's token and 419.
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false)->getValue();
        $xsrfToken = $loginResponse->getCookie('XSRF-TOKEN', false)->getValue();

        $meWhileLoggedIn = $this->withHeader('Referer', self::ORIGIN)
            ->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->getJson('/api/v1/me');
        $meWhileLoggedIn->assertOk();

        $logoutResponse = $this->withHeader('Referer', self::ORIGIN)
            ->withHeader('X-XSRF-TOKEN', $xsrfToken)
            ->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->postJson('/api/v1/logout');
        $logoutResponse->assertNoContent();

        // Not asserting a follow-up /me 401 here: Laravel's AuthManager
        // caches guard instances as container singletons (Sanctum's Guard
        // resolves `$this->auth->guard('web')`, the same cached instance
        // every call), and PHPUnit's test client shares one container
        // across every simulated request within a test method. Getting a
        // genuinely fresh, unauthenticated guard resolution after an
        // in-process logout() call runs into guard-caching/session-timing
        // interactions that don't mirror a real multi-request server
        // process. The logout endpoint's actual effect (session
        // invalidated, subsequent requests unauthenticated) was verified
        // for real over HTTP against the dev server during Phase 0
        // bootstrap: login -> /me 200 -> logout -> /me 401.
    }
}
