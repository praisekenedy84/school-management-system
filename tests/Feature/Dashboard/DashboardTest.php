<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\InventoryItem;
use App\Models\PaymentSlip;
use App\Models\School;
use App\Models\StoreRequisition;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\StudentGuardian;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * DashboardController — read-only cross-module summaries (PRD §5.9/§5.10).
 * ADR-0008 session-based tenancy: withSession(['tenant_id' => ...]) + actingAs().
 */
class DashboardTest extends TestCase
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

    public function test_staff_summary_reports_counts_across_modules(): void
    {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('school_admin');

        $session = AcademicSession::factory()->create(['school_id' => $this->school->id, 'is_current' => true]);

        $student = Student::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        AttendanceRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);
        PaymentSlip::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'status' => 'pending']);

        $response = $this->asUser($admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('data.active_students', 1);
        $response->assertJsonPath('data.attendance_today.present', 1);
        $response->assertJsonPath('data.payment_slips.pending', 1);
        $response->assertJsonPath('data.current_academic_session', $session->name);
    }

    public function test_summary_rejects_roles_without_dashboard_access(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $response = $this->asUser($teacher)->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(403);
    }

    public function test_storekeeper_summary_only_includes_permitted_sections(): void
    {
        $keeper = User::factory()->create(['school_id' => $this->school->id]);
        $keeper->assignRole('storekeeper');

        InventoryItem::factory()->create([
            'school_id' => $this->school->id,
            'current_quantity' => '5.000',
            'reorder_level' => '10.000',
            'is_active' => true,
            'created_by' => $keeper->id,
        ]);

        StoreRequisition::factory()->create([
            'school_id' => $this->school->id,
            'requested_by' => $keeper->id,
            'status' => 'submitted',
        ]);

        $response = $this->asUser($keeper)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('data.stores.low_stock_items', 1);
        $response->assertJsonPath('data.stores.pending_requisitions', 1);
        $response->assertJsonMissingPath('data.active_students');
        $response->assertJsonMissingPath('data.payment_slips');
    }

    public function test_parent_wards_summary_returns_only_own_children(): void
    {
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');

        $ownChild = Student::factory()->create(['school_id' => $this->school->id]);
        $otherChild = Student::factory()->create(['school_id' => $this->school->id]);

        StudentGuardian::factory()->create([
            'student_id' => $ownChild->id,
            'guardian_id' => $parent->id,
            'is_primary' => true,
        ]);

        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $ownChild->id,
            'academic_session_id' => $session->id,
            'total_assessed' => 500000,
            'total_paid' => 200000,
        ]);

        $response = $this->asUser($parent)->getJson('/api/v1/dashboard/wards');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.student_id', $ownChild->id);
        $response->assertJsonPath('data.0.fee_balance', 300000);
        $response->assertJsonMissing(['data.0.student_id' => $otherChild->id]);
    }

    public function test_parent_wards_requires_view_own_children_permission(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $this->asUser($teacher)->getJson('/api/v1/dashboard/wards')->assertForbidden();
    }

    public function test_direct_permission_grant_unlocks_a_dashboard_widget(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');
        $teacher->givePermissionTo('finance.verify_slips');

        PaymentSlip::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => Student::factory()->create(['school_id' => $this->school->id])->id,
            'status' => 'pending',
        ]);

        $response = $this->asUser($teacher)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('data.payment_slips.pending', 1);
        $response->assertJsonMissingPath('data.active_students');
    }
}
