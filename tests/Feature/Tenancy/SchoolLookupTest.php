<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * GET /api/v1/schools — read-only lookup feeding the tenant-admin "which
 * school" picker (SchoolController + SchoolPolicy::viewAny, open to anyone).
 */
class SchoolLookupTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);
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

    public function test_lists_every_school_in_the_tenant(): void
    {
        School::factory()->create(['name' => 'Alpha School']);
        School::factory()->create(['name' => 'Beta School']);

        $user = User::factory()->withoutSchool()->create();
        $user->assignRole('tenant_admin');
        $this->actingAs($user);

        $response = $this->getJson($this->tenantUrl('/api/v1/schools'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Alpha School');
    }
}
