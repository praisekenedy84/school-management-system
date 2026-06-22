<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\ClassRoom;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * POST/DELETE /api/v1/classes/{classRoom}/subjects — ClassSubjectController.
 * Authorized via ClassRoomPolicy::update (school_admin/tenant_admin).
 * `class_subjects` has UNIQUE(class_id, subject_id); the controller uses
 * `syncWithoutDetaching()`, so re-attaching is a no-op, not an error.
 */
class ClassSubjectTest extends TestCase
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

    public function test_attach_then_detach_subject_to_class(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $attach = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects"), [
            'subject_id' => $subject->id,
        ]);
        $attach->assertCreated();
        $attach->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
        ]);

        $detach = $this->deleteJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects/{$subject->id}"));
        $detach->assertNoContent();

        $this->assertDatabaseMissing('class_subjects', [
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_reattaching_an_already_attached_subject_does_not_error_or_duplicate(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $first = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects"), [
            'subject_id' => $subject->id,
        ]);
        $first->assertCreated();

        $second = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects"), [
            'subject_id' => $subject->id,
        ]);
        $second->assertCreated();
        $second->assertJsonCount(1, 'data');

        $this->assertSame(
            1,
            DB::table('class_subjects')
                ->where('class_id', $classRoom->id)
                ->where('subject_id', $subject->id)
                ->count()
        );
    }

    public function test_non_admin_cannot_attach_subject(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($teacher);

        $response = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects"), [
            'subject_id' => $subject->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('class_subjects', [
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_index_lists_attached_subjects(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $attached = Subject::factory()->create(['school_id' => $this->school->id, 'name' => 'Biology']);
        Subject::factory()->create(['school_id' => $this->school->id, 'name' => 'Chemistry']);

        $classRoom->subjects()->attach($attached->id);

        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects"));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $attached->id);
    }

    public function test_attach_fails_validation_with_invalid_subject_id(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/subjects"), [
            'subject_id' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject_id']);
    }
}
