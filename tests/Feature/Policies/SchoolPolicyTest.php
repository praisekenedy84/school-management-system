<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * School administration RBAC — tenant.manage_* permissions (RULES.md §5).
 */
class SchoolPolicyTest extends TestCase
{
    use CreatesTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_tenant_admin_can_manage_schools_and_settings(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->withoutSchool()->create();
        $user->assignRole('tenant_admin');

        $this->assertTrue($user->can('create', School::class));
        $this->assertTrue($user->can('update', $school));
        $this->assertTrue($user->can('delete', $school));
        $this->assertTrue($user->can('updateSettings', $school));
        $this->assertTrue($user->can('updateBranding', $school));
        $this->assertTrue($user->can('updateBilling', $school));
    }

    public function test_school_admin_can_view_but_not_manage_schools(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('school_admin');

        $this->assertTrue($user->can('viewAny', School::class));
        $this->assertTrue($user->can('view', $school));
        $this->assertFalse($user->can('create', School::class));
        $this->assertFalse($user->can('update', $school));
        $this->assertFalse($user->can('delete', $school));
        $this->assertFalse($user->can('updateSettings', $school));
        $this->assertFalse($user->can('updateBranding', $school));
        $this->assertFalse($user->can('updateBilling', $school));
    }

    public function test_other_roles_can_view_but_not_mutate(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('teacher');

        $this->assertTrue($user->can('viewAny', School::class));
        $this->assertTrue($user->can('view', $school));
        $this->assertFalse($user->can('create', School::class));
        $this->assertFalse($user->can('update', $school));
        $this->assertFalse($user->can('delete', $school));
    }
}
