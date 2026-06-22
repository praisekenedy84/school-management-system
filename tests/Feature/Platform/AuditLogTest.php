<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesTenant;

    private array $createdPlatformAdminIds = [];

    protected function tearDown(): void
    {
        PlatformAdmin::whereIn('id', $this->createdPlatformAdminIds)->delete();
        AuditLog::where('action', 'subject.created')->delete();
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

    public function test_platform_admin_sees_activity_from_a_tenant(): void
    {
        $admin = $this->createPlatformAdmin();

        // Deliberately NOT calling tenancy()->end() before the HTTP call —
        // mirrors the proven pattern in HostelAllocationTest/DashboardTest:
        // tenancy stays initialized continuously from setup through the
        // request, instead of round-tripping through end()+re-initialize
        // via InitializeTenancyFromSession for a brand-new simulated
        // request (which is flaky here once Spatie roles are involved).
        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('tenant_admin');

        $this->withSession(['tenant_id' => $tenant->getTenantKey()])
            ->actingAs($user)
            ->postJson('/api/v1/subjects', ['name' => 'Mathematics', 'code' => 'MATH'])
            ->assertCreated();

        Auth::guard('web')->logout();
        tenancy()->end();

        $response = $this->actingAs($admin, 'platform')->getJson('/api/v1/platform/audit-logs?tenant_id='.$tenant->getTenantKey());

        $response->assertOk();
        $rows = collect($response->json('data'));
        $this->assertTrue($rows->contains(fn ($row) => $row['action'] === 'subject.created' && $row['tenant_id'] === $tenant->getTenantKey()));
    }

    public function test_a_tenant_user_cannot_read_the_platform_audit_log(): void
    {
        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('tenant_admin');

        $response = $this->withSession(['tenant_id' => $tenant->getTenantKey()])
            ->actingAs($user)
            ->getJson('/api/v1/platform/audit-logs');

        $response->assertStatus(403);
    }

    public function test_export_returns_an_excel_file(): void
    {
        $admin = $this->createPlatformAdmin();

        $tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('tenant_admin');

        $this->withSession(['tenant_id' => $tenant->getTenantKey()])
            ->actingAs($user)
            ->postJson('/api/v1/subjects', ['name' => 'Mathematics', 'code' => 'MATH'])
            ->assertCreated();

        Auth::guard('web')->logout();
        tenancy()->end();

        $response = $this->actingAs($admin, 'platform')->get('/api/v1/platform/audit-logs/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }
}
