<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\PaymentReceipt;
use App\Models\PaymentSlip;
use App\Models\PaymentSlipLog;
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

/**
 * End-to-end finance flow (PROJECT-PLAN Phase 3 exit criteria + RULES.md §9:
 * "submit -> verify -> receipt -> correct ledger balance"). QUEUE_CONNECTION
 * is sync in phpunit.xml so dispatched events/jobs run inline.
 *
 * Covers the invariants most likely to regress: allocation-sum == total,
 * duplicate-teller-per-bank-per-date rejection, ledger balance recompute via
 * the STORED GENERATED column (requires ->refresh()), receipt immutability /
 * verify idempotency, and parent ward-ownership on submit.
 */
class PaymentSlipFlowTest extends TestCase
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

        // Assessed fees for this class+session: 100,000 + 50,000 = 150,000.
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

    private function financeManager(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('finance_manager');

        return $user;
    }

    private function submitPayload(array $overrides = []): array
    {
        return array_merge([
            'student_id' => $this->student->id,
            'bank_name' => 'CRDB',
            'branch_name' => 'Main',
            'teller_number' => 'TLR-0001',
            'depositor_name' => 'Jane Doe',
            'deposit_date' => now()->subDay()->toDateString(),
            'total_amount' => 150000,
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => 100000, 'academic_session_id' => $this->session->id],
                ['fee_type' => 'Boarding', 'amount' => 50000, 'academic_session_id' => $this->session->id],
            ],
            'slip_attachments' => [UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf')],
        ], $overrides);
    }

    public function test_export_returns_an_excel_file(): void
    {
        PaymentSlip::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->financeManager());

        $response = $this->get($this->tenantUrl('/api/v1/payment-slips/export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    // ---- Happy path: submit -> verify -> receipt -> correct ledger balance -----------

    public function test_full_flow_produces_receipt_and_correct_ledger_balance(): void
    {
        $this->actingAs($this->parent);

        $submit = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload());
        $submit->assertStatus(201);
        $submit->assertJsonPath('data.status', 'pending');
        $slipId = $submit->json('data.id');

        $this->assertStringStartsWith('SLP-', $submit->json('data.slip_number'));
        // First audit log row written.
        $this->assertSame(1, PaymentSlipLog::where('payment_slip_id', $slipId)->count());
        $this->assertSame('submitted', PaymentSlipLog::where('payment_slip_id', $slipId)->first()->action);

        // Finance verifies.
        $finance = $this->financeManager();
        $this->actingAs($finance);

        $verify = $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/verify"), [
            'verification_notes' => 'Confirmed against bank statement.',
        ]);
        $verify->assertOk();
        $verify->assertJsonPath('data.status', 'verified');
        $receiptNumber = $verify->json('data.receipt_number');
        $this->assertStringStartsWith('RCP-', $receiptNumber);

        // Receipt + FeePayment rows exist.
        $this->assertSame(1, PaymentReceipt::where('payment_slip_id', $slipId)->count());
        $this->assertSame(2, FeePayment::where('payment_slip_id', $slipId)->count());

        // Ledger balance: assessed 150,000 - paid 150,000 = 0 -> fully_paid.
        $ledger = StudentFeeLedger::where('student_id', $this->student->id)
            ->where('academic_session_id', $this->session->id)
            ->first();
        $this->assertNotNull($ledger);
        $this->assertSame('150000.00', $ledger->total_assessed);
        $this->assertSame('150000.00', $ledger->total_paid);
        $this->assertSame('0.00', $ledger->balance);
        $this->assertSame('fully_paid', $ledger->payment_status);

        // Two audit rows now (submitted + verified).
        $this->assertSame(2, PaymentSlipLog::where('payment_slip_id', $slipId)->count());

        // Receipt PDF stored.
        Storage::assertExists(PaymentReceipt::where('payment_slip_id', $slipId)->first()->file_path);
    }

    public function test_partial_payment_yields_partially_paid_status(): void
    {
        $this->actingAs($this->parent);

        $payload = $this->submitPayload([
            'total_amount' => 60000,
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => 60000, 'academic_session_id' => $this->session->id],
            ],
        ]);
        $slipId = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $payload)->json('data.id');

        $this->actingAs($this->financeManager());
        $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/verify"), [
            'verification_notes' => 'Partial payment confirmed.',
        ])->assertOk();

        $ledger = StudentFeeLedger::where('student_id', $this->student->id)->first();
        $this->assertSame('150000.00', $ledger->total_assessed);
        $this->assertSame('60000.00', $ledger->total_paid);
        $this->assertSame('90000.00', $ledger->balance);
        $this->assertSame('partially_paid', $ledger->payment_status);
    }

    // ---- Idempotency: a verified slip cannot be re-verified ---------------------------

    public function test_double_verify_is_rejected_with_422_and_no_second_receipt(): void
    {
        $this->actingAs($this->parent);
        $slipId = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->json('data.id');

        $this->actingAs($this->financeManager());
        $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/verify"), [
            'verification_notes' => 'First verification.',
        ])->assertOk();

        $second = $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/verify"), [
            'verification_notes' => 'Second verification attempt.',
        ]);
        $second->assertStatus(422);

        $this->assertSame(1, PaymentReceipt::where('payment_slip_id', $slipId)->count());
        // Paid total must not have been double-counted.
        $ledger = StudentFeeLedger::where('student_id', $this->student->id)->first();
        $this->assertSame('150000.00', $ledger->total_paid);
    }

    // ---- Rejection: no ledger / no receipt -------------------------------------------

    public function test_rejected_slip_changes_no_ledger_and_issues_no_receipt(): void
    {
        $this->actingAs($this->parent);
        $slipId = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->json('data.id');

        $this->actingAs($this->financeManager());
        $reject = $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/reject"), [
            'rejection_category' => 'unclear_image',
            'rejection_reason' => 'The uploaded slip image is too blurry to read the teller number.',
        ]);
        $reject->assertOk();
        $reject->assertJsonPath('data.status', 'rejected');

        $this->assertSame(0, PaymentReceipt::where('payment_slip_id', $slipId)->count());
        $this->assertNull(StudentFeeLedger::where('student_id', $this->student->id)->first());
    }

    public function test_reject_without_reason_returns_422(): void
    {
        $this->actingAs($this->parent);
        $slipId = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->json('data.id');

        $this->actingAs($this->financeManager());
        $reject = $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/reject"), [
            'rejection_category' => 'unclear_image',
            'rejection_reason' => '',
        ]);
        $reject->assertStatus(422);
        $reject->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_zero_value_allocation_line_is_rejected(): void
    {
        $this->actingAs($this->parent);

        $payload = $this->submitPayload([
            'total_amount' => 200000,
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => 200000, 'academic_session_id' => $this->session->id],
                ['fee_type' => 'Hostel', 'amount' => 0, 'academic_session_id' => $this->session->id],
            ],
        ]);

        $response = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocation.1.amount']);
    }

    // ---- Allocation invariant --------------------------------------------------------

    public function test_allocation_must_sum_to_total(): void
    {
        $this->actingAs($this->parent);

        $payload = $this->submitPayload([
            'total_amount' => 150000,
            'allocation' => [
                ['fee_type' => 'Tuition', 'amount' => 100000, 'academic_session_id' => $this->session->id],
                ['fee_type' => 'Boarding', 'amount' => 40000, 'academic_session_id' => $this->session->id],
            ],
        ]);

        $response = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocation']);
        $this->assertSame(0, PaymentSlip::count());
    }

    // ---- Duplicate teller per bank per date ------------------------------------------

    public function test_duplicate_teller_per_bank_per_date_is_rejected(): void
    {
        $this->actingAs($this->parent);

        $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->assertStatus(201);

        // Same teller, same bank, same deposit date -> duplicate.
        $dup = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload([
            'slip_attachments' => [UploadedFile::fake()->create('slip2.pdf', 50, 'application/pdf')],
        ]));
        $dup->assertStatus(422);
        $dup->assertJsonValidationErrors(['teller_number']);
    }

    public function test_same_teller_different_date_is_allowed(): void
    {
        $this->actingAs($this->parent);

        $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->assertStatus(201);

        $other = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload([
            'deposit_date' => now()->subDays(3)->toDateString(),
            'slip_attachments' => [UploadedFile::fake()->create('slip3.pdf', 50, 'application/pdf')],
        ]));
        $other->assertStatus(201);
    }

    // ---- Parent ward ownership on submit ---------------------------------------------

    public function test_parent_cannot_submit_for_a_non_ward(): void
    {
        $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($this->parent);

        $response = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload([
            'student_id' => $otherStudent->id,
        ]));

        $response->assertStatus(403);
        $this->assertSame(0, PaymentSlip::count());
    }

    public function test_parent_index_only_returns_their_wards_slips(): void
    {
        // Parent's own ward slip.
        $this->actingAs($this->parent);
        $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->assertStatus(201);

        // A slip for another student, submitted by finance staff.
        $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);
        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $otherStudent->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);
        $finance = $this->financeManager();
        $this->actingAs($finance);
        $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload([
            'student_id' => $otherStudent->id,
            'teller_number' => 'TLR-9999',
            'slip_attachments' => [UploadedFile::fake()->create('other.pdf', 50, 'application/pdf')],
        ]))->assertStatus(201);

        // Parent sees only their ward's slip.
        $this->actingAs($this->parent);
        $index = $this->getJson($this->tenantUrl('/api/v1/payment-slips'));
        $index->assertOk();
        $index->assertJsonCount(1, 'data');
        $index->assertJsonPath('data.0.student_id', $this->student->id);

        // Finance sees both.
        $this->actingAs($finance);
        $financeIndex = $this->getJson($this->tenantUrl('/api/v1/payment-slips'));
        $financeIndex->assertJsonCount(2, 'data');
    }

    public function test_parent_cannot_verify(): void
    {
        $this->actingAs($this->parent);
        $slipId = $this->postJson($this->tenantUrl('/api/v1/payment-slips'), $this->submitPayload())->json('data.id');

        $verify = $this->postJson($this->tenantUrl("/api/v1/payment-slips/{$slipId}/verify"), [
            'verification_notes' => 'Trying to self-verify.',
        ]);
        $verify->assertStatus(403);
    }
}
