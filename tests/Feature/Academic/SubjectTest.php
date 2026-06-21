<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Subjects CRUD (SubjectController + SubjectRequest). Uniqueness is
 * `(school_id, name)` (migration 2026_06_22_000003); SubjectPolicy is the
 * Phase 0/1 placeholder — school_admin/tenant_admin mutate, everyone views.
 */
class SubjectTest extends TestCase
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

    public function test_crud_happy_path(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $create = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Mathematics',
            'code' => 'MATH-1',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Mathematics');
        $subjectId = $create->json('data.id');

        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'name' => 'Mathematics',
            'school_id' => $this->school->id,
        ]);

        $show = $this->getJson($this->tenantUrl("/api/v1/subjects/{$subjectId}"));
        $show->assertOk();
        $show->assertJsonPath('data.name', 'Mathematics');

        $update = $this->putJson($this->tenantUrl("/api/v1/subjects/{$subjectId}"), [
            'name' => 'Advanced Mathematics',
            'code' => 'MATH-1',
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.name', 'Advanced Mathematics');

        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'name' => 'Advanced Mathematics',
        ]);

        $index = $this->getJson($this->tenantUrl('/api/v1/subjects'));
        $index->assertOk();
        $index->assertJsonCount(1, 'data');

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/subjects/{$subjectId}"));
        $delete->assertNoContent();

        $this->assertDatabaseMissing('subjects', ['id' => $subjectId]);
    }

    public function test_duplicate_name_within_same_school_fails_validation(): void
    {
        $admin = $this->admin();
        Subject::factory()->create(['school_id' => $this->school->id, 'name' => 'Physics']);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Physics',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_same_name_allowed_across_different_schools(): void
    {
        $otherSchool = School::factory()->create();
        Subject::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Physics']);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Physics',
        ]);

        $response->assertCreated();
    }

    public function test_non_admin_cannot_create_subject(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Chemistry',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('subjects', ['name' => 'Chemistry']);
    }

    public function test_non_admin_cannot_update_or_delete_subject(): void
    {
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);

        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher);

        $update = $this->putJson($this->tenantUrl("/api/v1/subjects/{$subject->id}"), [
            'name' => 'Renamed',
        ]);
        $update->assertStatus(403);

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/subjects/{$subject->id}"));
        $delete->assertStatus(403);
    }

    public function test_index_is_scoped_to_the_authenticated_users_school(): void
    {
        $otherSchool = School::factory()->create();

        Subject::factory()->count(2)->create(['school_id' => $this->school->id]);
        Subject::factory()->count(3)->create(['school_id' => $otherSchool->id]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/subjects'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertSame($this->school->id, $row['school_id']);
        }
    }

    public function test_create_requires_name(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }
}
