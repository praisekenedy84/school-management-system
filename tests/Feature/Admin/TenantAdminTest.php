<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class TenantAdminTest extends TestCase
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

    public function test_tenant_admin_can_create_and_update_school(): void
    {
        $admin = User::factory()->withoutSchool()->create();
        $admin->assignRole('tenant_admin');

        $this->actingAs($admin);

        $create = $this->postJson($this->url('/api/v1/admin/schools'), [
            'name' => 'North Campus',
            'code' => 'NORTH',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.code', 'NORTH');

        $schoolId = $create->json('data.id');

        $update = $this->putJson($this->url("/api/v1/admin/schools/{$schoolId}"), [
            'name' => 'North Campus Updated',
            'code' => 'NORTH',
            'hostel_available' => true,
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.hostel_available', true);
    }

    public function test_school_admin_cannot_create_school(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin);

        $this->postJson($this->url('/api/v1/admin/schools'), [
            'name' => 'Blocked Campus',
            'code' => 'BLOCK',
        ])->assertForbidden();
    }

    public function test_tenant_admin_can_update_settings_branding_and_billing(): void
    {
        $admin = User::factory()->withoutSchool()->create();
        $admin->assignRole('tenant_admin');

        $this->actingAs($admin);

        $this->patchJson($this->url("/api/v1/admin/schools/{$this->school->id}/settings"), [
            'locale' => 'sw',
            'currency' => 'TZS',
            'hostel_available' => true,
        ])->assertOk()->assertJsonPath('data.locale', 'sw');

        $this->patchJson($this->url("/api/v1/admin/schools/{$this->school->id}/branding"), [
            'branding' => [
                'primary_color' => '#0044aa',
                'tagline' => 'Excellence in learning',
            ],
        ])->assertOk()->assertJsonPath('data.branding.primary_color', '#0044aa');

        $this->patchJson($this->url("/api/v1/admin/schools/{$this->school->id}/billing"), [
            'billing' => [
                'billing_contact_email' => 'billing@school.test',
            ],
        ])->assertOk()->assertJsonPath('data.billing.billing_contact_email', 'billing@school.test');
    }

    public function test_school_admin_can_manage_user_roles_in_own_school(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($admin);

        $this->getJson($this->url('/api/v1/admin/users'))
            ->assertOk()
            ->assertJsonFragment(['email' => $teacher->email]);

        $this->putJson($this->url("/api/v1/admin/users/{$teacher->id}/roles"), [
            'roles' => ['class_teacher'],
        ])->assertOk()
            ->assertJsonPath('data.roles.0', 'class_teacher');

        $this->assertTrue($teacher->fresh()->hasRole('class_teacher'));
    }

    public function test_school_admin_cannot_assign_tenant_admin_role(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $target = User::factory()->create(['school_id' => $this->school->id]);
        $target->assignRole('teacher');

        $this->actingAs($admin);

        $this->putJson($this->url("/api/v1/admin/users/{$target->id}/roles"), [
            'roles' => ['tenant_admin'],
        ])->assertUnprocessable();
    }

    public function test_school_admin_cannot_update_roles_for_other_school_user(): void
    {
        $otherSchool = School::factory()->create(['code' => 'OTHER']);
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $otherUser = User::factory()->create(['school_id' => $otherSchool->id]);
        $otherUser->assignRole('teacher');

        $this->actingAs($admin);

        $this->putJson($this->url("/api/v1/admin/users/{$otherUser->id}/roles"), [
            'roles' => ['class_teacher'],
        ])->assertForbidden();
    }

    public function test_teacher_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('teacher');

        $this->actingAs($user);

        $this->getJson($this->url('/api/v1/admin/schools'))->assertOk();
        $this->postJson($this->url('/api/v1/admin/schools'), [
            'name' => 'X',
            'code' => 'X',
        ])->assertForbidden();
        $this->getJson($this->url('/api/v1/admin/users'))->assertForbidden();
    }

    public function test_school_changes_emit_audit_log(): void
    {
        $admin = User::factory()->withoutSchool()->create();
        $admin->assignRole('tenant_admin');

        $this->actingAs($admin);

        $this->patchJson($this->url("/api/v1/admin/schools/{$this->school->id}/settings"), [
            'locale' => 'en',
        ])->assertOk();

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'school.settings_updated')
                ->where('subject_id', $this->school->id)
                ->exists()
        );
    }
}
