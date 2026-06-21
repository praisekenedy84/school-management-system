<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Phase 0 placeholder behaviour (see app/Policies/SchoolPolicy.php docblock):
 * school_admin/tenant_admin pass every check; every other role can view but
 * not mutate. This is a smoke test for the placeholder, not the full RBAC
 * matrix (RULES.md §5) that replaces it later.
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

    public function test_school_admin_can_create_update_delete(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('school_admin');

        $this->assertTrue($user->can('viewAny', School::class));
        $this->assertTrue($user->can('view', $school));
        $this->assertTrue($user->can('create', School::class));
        $this->assertTrue($user->can('update', $school));
        $this->assertTrue($user->can('delete', $school));
    }

    public function test_tenant_admin_can_create_update_delete(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->withoutSchool()->create();
        $user->assignRole('tenant_admin');

        $this->assertTrue($user->can('create', School::class));
        $this->assertTrue($user->can('update', $school));
        $this->assertTrue($user->can('delete', $school));
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
