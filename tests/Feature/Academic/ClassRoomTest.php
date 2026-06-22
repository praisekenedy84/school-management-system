<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\ClassRoom;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\Concerns\MakesXlsxUploads;
use Tests\TestCase;

/**
 * Classes CRUD (ClassRoomController + ClassRoomRequest), mirroring SubjectTest.
 * ClassRoomPolicy: tenant_admin/school_admin/academic_director mutate, everyone views.
 */
class ClassRoomTest extends TestCase
{
    use CreatesTenant;
    use MakesXlsxUploads;

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

        $create = $this->postJson($this->tenantUrl('/api/v1/classes'), [
            'name' => 'Form 1',
            'level' => 1,
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Form 1');
        $classId = $create->json('data.id');

        $this->assertDatabaseHas('classes', [
            'id' => $classId,
            'name' => 'Form 1',
            'school_id' => $this->school->id,
        ]);

        $show = $this->getJson($this->tenantUrl("/api/v1/classes/{$classId}"));
        $show->assertOk();
        $show->assertJsonPath('data.name', 'Form 1');

        $update = $this->putJson($this->tenantUrl("/api/v1/classes/{$classId}"), [
            'name' => 'Form 1A',
            'level' => 1,
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.name', 'Form 1A');

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/classes/{$classId}"));
        $delete->assertNoContent();

        $this->assertDatabaseMissing('classes', ['id' => $classId]);
    }

    public function test_export_returns_an_excel_file(): void
    {
        ClassRoom::factory()->create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/classes/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_import_creates_classes(): void
    {
        $this->actingAs($this->admin());

        $file = $this->makeXlsxUpload(['name', 'level'], [['Form 1', '1'], ['Form 2', '2']]);

        $response = $this->post($this->tenantUrl('/api/v1/classes/import'), ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('created', 2);
        $response->assertJsonPath('failed', 0);

        $this->assertDatabaseHas('classes', ['school_id' => $this->school->id, 'name' => 'Form 1', 'level' => 1]);
        $this->assertDatabaseHas('classes', ['school_id' => $this->school->id, 'name' => 'Form 2', 'level' => 2]);
    }

    public function test_duplicate_name_within_same_school_fails_validation(): void
    {
        $admin = $this->admin();
        ClassRoom::factory()->create(['school_id' => $this->school->id, 'name' => 'Form 2']);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/classes'), [
            'name' => 'Form 2',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_academic_director_can_create_update_delete(): void
    {
        $director = User::factory()->create(['school_id' => $this->school->id]);
        $director->assignRole('academic_director');

        $this->actingAs($director);

        $response = $this->postJson($this->tenantUrl('/api/v1/classes'), [
            'name' => 'Form 3',
        ]);

        $response->assertCreated();
    }

    public function test_non_admin_cannot_create_update_or_delete(): void
    {
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher);

        $create = $this->postJson($this->tenantUrl('/api/v1/classes'), ['name' => 'Form 4']);
        $create->assertStatus(403);

        $update = $this->putJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}"), ['name' => 'Renamed']);
        $update->assertStatus(403);

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}"));
        $delete->assertStatus(403);
    }
}
