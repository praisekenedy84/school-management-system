<?php

declare(strict_types=1);

namespace Tests\Feature\Assessment;

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\ReportCard;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

class ReportCardBulkTest extends TestCase
{
    use CreatesTenant;

    private string $tenantId;

    private School $school;

    private AcademicSession $session;

    private ClassRoom $classRoom;

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

    private function seedPublishedResult(Student $student): void
    {
        $subject = Subject::factory()->create(['school_id' => $this->school->id]);
        $assessment = Assessment::factory()->create([
            'school_id' => $this->school->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $this->session->id,
        ]);

        ResultRecord::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $this->session->id,
            'assessment_id' => $assessment->id,
            'score' => 75,
            'version' => 1,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_bulk_generation_produces_combined_pdf_for_class(): void
    {
        $students = Student::factory()->count(3)->create(['school_id' => $this->school->id]);

        foreach ($students as $student) {
            Enrolment::factory()->create([
                'school_id' => $this->school->id,
                'student_id' => $student->id,
                'class_id' => $this->classRoom->id,
                'academic_session_id' => $this->session->id,
                'status' => 'active',
            ]);
            $this->seedPublishedResult($student);
        }

        $this->actingAs($this->admin());

        $response = $this->postJson($this->tenantUrl('/api/v1/report-cards/bulk'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('included_count', 3);
        $response->assertJsonPath('excluded_students', []);

        $this->assertSame(3, ReportCard::count());

        $filePath = $response->json('file_path');
        $this->assertNotNull($filePath);
        Storage::assertExists($filePath);

        $pdfContent = Storage::get($filePath);
        $this->assertStringStartsWith('%PDF', $pdfContent);

        $download = $this->get($this->tenantUrl(
            "/api/v1/report-cards/class-download?class_id={$this->classRoom->id}&academic_session_id={$this->session->id}"
        ));
        $download->assertOk();
        $download->assertHeader('content-type', 'application/pdf');
    }

    public function test_bulk_generation_rejects_when_no_published_results(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin());

        $response = $this->postJson($this->tenantUrl('/api/v1/report-cards/bulk'), [
            'class_id' => $this->classRoom->id,
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
    }

    public function test_single_student_store_requires_published_results(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->actingAs($this->admin());

        $response = $this->postJson($this->tenantUrl("/api/v1/students/{$student->id}/report-card"), [
            'academic_session_id' => $this->session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Results must be published before report cards can be generated.');
    }
}
