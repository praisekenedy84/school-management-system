<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class AssignmentLifecycleTest extends TestCase
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

    public function test_draft_can_be_edited_and_published_then_archived(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $teacherAssignment = TeacherAssignment::factory()->create([
            'school_id' => $this->school->id,
            'teacher_id' => $teacher->id,
            'class_id' => $classRoom->id,
            'subject_id' => $subject->id,
        ]);

        $assignment = Assignment::factory()->draft()->create([
            'school_id' => $this->school->id,
            'teacher_assignment_id' => $teacherAssignment->id,
            'created_by' => $teacher->id,
            'title' => 'Original',
        ]);

        $this->actingAs($teacher);

        $this->putJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}"), [
            'title' => 'Updated Title',
        ])->assertOk()->assertJsonPath('data.title', 'Updated Title');

        $this->patchJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}/publish"))
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->patchJson($this->tenantUrl("/api/v1/assignments/{$assignment->id}/archive"))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }
}
