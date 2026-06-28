<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\NavigationItem;
use App\Models\School;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class RbacCustomizationTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(NavigationSeeder::class);

        $this->school = School::factory()->create(['code' => 'MAIN']);
    }

    protected function tearDown(): void
    {
        Auth::guard('web')->logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    private function url(string $path): string
    {
        return "http://{$this->tenantId}.sms.test{$path}";
    }

    public function test_tenant_admin_can_create_role_and_sync_permissions(): void
    {
        $admin = User::factory()->withoutSchool()->create();
        $admin->assignRole('tenant_admin');

        $this->actingAs($admin);

        $this->postJson($this->url('/api/v1/admin/role-definitions'), [
            'name' => 'lab_assistant',
            'permissions' => ['students.view_basic_info', 'academic.manage_assignments'],
        ])->assertCreated();

        $this->putJson($this->url('/api/v1/admin/role-definitions/lab_assistant/permissions'), [
            'permissions' => ['students.view_basic_info'],
        ])->assertOk();

        $this->assertTrue(Role::findByName('lab_assistant', 'web')->hasPermissionTo('students.view_basic_info'));
    }

    public function test_school_admin_cannot_grant_tenant_permissions_to_role(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin);

        $this->putJson($this->url('/api/v1/admin/role-definitions/teacher/permissions'), [
            'permissions' => ['tenant.manage_schools', 'students.view_basic_info'],
        ])->assertForbidden();
    }

    public function test_tenant_admin_can_customize_navigation_item(): void
    {
        $admin = User::factory()->withoutSchool()->create();
        $admin->assignRole('tenant_admin');

        $item = NavigationItem::query()->where('path', '/students')->firstOrFail();

        $this->actingAs($admin);

        $this->patchJson($this->url("/api/v1/admin/navigation/items/{$item->id}"), [
            'label' => 'Learners',
            'permissions' => ['students.view_basic_info'],
        ])->assertOk()->assertJsonPath('data.label', 'Learners');
    }

    public function test_authenticated_user_can_read_navigation_tree(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('teacher');

        $this->actingAs($user);

        $this->getJson($this->url('/api/v1/navigation'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['label', 'items']]]);
    }
}
