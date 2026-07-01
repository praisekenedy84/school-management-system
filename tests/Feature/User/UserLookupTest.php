<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class UserLookupTest extends TestCase
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

    public function test_teacher_lookup_returns_active_teachers_without_search_term(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $activeTeacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true, 'name' => 'Grace Mwangi']);
        $activeTeacher->assignRole('teacher');

        $inactiveTeacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => false, 'name' => 'Inactive Teacher']);
        $inactiveTeacher->assignRole('teacher');

        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/users?role=teacher'));

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Grace Mwangi']);
        $response->assertJsonMissing(['name' => 'Inactive Teacher']);
    }
}
