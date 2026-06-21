<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Phase 0 placeholder behaviour (see app/Policies/StudentPolicy.php
 * docblock): school_admin/tenant_admin pass every check; every other role
 * can view but not mutate.
 */
class StudentPolicyTest extends TestCase
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
        $student = Student::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('school_admin');

        $this->assertTrue($user->can('viewAny', Student::class));
        $this->assertTrue($user->can('view', $student));
        $this->assertTrue($user->can('create', Student::class));
        $this->assertTrue($user->can('update', $student));
        $this->assertTrue($user->can('delete', $student));
    }

    public function test_tenant_admin_can_create_update_delete(): void
    {
        $student = Student::factory()->create();
        $user = User::factory()->withoutSchool()->create();
        $user->assignRole('tenant_admin');

        $this->assertTrue($user->can('create', Student::class));
        $this->assertTrue($user->can('update', $student));
        $this->assertTrue($user->can('delete', $student));
    }

    public function test_other_roles_can_view_but_not_mutate(): void
    {
        $school = School::factory()->create();
        $student = Student::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('parent');

        $this->assertTrue($user->can('viewAny', Student::class));
        $this->assertTrue($user->can('view', $student));

        $this->assertFalse($user->can('create', Student::class));
        $this->assertFalse($user->can('update', $student));
        $this->assertFalse($user->can('delete', $student));
    }
}
