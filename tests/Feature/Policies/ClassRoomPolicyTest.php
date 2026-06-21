<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\ClassRoom;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Phase 0 placeholder behaviour (see app/Policies/ClassRoomPolicy.php
 * docblock): school_admin/tenant_admin pass every check; every other role
 * can view but not mutate.
 */
class ClassRoomPolicyTest extends TestCase
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
        $classRoom = ClassRoom::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('school_admin');

        $this->assertTrue($user->can('viewAny', ClassRoom::class));
        $this->assertTrue($user->can('view', $classRoom));
        $this->assertTrue($user->can('create', ClassRoom::class));
        $this->assertTrue($user->can('update', $classRoom));
        $this->assertTrue($user->can('delete', $classRoom));
    }

    public function test_tenant_admin_can_create_update_delete(): void
    {
        $classRoom = ClassRoom::factory()->create();
        $user = User::factory()->withoutSchool()->create();
        $user->assignRole('tenant_admin');

        $this->assertTrue($user->can('create', ClassRoom::class));
        $this->assertTrue($user->can('update', $classRoom));
        $this->assertTrue($user->can('delete', $classRoom));
    }

    public function test_other_roles_can_view_but_not_mutate(): void
    {
        $school = School::factory()->create();
        $classRoom = ClassRoom::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('class_teacher');

        $this->assertTrue($user->can('viewAny', ClassRoom::class));
        $this->assertTrue($user->can('view', $classRoom));

        $this->assertFalse($user->can('create', ClassRoom::class));
        $this->assertFalse($user->can('update', $classRoom));
        $this->assertFalse($user->can('delete', $classRoom));
    }
}
