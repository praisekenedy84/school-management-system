<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * bootstrap/app.php's withExceptions(): every /api error response should be
 * friendly JSON, never a raw exception message, leaked Eloquent class name,
 * or stack trace. ValidationException (422) is intentionally NOT covered
 * here — it was already correct and is exercised by every other feature test.
 */
class ErrorHandlingTest extends TestCase
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

    public function test_missing_model_returns_a_friendly_404_without_leaking_internal_details(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('school_admin');

        $bogusId = '00000000-0000-0000-0000-000000000000';

        $response = $this->asUser($user)->getJson("/api/v1/students/{$bogusId}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'The requested record could not be found.']);
        $response->assertDontSeeText('App\\Models\\Student');
        $response->assertDontSeeText($bogusId);
    }

    public function test_unknown_route_returns_a_friendly_404(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('school_admin');

        $response = $this->asUser($user)->getJson('/api/v1/this-route-does-not-exist');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'The requested record could not be found.']);
    }

    public function test_denied_dashboard_access_returns_a_friendly_403(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $response = $this->asUser($teacher)->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have access to this dashboard.']);
    }
}
