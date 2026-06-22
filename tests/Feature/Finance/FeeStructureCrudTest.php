<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Fee-structure listing export (FeeStructureController::export). Mirrors the
 * shared export pattern; reuses FeeStructurePolicy::viewAny (open to all roles
 * in-tenant) — the export query is the same scoped/filtered query as index().
 */
class FeeStructureCrudTest extends TestCase
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
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        FeeStructure::factory()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $session->id,
            'class_id' => $classRoom->id,
        ]);

        $this->actingAs($this->admin());

        $response = $this->get($this->tenantUrl('/api/v1/fee-structures/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }
}
