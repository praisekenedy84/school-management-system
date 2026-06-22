<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Academic sessions CRUD (AcademicSessionController + AcademicSessionRequest).
 * AcademicSessionPolicy: tenant_admin/school_admin mutate, everyone views.
 */
class AcademicSessionTest extends TestCase
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

    public function test_export_returns_an_excel_file(): void
    {
        AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/academic-sessions/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_crud_happy_path(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $create = $this->postJson($this->tenantUrl('/api/v1/academic-sessions'), [
            'name' => '2026/2027',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.name', '2026/2027');
        $create->assertJsonPath('data.is_current', true);
        $sessionId = $create->json('data.id');

        $update = $this->putJson($this->tenantUrl("/api/v1/academic-sessions/{$sessionId}"), [
            'name' => '2026/2027 (revised)',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.name', '2026/2027 (revised)');

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/academic-sessions/{$sessionId}"));
        $delete->assertNoContent();

        $this->assertDatabaseMissing('academic_sessions', ['id' => $sessionId]);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/academic-sessions'), [
            'name' => '2026/2027',
            'start_date' => '2027-01-01',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_date']);
    }

    public function test_marking_a_session_current_demotes_the_previous_current_session(): void
    {
        $admin = $this->admin();
        $previousCurrent = AcademicSession::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
            'is_current' => true,
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/academic-sessions'), [
            'name' => '2026/2027',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('academic_sessions', [
            'id' => $previousCurrent->id,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('academic_sessions', [
            'id' => $response->json('data.id'),
            'is_current' => true,
        ]);
    }

    public function test_non_admin_cannot_create_or_delete(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher);

        $create = $this->postJson($this->tenantUrl('/api/v1/academic-sessions'), [
            'name' => '2030/2031',
            'start_date' => '2030-01-01',
            'end_date' => '2031-12-31',
        ]);
        $create->assertStatus(403);

        $delete = $this->deleteJson($this->tenantUrl("/api/v1/academic-sessions/{$session->id}"));
        $delete->assertStatus(403);
    }
}
