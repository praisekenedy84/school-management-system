<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * POST/DELETE /api/v1/students/{student}/guardians — StudentGuardianController.
 *
 * Authorization for `store` lives in LinkGuardianRequest::authorize() (checks
 * `update` on the Student, i.e. StudentPolicy::update — school_admin/
 * tenant_admin only). `destroy` calls $this->authorize('update', $student)
 * directly in the controller.
 */
class GuardianLinkingTest extends TestCase
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

        $this->school = School::factory()->create();
    }

    protected function tearDown(): void
    {
        Auth::guard('web')->logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    private function tenantUrl(string $path): string
    {
        return "http://{$this->tenantId}.sms.test{$path}";
    }

    private function admin(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('school_admin');

        return $user;
    }

    /**
     * LinkGuardianRequest requires the target user to hold the `parent`
     * role and share the student's school_id — see its docblock.
     */
    private function guardianUser(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('parent');

        return $user;
    }

    public function test_linking_a_guardian_creates_pivot_row_with_relationship_and_is_primary(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $guardian = $this->guardianUser();

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), [
            'guardian_id' => $guardian->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.guardians');
        $response->assertJsonPath('data.guardians.0.id', $guardian->id);
        $response->assertJsonPath('data.guardians.0.relationship', 'father');
        $response->assertJsonPath('data.guardians.0.is_primary', true);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);
    }

    public function test_unlinking_a_guardian_removes_the_pivot_row(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $guardian = $this->guardianUser();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($admin);

        $response = $this->deleteJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians/{$guardian->id}"));

        $response->assertNoContent();

        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
        ]);
    }

    /**
     * The controller uses `syncWithoutDetaching()`, which on a duplicate
     * `guardian_id` UPDATES the existing pivot row's attributes rather than
     * inserting a second row or erroring — confirmed by reading
     * StudentGuardianController::store(). This test locks in that real
     * behaviour rather than assuming insert-or-fail semantics.
     */
    public function test_linking_the_same_guardian_twice_updates_pivot_instead_of_duplicating(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $guardian = $this->guardianUser();

        $this->actingAs($admin);

        $first = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), [
            'guardian_id' => $guardian->id,
            'relationship' => 'mother',
            'is_primary' => false,
        ]);
        $first->assertOk();

        $second = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), [
            'guardian_id' => $guardian->id,
            'relationship' => 'guardian',
            'is_primary' => true,
        ]);
        $second->assertOk();

        // Exactly one pivot row, with the second call's values applied.
        $this->assertSame(
            1,
            DB::table('student_guardians')
                ->where('student_id', $student->id)
                ->where('guardian_id', $guardian->id)
                ->count()
        );

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'guardian',
            'is_primary' => true,
        ]);

        $second->assertJsonCount(1, 'data.guardians');
    }

    public function test_link_guardian_fails_validation_with_missing_guardian_id(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['guardian_id']);
    }

    public function test_linking_a_user_without_the_parent_role_is_rejected(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), [
            'guardian_id' => $teacher->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['guardian_id']);
        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $teacher->id,
        ]);
    }

    public function test_linking_a_guardian_from_a_different_school_is_rejected(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $otherSchool = School::factory()->create();
        $outsideGuardian = User::factory()->create(['school_id' => $otherSchool->id]);
        $outsideGuardian->assignRole('parent');

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), [
            'guardian_id' => $outsideGuardian->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['guardian_id']);
        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $outsideGuardian->id,
        ]);
    }

    public function test_role_without_update_permission_cannot_link_guardian(): void
    {
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $guardian = User::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($parent);

        $response = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/guardians"), [
            'guardian_id' => $guardian->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
        ]);
    }
}
