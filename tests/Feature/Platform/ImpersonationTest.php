<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Full read+write impersonation (Phase 6, confirmed scope: not read-only).
 * The dual-guard design: the platform admin stays authenticated on the
 * `platform` guard while the impersonated user is logged in on `web`, both
 * in the same session.
 *
 * Assertions are made in-process (direct guard/tenancy checks) rather than
 * via a second chained HTTP call wherever possible — Laravel's test client
 * doesn't replay cookies between separate postJson()/getJson() calls (see
 * LoginTest's docblock), so a fresh simulated request can't be relied on to
 * reflect state a previous call set up, only what we put there ourselves.
 */
class ImpersonationTest extends TestCase
{
    use CreatesTenant;

    private array $createdPlatformAdminIds = [];

    protected function tearDown(): void
    {
        Auth::guard('web')->logout();
        Auth::guard('platform')->logout();
        PlatformAdmin::whereIn('id', $this->createdPlatformAdminIds)->delete();
        AuditLog::where('action', 'like', 'impersonation.%')->delete();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    private function createPlatformAdmin(): PlatformAdmin
    {
        $this->migrateCentralDatabaseOnce();

        $admin = PlatformAdmin::factory()->create();
        $this->createdPlatformAdminIds[] = $admin->id;

        return $admin;
    }

    public function test_platform_admin_can_start_and_act_as_the_target_user(): void
    {
        $admin = $this->createPlatformAdmin();

        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $target = User::factory()->create(['name' => 'Target User']);
        $target->assignRole('finance_manager');
        tenancy()->end();

        $response = $this->actingAs($admin, 'platform')->postJson('/api/v1/platform/impersonate', [
            'tenant_id' => $tenant->getTenantKey(),
            'user_id' => $target->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $target->id);
        $response->assertJsonPath('impersonation.platform_admin_id', $admin->id);

        // In-process: this is still the same request cycle's container, so
        // the guards/session/tenancy this controller call just set are
        // directly inspectable here.
        $this->assertAuthenticatedAs($target, 'web');
        $this->assertSame($tenant->getTenantKey(), session('tenant_id'));
        $this->assertSame($admin->id, session('impersonation')['platform_admin_id']);

        // audit_logs is a central table — explicit connection, since the
        // default connection right now is the tenant schema we just
        // initialized for the impersonated user.
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->getTenantKey(),
            'action' => 'impersonation.started',
            'actor_type' => 'platform_admin',
            'actor_id' => $admin->id,
            'subject_id' => $target->id,
        ], 'pgsql');
    }

    public function test_stopping_impersonation_returns_to_the_platform_admin(): void
    {
        $admin = $this->createPlatformAdmin();

        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $target = User::factory()->create();
        $target->assignRole('finance_manager');
        tenancy()->end();

        $this->actingAs($admin, 'platform')->postJson('/api/v1/platform/impersonate', [
            'tenant_id' => $tenant->getTenantKey(),
            'user_id' => $target->id,
        ])->assertOk();

        $stop = $this->postJson('/api/v1/platform/impersonate/stop');

        $stop->assertOk();
        $stop->assertJsonPath('data.id', $admin->id);
        $stop->assertJsonPath('data.type', 'platform_admin');
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('impersonation'));
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'platform');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->getTenantKey(),
            'action' => 'impersonation.ended',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_a_tenant_admin_cannot_call_impersonate(): void
    {
        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $caller = User::factory()->create();
        $caller->assignRole('tenant_admin');
        $target = User::factory()->create();
        tenancy()->end();

        $response = $this->withSession(['tenant_id' => $tenant->getTenantKey()])
            ->actingAs($caller)
            ->postJson('/api/v1/platform/impersonate', [
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $target->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_impersonating_tenant_a_never_exposes_tenant_b_data(): void
    {
        $admin = $this->createPlatformAdmin();

        $tenantA = $this->createAndInitializeTenant();
        $userA = User::factory()->create(['name' => 'User A']);
        tenancy()->end();

        $tenantB = $this->createAndInitializeTenant();
        $userB = User::factory()->create(['name' => 'User B']);
        tenancy()->end();

        $this->actingAs($admin, 'platform')->postJson('/api/v1/platform/impersonate', [
            'tenant_id' => $tenantA->getTenantKey(),
            'user_id' => $userA->id,
        ])->assertOk();

        // Still inside the request that just initialized Tenant A's schema
        // as the active connection — Tenant B's user must be unreachable.
        $this->assertSame($tenantA->getTenantKey(), tenant('id'));
        $this->assertNull(User::find($userB->id));
        $this->assertNotNull(User::find($userA->id));
    }
}
