<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\ClassRoom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * The campus boundary (RULES.md §9 / §1): WITHIN one tenant schema,
 * `school_id` + the `BelongsToSchool` global scope (SchoolScope) must stop
 * campus A reading campus B's rows. This is enforced in PHP, not by the
 * Postgres schema, so it's tested differently from SchemaIsolationTest:
 * one tenant, two schools, varying the authenticated user.
 */
class SchoolScopeTest extends TestCase
{
    use CreatesTenant;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAndInitializeTenant();

        $this->schoolA = School::factory()->create(['name' => 'Campus A']);
        $this->schoolB = School::factory()->create(['name' => 'Campus B']);

        Student::factory()->create(['school_id' => $this->schoolA->id]);
        Student::factory()->create(['school_id' => $this->schoolB->id]);

        ClassRoom::factory()->create(['school_id' => $this->schoolA->id]);
        ClassRoom::factory()->create(['school_id' => $this->schoolB->id]);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_school_scoped_user_only_sees_their_own_campus(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);

        $this->actingAs($userA);

        $students = Student::all();
        $classRooms = ClassRoom::all();

        $this->assertCount(1, $students);
        $this->assertSame($this->schoolA->id, $students->first()->school_id);

        $this->assertCount(1, $classRooms);
        $this->assertSame($this->schoolA->id, $classRooms->first()->school_id);
    }

    public function test_school_scoped_user_cannot_see_other_campus_by_id(): void
    {
        $userA = User::factory()->create(['school_id' => $this->schoolA->id]);
        $otherCampusStudent = Student::withoutGlobalScopes()
            ->where('school_id', $this->schoolB->id)
            ->first();

        $this->actingAs($userA);

        $this->assertNull(Student::find($otherCampusStudent->id));
    }

    public function test_tenant_wide_user_with_null_school_id_sees_every_campus(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();

        $this->actingAs($tenantAdmin);

        $this->assertCount(2, Student::all());
        $this->assertCount(2, ClassRoom::all());
    }

    public function test_unauthenticated_context_is_unscoped(): void
    {
        Auth::logout();
        $this->assertNull(Auth::user());

        $this->assertCount(2, Student::all());
        $this->assertCount(2, ClassRoom::all());
    }
}
