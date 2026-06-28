<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use CreatesTenant;

    private array $createdPlatformAdminIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateCentralDatabaseOnce();
        PlatformSetting::query()->delete();
    }

    protected function tearDown(): void
    {
        PlatformAdmin::whereIn('id', $this->createdPlatformAdminIds)->delete();
        AuditLog::where('action', 'platform.settings_updated')->delete();

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

    public function test_platform_admin_can_view_and_update_settings(): void
    {
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin, 'platform');

        $show = $this->getJson('/api/v1/platform/settings');
        $show->assertOk();
        $show->assertJsonPath('data.platform_name', 'School Management System');

        $update = $this->patchJson('/api/v1/platform/settings', [
            'platform_name' => 'EduPlatform TZ',
            'support_email' => 'support@edu.test',
            'maintenance_mode' => true,
            'branding' => ['primary_color' => '#112233'],
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.platform_name', 'EduPlatform TZ');
        $update->assertJsonPath('data.maintenance_mode', true);

        $this->assertDatabaseHas('platform_settings', [
            'platform_name' => 'EduPlatform TZ',
            'support_email' => 'support@edu.test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.settings_updated',
        ]);
    }

    public function test_tenant_user_cannot_access_platform_settings(): void
    {
        $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('tenant_admin');

        $this->actingAs($user);

        $this->getJson('/api/v1/platform/settings')->assertForbidden();
    }

    public function test_current_returns_singleton_row(): void
    {
        $first = PlatformSetting::current();
        $second = PlatformSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PlatformSetting::on(config('tenancy.database.central_connection'))->count());
    }
}
