<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Jobs\GenerateReportCardPdf;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\ReportCard;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Optional fee-status gate on report cards (PRD §5.5 / PROJECT-PLAN Phase 3
 * hook). The gate is OPT-IN per school via
 * School.fee_terms->results_gate_enabled and defaults OFF, so Phase 2's
 * existing ReportCardTest is unaffected. When ON and the student's ledger
 * balance is outstanding, the PDF is withheld: a ReportCard row is recorded
 * with a withheld_reason and no file_path, and the show endpoint returns 403
 * with the reason rather than a 404 or a null path.
 */
class ReportCardFeeGateTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private AcademicSession $session;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createAndInitializeTenant();
        $this->tenantId = $tenant->getTenantKey();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake();
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

    private function setUpFixtures(array $feeTerms): void
    {
        $this->school = School::factory()->create(['fee_terms' => $feeTerms]);
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $this->student = Student::factory()->create(['school_id' => $this->school->id]);

        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $assessment = Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $this->session->id,
        ]);
        ResultRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $this->session->id,
            'assessment_id' => $assessment->id,
            'score' => 80,
            'version' => 1,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('school_admin');

        return $user;
    }

    public function test_gate_off_generates_pdf_even_with_outstanding_balance(): void
    {
        $this->setUpFixtures(['results_gate_enabled' => false]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 100000,
            'total_paid' => 0,
        ]);

        GenerateReportCardPdf::dispatch($this->student->id, $this->session->id, $this->admin()->id, $this->tenantId);

        $card = ReportCard::where('student_id', $this->student->id)->first();
        $this->assertNotNull($card);
        $this->assertNull($card->withheld_reason);
        $this->assertNotNull($card->file_path);
        Storage::assertExists($card->file_path);
    }

    public function test_gate_on_with_outstanding_balance_withholds_pdf(): void
    {
        $this->setUpFixtures(['results_gate_enabled' => true]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 100000,
            'total_paid' => 30000,
        ]);

        GenerateReportCardPdf::dispatch($this->student->id, $this->session->id, $this->admin()->id, $this->tenantId);

        $card = ReportCard::where('student_id', $this->student->id)->first();
        $this->assertNotNull($card);
        $this->assertNotNull($card->withheld_reason);
        $this->assertNull($card->file_path);

        // Staff can see the withheld status internally (200 + withheld flag).
        $admin = $this->admin();
        $this->actingAs($admin);
        $response = $this->getJson($this->tenantUrl(
            "/api/v1/students/{$this->student->id}/report-card?academic_session_id={$this->session->id}"
        ));
        $response->assertOk();
        $response->assertJsonPath('data.withheld', true);
        $response->assertJsonPath('data.withheld_reason', $card->withheld_reason);
    }

    public function test_gate_on_but_balance_below_threshold_generates_pdf(): void
    {
        $this->setUpFixtures([
            'results_gate_enabled' => true,
            'results_gate_threshold' => 50000,
        ]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 100000,
            'total_paid' => 60000, // balance 40000 — below 50000 threshold
        ]);

        GenerateReportCardPdf::dispatch($this->student->id, $this->session->id, $this->admin()->id, $this->tenantId);

        $card = ReportCard::where('student_id', $this->student->id)->first();
        $this->assertNull($card->withheld_reason);
        $this->assertNotNull($card->file_path);
    }

    public function test_gate_on_with_balance_above_threshold_withholds_pdf(): void
    {
        $this->setUpFixtures([
            'results_gate_enabled' => true,
            'results_gate_threshold' => 50000,
        ]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 100000,
            'total_paid' => 40000, // balance 60000 — above threshold
        ]);

        GenerateReportCardPdf::dispatch($this->student->id, $this->session->id, $this->admin()->id, $this->tenantId);

        $card = ReportCard::where('student_id', $this->student->id)->first();
        $this->assertNotNull($card->withheld_reason);
        $this->assertNull($card->file_path);
    }

    public function test_parent_show_returns_403_when_withheld(): void
    {
        $this->setUpFixtures(['results_gate_enabled' => true]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 100000,
            'total_paid' => 0,
        ]);

        GenerateReportCardPdf::dispatch($this->student->id, $this->session->id, $this->admin()->id, $this->tenantId);

        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');
        $this->student->guardians()->attach($parent->id, ['relationship' => 'father', 'is_primary' => true]);

        $this->actingAs($parent);
        $response = $this->getJson($this->tenantUrl(
            "/api/v1/students/{$this->student->id}/report-card?academic_session_id={$this->session->id}"
        ));
        $response->assertStatus(403);
        $response->assertJsonPath('withheld', true);
    }

    public function test_gate_on_but_balance_cleared_generates_pdf(): void
    {
        $this->setUpFixtures(['results_gate_enabled' => true]);

        StudentFeeLedger::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'total_assessed' => 100000,
            'total_paid' => 100000,
        ]);

        GenerateReportCardPdf::dispatch($this->student->id, $this->session->id, $this->admin()->id, $this->tenantId);

        $card = ReportCard::where('student_id', $this->student->id)->first();
        $this->assertNull($card->withheld_reason);
        $this->assertNotNull($card->file_path);
    }
}
