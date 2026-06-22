<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\Concerns\MakesXlsxUploads;
use Tests\TestCase;

/**
 * Subjects CRUD (SubjectController + SubjectRequest). Uniqueness is
 * `(school_id, name)` (migration 2026_06_22_000003); SubjectPolicy is the
 * Phase 0/1 placeholder — school_admin/tenant_admin mutate, everyone views.
 */
class SubjectTest extends TestCase
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

    public function test_export_returns_an_excel_file(): void
    {
        Subject::factory()->create(['school_id' => $this->school->id, 'name' => 'Mathematics']);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/subjects/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_export_returns_a_pdf_file(): void
    {
        Subject::factory()->create(['school_id' => $this->school->id, 'name' => 'Mathematics']);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/subjects/export?format=pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_import_template_returns_an_excel_file(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/subjects/import-template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_import_creates_valid_rows_and_reports_invalid_ones(): void
    {
        $this->actingAs($this->admin());

        $file = $this->makeXlsxUpload(
            ['name', 'code'],
            [
                ['Physics', 'PHY'],
                ['', 'BLANK'], // missing name -> should be reported, not abort the batch
                ['Chemistry', 'CHE'],
            ]
        );

        $response = $this->post($this->tenantUrl('/api/v1/subjects/import'), ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('created', 2);
        $response->assertJsonPath('failed', 1);
        $response->assertJsonPath('errors.0.row', 3);

        $this->assertDatabaseHas('subjects', ['school_id' => $this->school->id, 'name' => 'Physics']);
        $this->assertDatabaseHas('subjects', ['school_id' => $this->school->id, 'name' => 'Chemistry']);
    }

    public function test_tenant_admin_must_choose_a_school_to_import_into(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');
        $this->actingAs($tenantAdmin);

        $file = $this->makeXlsxUpload(['name', 'code'], [['Physics', 'PHY']]);

        $response = $this->post($this->tenantUrl('/api/v1/subjects/import'), ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['school_id']);
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

    public function test_tenant_admin_without_school_id_must_specify_one(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');

        $this->actingAs($tenantAdmin);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Geography',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['school_id']);
    }

    public function test_tenant_admin_can_create_subject_for_a_chosen_school(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');

        $this->actingAs($tenantAdmin);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Geography',
            'school_id' => $this->school->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('subjects', [
            'name' => 'Geography',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_school_admin_cannot_override_school_id(): void
    {
        $otherSchool = School::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'History',
            'school_id' => $otherSchool->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('subjects', [
            'name' => 'History',
            'school_id' => $this->school->id,
        ]);
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

    public function test_academic_director_can_create_subject(): void
    {
        $director = User::factory()->create(['school_id' => $this->school->id]);
        $director->assignRole('academic_director');

        $this->actingAs($director);

        $response = $this->postJson($this->tenantUrl('/api/v1/subjects'), [
            'name' => 'Biology',
        ]);

        $response->assertCreated();
    }
}
