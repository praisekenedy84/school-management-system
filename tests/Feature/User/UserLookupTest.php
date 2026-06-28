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

    public function test_school_admin_can_search_teachers_in_their_school(): void
    {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Peter Mushi',
            'email' => 'peter@school.test',
        ]);
        $teacher->assignRole('teacher');

        User::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Jane Parent',
            'email' => 'jane@school.test',
        ])->assignRole('parent');

        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/users?role=teacher&search=peter'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $teacher->id);
        $response->assertJsonPath('data.0.name', 'Peter Mushi');
        $response->assertJsonMissingPath('data.0.roles');
    }

    public function test_school_admin_can_search_guardians_scoped_to_school(): void
    {
        $otherSchool = School::factory()->create();

        $guardian = User::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Mary Guardian',
            'email' => 'mary@school.test',
        ]);
        $guardian->assignRole('parent');

        User::factory()->create([
            'school_id' => $otherSchool->id,
            'name' => 'Other Parent',
            'email' => 'other@school.test',
        ])->assignRole('parent');

        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/users?role=parent&search=mary'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $guardian->id);
    }

    public function test_teacher_cannot_lookup_users(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');
        $this->actingAs($teacher);

        $this->getJson($this->tenantUrl('/api/v1/users?role=teacher'))
            ->assertForbidden();
    }

    public function test_lookup_requires_role_parameter(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');
        $this->actingAs($admin);

        $this->getJson($this->tenantUrl('/api/v1/users'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }
}
