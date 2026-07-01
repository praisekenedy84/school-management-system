<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class AcademicTermTest extends TestCase
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

    public function test_create_and_list_terms_for_a_session(): void
    {
        $admin = $this->admin();
        $session = AcademicSession::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->actingAs($admin);

        $create = $this->postJson($this->tenantUrl("/api/v1/academic-sessions/{$session->id}/terms"), [
            'name' => 'Term I',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => true,
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Term I');
        $create->assertJsonPath('data.is_current', true);

        $list = $this->getJson($this->tenantUrl("/api/v1/academic-sessions/{$session->id}/terms"));
        $list->assertOk();
        $list->assertJsonCount(1, 'data');
    }

    public function test_overlapping_term_dates_are_rejected(): void
    {
        $admin = $this->admin();
        $session = AcademicSession::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        AcademicTerm::factory()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $session->id,
            'name' => 'Term I',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/academic-sessions/{$session->id}/terms"), [
            'name' => 'Term II',
            'start_date' => '2026-04-01',
            'end_date' => '2026-07-31',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
    }

    public function test_setting_current_term_demotes_others_in_same_session(): void
    {
        $admin = $this->admin();
        $session = AcademicSession::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $termA = AcademicTerm::factory()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $session->id,
            'name' => 'Term I',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => true,
        ]);

        $termB = AcademicTerm::factory()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $session->id,
            'name' => 'Term II',
            'start_date' => '2026-05-01',
            'end_date' => '2026-08-31',
            'is_current' => false,
        ]);

        $this->actingAs($admin);

        $this->putJson($this->tenantUrl("/api/v1/academic-sessions/{$session->id}/terms/{$termB->id}"), [
            'name' => 'Term II',
            'start_date' => '2026-05-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ])->assertOk();

        $this->assertDatabaseHas('academic_terms', ['id' => $termB->id, 'is_current' => true]);
        $this->assertDatabaseHas('academic_terms', ['id' => $termA->id, 'is_current' => false]);
    }
}
