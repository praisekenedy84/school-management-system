<?php

declare(strict_types=1);

namespace Tests\Feature\Hostel;

use App\Models\AcademicSession;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * HostelAllocationController + HostelAllocationService. ADR-0008: tenant
 * resolution is session-based (InitializeTenancyFromSession), not
 * subdomain-based — withSession(['tenant_id' => ...]) + actingAs() is all
 * a test needs, no Host header/CSRF dance required for non-login requests.
 */
class HostelAllocationTest extends TestCase
{
    use CreatesTenant;

    private Tenant $tenant;

    private School $school;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->school = School::factory()->create();
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
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

    private function manager(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('hostel_manager');

        return $user;
    }

    public function test_export_returns_an_excel_file(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $response = $this->asUser($this->manager())->get('/api/v1/hostel-allocations/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_allocate_happy_path(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'female']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 2]);
        $student = Student::factory()->create(['school_id' => $this->school->id, 'gender' => 'female']);

        $response = $this->asUser($this->manager())->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('hostel_allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'status' => 'active',
        ]);
    }

    public function test_allocate_rejects_when_room_is_full(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 1]);

        HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $response = $this->asUser($this->manager())->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hostel_room_id']);
    }

    public function test_allocate_rejects_gender_mismatch(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'male']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 2]);
        $student = Student::factory()->create(['school_id' => $this->school->id, 'gender' => 'female']);

        $response = $this->asUser($this->manager())->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hostel_room_id']);
    }

    public function test_allocate_rejects_a_second_active_allocation_in_the_same_session(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $roomA = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 2]);
        $roomB = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 2]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'hostel_room_id' => $roomA->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $response = $this->asUser($this->manager())->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $roomB->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_id']);
    }

    public function test_role_without_permission_cannot_allocate(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $response = $this->asUser($teacher)->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_end_allocation_sets_status_ended_and_keeps_the_row(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $allocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $response = $this->asUser($this->manager())->postJson("/api/v1/hostel-allocations/{$allocation->id}/end");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'ended');
        $this->assertDatabaseHas('hostel_allocations', [
            'id' => $allocation->id,
            'status' => 'ended',
        ]);
    }

    /**
     * Opt-in fee-status gate (PRD §5.7), same pattern as the report-card
     * gate — defaults OFF (test_allocate_happy_path above proves an
     * outstanding balance doesn't matter when the gate is disabled).
     */
    public function test_allocate_blocked_by_fee_gate_when_balance_outstanding(): void
    {
        $this->school->update(['fee_terms' => ['hostel_gate_enabled' => true]]);

        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 500000,
            'total_paid' => 0,
        ]);

        $response = $this->asUser($this->manager())->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_id']);
    }

    public function test_allocate_allowed_by_fee_gate_when_balance_cleared(): void
    {
        $this->school->update(['fee_terms' => ['hostel_gate_enabled' => true]]);

        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 500000,
            'total_paid' => 500000,
        ]);

        $response = $this->asUser($this->manager())->postJson('/api/v1/hostel-allocations', [
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertCreated();
    }

    public function test_index_scopes_parent_to_own_wards_only(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 4]);

        $ward = Student::factory()->create(['school_id' => $this->school->id]);
        $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);

        $wardAllocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $ward->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);
        HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $otherStudent->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        $parent->wards()->attach($ward->id, ['relationship' => 'father', 'is_primary' => true]);

        $response = $this->asUser($parent)->getJson('/api/v1/hostel-allocations');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $wardAllocation->id);
    }
}
