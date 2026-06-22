<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Platform Admin provisioning a brand-new tenant (Phase 6). Confirms the
 * full pipeline: schema creation, RBAC seeding, initial school + tenant_admin
 * user, and that the action is gated to the `platform` guard specifically.
 */
class TenantProvisioningTest extends TestCase
{
    use CreatesTenant;

    private array $createdPlatformAdminIds = [];

    /** @var list<string> tenant ids created directly via the API, not via createTenant() */
    private array $apiProvisionedTenantIds = [];

    protected function tearDown(): void
    {
        PlatformAdmin::whereIn('id', $this->createdPlatformAdminIds)->delete();
        AuditLog::where('action', 'tenant.provisioned')->delete();

        foreach ($this->apiProvisionedTenantIds as $tenantId) {
            Tenant::find($tenantId)?->delete();
        }

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

    public function test_platform_admin_can_provision_a_new_tenant(): void
    {
        $admin = $this->createPlatformAdmin();

        $response = $this->actingAs($admin, 'platform')->postJson('/api/v1/platform/tenants', [
            'tenant_id' => 'greenwood-academy',
            'school_name' => 'Greenwood Academy',
            'school_code' => 'GWA',
            'admin_name' => 'Jane Tenant Admin',
            'admin_email' => 'jane@greenwood.test',
            'admin_password' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.id', 'greenwood-academy');

        $tenant = Tenant::find('greenwood-academy');
        $this->assertNotNull($tenant);
        $this->apiProvisionedTenantIds[] = 'greenwood-academy';

        tenancy()->initialize($tenant);
        $this->assertSame(1, School::where('code', 'GWA')->count());
        $admin_user = User::where('email', 'jane@greenwood.test')->first();
        $this->assertNotNull($admin_user);
        $this->assertTrue($admin_user->hasRole('tenant_admin'));
        tenancy()->end();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => 'greenwood-academy',
            'action' => 'tenant.provisioned',
            'actor_type' => 'platform_admin',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_duplicate_tenant_id_is_rejected(): void
    {
        $admin = $this->createPlatformAdmin();
        $existing = $this->createTenant('existing-tenant');

        $response = $this->actingAs($admin, 'platform')->postJson('/api/v1/platform/tenants', [
            'tenant_id' => $existing->getTenantKey(),
            'school_name' => 'Whatever',
            'school_code' => 'WHA',
            'admin_name' => 'Someone',
            'admin_email' => 'someone@example.com',
            'admin_password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_tenant_user_cannot_provision_a_tenant(): void
    {
        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('tenant_admin');
        tenancy()->end();

        $response = $this->withSession(['tenant_id' => $tenant->getTenantKey()])
            ->actingAs($user)
            ->postJson('/api/v1/platform/tenants', [
                'tenant_id' => 'should-not-exist',
                'school_name' => 'Whatever',
                'school_code' => 'WHA',
                'admin_name' => 'Someone',
                'admin_email' => 'someone@example.com',
                'admin_password' => 'password123',
            ]);

        $response->assertStatus(403);
        $this->assertNull(Tenant::find('should-not-exist'));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/platform/tenants', [
            'tenant_id' => 'should-not-exist',
            'school_name' => 'Whatever',
            'school_code' => 'WHA',
            'admin_name' => 'Someone',
            'admin_email' => 'someone@example.com',
            'admin_password' => 'password123',
        ]);

        $response->assertStatus(401);
    }
}
