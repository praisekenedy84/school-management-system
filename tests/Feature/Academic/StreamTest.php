<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Models\ClassRoom;
use App\Models\School;
use App\Models\Stream;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class StreamTest extends TestCase
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

    public function test_create_and_list_streams_for_a_class(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $create = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/streams"), [
            'name' => 'Form 1A',
            'capacity' => 40,
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Form 1A');

        $list = $this->getJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/streams"));
        $list->assertOk();
        $list->assertJsonCount(1, 'data');
    }

    public function test_deactivate_stream_sets_is_active_false(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $stream = Stream::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $classRoom->id,
            'name' => 'Blue',
        ]);

        $this->actingAs($admin);

        $response = $this->deleteJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/streams/{$stream->id}"));

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('streams', [
            'id' => $stream->id,
            'is_active' => false,
        ]);
    }

    public function test_duplicate_stream_name_within_class_is_rejected(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        Stream::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $classRoom->id,
            'name' => 'Green',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/classes/{$classRoom->id}/streams"), [
            'name' => 'Green',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }
}
