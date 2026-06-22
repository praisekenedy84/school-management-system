<?php

declare(strict_types=1);

namespace Tests\Feature\Hostel;

use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Regression: HostelRequest used to rely entirely on BelongsToSchool's
 * `auth()->user()->school_id` stamp, which is null for a tenant-wide admin —
 * violating hostels.school_id's NOT NULL constraint. Now school_id is
 * required explicitly on create when the acting user has none of their own.
 */
class HostelCrudTest extends TestCase
{
    use CreatesTenant;

    private Tenant $tenant;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->school = School::factory()->create();
    }

    protected function tearDown(): void
    {
        Auth::guard('web')->logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    private function asUser(User $user)
    {
        return $this->withSession(['tenant_id' => $this->tenant->getTenantKey()])->actingAs($user);
    }

    public function test_hostels_export_returns_an_excel_file(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');
        Hostel::factory()->create(['school_id' => $this->school->id]);

        $response = $this->asUser($admin)->get('/api/v1/hostels/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_hostel_rooms_export_returns_an_excel_file(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
        HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);

        $response = $this->asUser($admin)->get('/api/v1/hostel-rooms/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_tenant_admin_without_school_id_must_specify_one(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');

        $response = $this->asUser($tenantAdmin)->postJson('/api/v1/hostels', [
            'name' => 'Kilimanjaro House',
            'gender' => 'male',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['school_id']);
    }

    public function test_tenant_admin_can_create_a_hostel_for_a_chosen_school(): void
    {
        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');

        $response = $this->asUser($tenantAdmin)->postJson('/api/v1/hostels', [
            'name' => 'Kilimanjaro House',
            'gender' => 'male',
            'school_id' => $this->school->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('hostels', [
            'name' => 'Kilimanjaro House',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_school_admin_can_create_a_hostel_without_specifying_school(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $response = $this->asUser($admin)->postJson('/api/v1/hostels', [
            'name' => 'Serengeti House',
            'gender' => 'female',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('hostels', [
            'name' => 'Serengeti House',
            'school_id' => $this->school->id,
        ]);
    }
}
