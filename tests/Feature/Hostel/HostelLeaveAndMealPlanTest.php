<?php

declare(strict_types=1);

namespace Tests\Feature\Hostel;

use App\Models\AcademicSession;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelLeaveRequest;
use App\Models\HostelRoom;
use App\Models\MealPlan;
use App\Models\School;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class HostelLeaveAndMealPlanTest extends TestCase
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

    private function manager(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('hostel_manager');

        return $user;
    }

    public function test_meal_plans_export_returns_an_excel_file(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
        MealPlan::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);

        $response = $this->asUser($this->manager())->get('/api/v1/meal-plans/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_hostel_leave_requests_export_returns_an_excel_file(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $allocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);

        HostelLeaveRequest::factory()->create([
            'school_id' => $this->school->id,
            'hostel_allocation_id' => $allocation->id,
        ]);

        $response = $this->asUser($this->manager())->get('/api/v1/hostel-leave-requests/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_hostel_manager_can_create_a_meal_plan(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);

        $response = $this->asUser($this->manager())->postJson('/api/v1/meal-plans', [
            'hostel_id' => $hostel->id,
            'name' => 'Standard Meals',
            'price' => 50000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('meal_plans', ['hostel_id' => $hostel->id, 'name' => 'Standard Meals']);
    }

    /**
     * Regression: MealPlanRequest used to rely entirely on BelongsToSchool's
     * `auth()->user()->school_id` stamp, which is null for a tenant-wide
     * admin — violating meal_plans.school_id's NOT NULL constraint. Now
     * derived from hostel_id instead, so it works for every role.
     */
    public function test_tenant_admin_can_create_a_meal_plan_for_any_school(): void
    {
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);

        $tenantAdmin = User::factory()->withoutSchool()->create();
        $tenantAdmin->assignRole('tenant_admin');

        $response = $this->asUser($tenantAdmin)->postJson('/api/v1/meal-plans', [
            'hostel_id' => $hostel->id,
            'name' => 'Standard Meals',
            'price' => 50000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('meal_plans', [
            'hostel_id' => $hostel->id,
            'school_id' => $this->school->id,
        ]);
    }

    public function test_school_admin_cannot_create_a_meal_plan_for_another_schools_hostel(): void
    {
        $otherSchool = School::factory()->create();
        $otherHostel = Hostel::factory()->create(['school_id' => $otherSchool->id]);

        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $response = $this->asUser($admin)->postJson('/api/v1/meal-plans', [
            'hostel_id' => $otherHostel->id,
            'name' => 'Standard Meals',
            'price' => 50000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hostel_id']);
    }

    public function test_parent_can_request_leave_for_their_own_ward(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $allocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        $student->guardians()->attach($parent->id, ['relationship' => 'father', 'is_primary' => true]);

        $response = $this->asUser($parent)->postJson('/api/v1/hostel-leave-requests', [
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Family event over the weekend',
            'depart_at' => now()->addDays(2)->toDateString(),
            'return_at' => now()->addDays(4)->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_parent_cannot_request_leave_for_a_non_ward(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $allocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        // Not linked as a guardian of $student.

        $response = $this->asUser($parent)->postJson('/api/v1/hostel-leave-requests', [
            'hostel_allocation_id' => $allocation->id,
            'reason' => 'Family event over the weekend',
            'depart_at' => now()->addDays(2)->toDateString(),
            'return_at' => now()->addDays(4)->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_hostel_manager_can_approve_a_leave_request(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $allocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);

        $leaveRequest = HostelLeaveRequest::factory()->create([
            'school_id' => $this->school->id,
            'hostel_allocation_id' => $allocation->id,
        ]);

        $response = $this->asUser($this->manager())
            ->postJson("/api/v1/hostel-leave-requests/{$leaveRequest->id}/approve", [
                'decision_notes' => 'Approved, enjoy the trip.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
    }

    public function test_index_scopes_parent_to_own_wards_only(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id, 'capacity' => 4]);

        $ward = Student::factory()->create(['school_id' => $this->school->id]);
        $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);

        $wardAllocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $ward->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);
        $otherAllocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $otherStudent->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);

        $wardLeaveRequest = HostelLeaveRequest::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $ward->id,
            'hostel_allocation_id' => $wardAllocation->id,
        ]);
        HostelLeaveRequest::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $otherStudent->id,
            'hostel_allocation_id' => $otherAllocation->id,
        ]);

        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        $parent->wards()->attach($ward->id, ['relationship' => 'mother', 'is_primary' => true]);

        $response = $this->asUser($parent)->getJson('/api/v1/hostel-leave-requests');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $wardLeaveRequest->id);
    }

    public function test_approving_an_already_decided_request_fails_cleanly(): void
    {
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $hostel = Hostel::factory()->create(['school_id' => $this->school->id, 'gender' => 'mixed']);
        $room = HostelRoom::factory()->create(['school_id' => $this->school->id, 'hostel_id' => $hostel->id]);
        $allocation = HostelAllocation::factory()->create([
            'school_id' => $this->school->id,
            'hostel_room_id' => $room->id,
            'academic_session_id' => $session->id,
        ]);

        $leaveRequest = HostelLeaveRequest::factory()->create([
            'school_id' => $this->school->id,
            'hostel_allocation_id' => $allocation->id,
            'status' => 'approved',
        ]);

        $response = $this->asUser($this->manager())
            ->postJson("/api/v1/hostel-leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(422);
    }
}
