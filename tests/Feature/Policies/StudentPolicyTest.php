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
 * docblock): school_admin/tenant_admin pass every check; staff can view
 * but not mutate; a parent may only view their own ward.
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
        $user->assignRole('teacher');

        $this->assertTrue($user->can('viewAny', Student::class));
        $this->assertTrue($user->can('view', $student));

        $this->assertFalse($user->can('create', Student::class));
        $this->assertFalse($user->can('update', $student));
        $this->assertFalse($user->can('delete', $student));
    }

    public function test_parent_can_view_own_ward_but_not_other_students(): void
    {
        $school = School::factory()->create();
        $ward = Student::factory()->create(['school_id' => $school->id]);
        $otherStudent = Student::factory()->create(['school_id' => $school->id]);
        $parent = User::factory()->create(['school_id' => $school->id]);
        $parent->assignRole('parent');
        $parent->wards()->attach($ward->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->assertTrue($parent->can('view', $ward));
        $this->assertFalse($parent->can('view', $otherStudent));
        $this->assertFalse($parent->can('create', Student::class));
    }
}
