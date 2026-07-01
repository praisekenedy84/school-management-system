<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\School;
use App\Models\Stream;
use App\Models\User;
use App\Services\Sis\StudentAdmissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class StudentFilterTest extends TestCase
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

    public function test_search_by_admission_number(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        app(StudentAdmissionService::class)->admit([
            'school_id' => $this->school->id,
            'admission_number' => 'ADM-FIND-001',
            'first_name' => 'Alice',
            'last_name' => 'Alpha',
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
            'residence_type' => 'day',
        ]);

        app(StudentAdmissionService::class)->admit([
            'school_id' => $this->school->id,
            'admission_number' => 'ADM-FIND-002',
            'first_name' => 'Bob',
            'last_name' => 'Beta',
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
            'residence_type' => 'boarding',
        ]);

        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/students?search=ADM-FIND-001'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.admission_number', 'ADM-FIND-001');
    }

    public function test_filter_by_class_and_stream(): void
    {
        $admin = $this->admin();
        $classA = ClassRoom::factory()->create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $classB = ClassRoom::factory()->create(['school_id' => $this->school->id, 'name' => 'Form 2']);
        $streamA = Stream::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $classA->id,
            'name' => 'A',
        ]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $studentInStream = app(StudentAdmissionService::class)->admit([
            'school_id' => $this->school->id,
            'admission_number' => 'ADM-STR-001',
            'first_name' => 'Stream',
            'last_name' => 'Student',
            'class_id' => $classA->id,
            'stream_id' => $streamA->id,
            'academic_session_id' => $session->id,
            'residence_type' => 'day',
        ]);

        app(StudentAdmissionService::class)->admit([
            'school_id' => $this->school->id,
            'admission_number' => 'ADM-STR-002',
            'first_name' => 'Other',
            'last_name' => 'Class',
            'class_id' => $classB->id,
            'academic_session_id' => $session->id,
            'residence_type' => 'day',
        ]);

        $this->actingAs($admin);

        $byStream = $this->getJson($this->tenantUrl("/api/v1/students?stream_id={$streamA->id}"));
        $byStream->assertOk();
        $byStream->assertJsonCount(1, 'data');
        $byStream->assertJsonPath('data.0.id', $studentInStream->id);

        $byClass = $this->getJson($this->tenantUrl("/api/v1/students?class_id={$classA->id}"));
        $byClass->assertOk();
        $byClass->assertJsonCount(1, 'data');
    }

    public function test_admit_with_stream_persists_stream_id_on_enrolment(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $stream = Stream::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $classRoom->id,
        ]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/students'), [
            'admission_number' => 'ADM-STR-003',
            'first_name' => 'Carol',
            'last_name' => 'Stream',
            'class_id' => $classRoom->id,
            'stream_id' => $stream->id,
            'academic_session_id' => $session->id,
            'residence_type' => 'day',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.current_enrolment.stream_id', $stream->id);

        $this->assertDatabaseHas('enrolments', [
            'stream_id' => $stream->id,
            'status' => 'active',
        ]);
    }
}
