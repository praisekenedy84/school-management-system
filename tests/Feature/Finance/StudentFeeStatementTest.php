<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\PaymentSlip;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\StudentGuardian;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class StudentFeeStatementTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private AcademicSession $session;

    private ClassRoom $classRoom;

    private Student $student;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake();

        $this->school = School::factory()->create();
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $this->classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $this->student = Student::factory()->create(['school_id' => $this->school->id]);

        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        FeeStructure::factory()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->classRoom->id,
            'fee_type' => 'Tuition',
            'amount' => 100000,
            'applicable_to' => 'all',
            'is_active' => true,
        ]);
        FeeStructure::factory()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->classRoom->id,
            'fee_type' => 'Boarding',
            'amount' => 50000,
            'applicable_to' => 'all',
            'is_active' => true,
        ]);

        $this->parent = User::factory()->create(['school_id' => $this->school->id]);
        $this->parent->assignRole('parent');
        StudentGuardian::factory()->create([
            'student_id' => $this->student->id,
            'guardian_id' => $this->parent->id,
        ]);
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

    public function test_parent_can_view_fee_statement_with_per_item_breakdown(): void
    {
        $slip = PaymentSlip::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'status' => 'verified',
        ]);

        FeePayment::factory()->create([
            'school_id' => $this->school->id,
            'payment_slip_id' => $slip->id,
            'fee_type' => 'Tuition',
            'amount' => 60000,
            'academic_session_id' => $this->session->id,
        ]);

        $this->actingAs($this->parent);

        $response = $this->getJson($this->tenantUrl(
            "/api/v1/students/{$this->student->id}/fee-statement?academic_session_id={$this->session->id}"
        ));

        $response->assertOk();
        $response->assertJsonPath('data.totals.total_charged', '150000.00');
        $response->assertJsonPath('data.totals.total_paid', '60000.00');
        $response->assertJsonPath('data.totals.balance', '90000.00');
        $response->assertJsonFragment(['fee_type' => 'Tuition', 'total_paid' => '60000.00', 'balance' => '40000.00']);
        $response->assertJsonFragment(['fee_type' => 'Boarding', 'total_paid' => '0.00', 'balance' => '50000.00']);
    }

    public function test_submit_rejects_unknown_fee_type(): void
    {
        $this->actingAs($this->parent);

        $response = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), [
            'student_id' => $this->student->id,
            'depositor_name' => 'Jane Doe',
            'deposit_date' => now()->subDay()->toDateString(),
            'total_amount' => 10000,
            'allocation' => [
                ['fee_type' => 'misc', 'amount' => 10000, 'academic_session_id' => $this->session->id],
            ],
            'slip_attachments' => [UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf')],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocation.0.fee_type']);
    }

    public function test_submit_rejects_duplicate_fee_type_on_same_slip(): void
    {
        $this->actingAs($this->parent);

        $response = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), [
            'student_id' => $this->student->id,
            'depositor_name' => 'Jane Doe',
            'deposit_date' => now()->subDay()->toDateString(),
            'total_amount' => 20000,
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => 10000, 'academic_session_id' => $this->session->id],
                ['fee_type' => 'Tuition', 'amount' => 10000, 'academic_session_id' => $this->session->id],
            ],
            'slip_attachments' => [UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf')],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocation.1.fee_type']);
    }

    public function test_verification_updates_fee_details_per_item(): void
    {
        $this->actingAs($this->parent);

        $slipId = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), [
            'student_id' => $this->student->id,
            'depositor_name' => 'Jane Doe',
            'deposit_date' => now()->subDay()->toDateString(),
            'total_amount' => 60000,
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => 60000, 'academic_session_id' => $this->session->id],
            ],
            'slip_attachments' => [UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf')],
        ])->json('data.id');

        $finance = User::factory()->create(['school_id' => $this->school->id]);
        $finance->assignRole('finance_manager');
        $this->actingAs($finance);

        $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/verify"), [
            'verification_notes' => 'Confirmed.',
        ])->assertOk();

        $ledger = StudentFeeLedger::where('student_id', $this->student->id)->first();
        $this->assertNotNull($ledger);

        $tuition = collect($ledger->fee_details)->firstWhere('fee_type', 'Tuition');
        $this->assertSame('60000.00', $tuition['total_paid']);
        $this->assertSame('40000.00', $tuition['balance']);
    }
}
