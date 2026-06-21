<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * POST /api/v1/students — StudentAdmissionService::admit() creates a Student
 * AND its first Enrolment atomically (SKILLS Recipe A/B; RULES §1 + §8).
 *
 * Uses real HTTP through routes/tenant.php via the absolute-URL pattern
 * (see LoginTest's class docblock for why a 'Host' header doesn't work) and
 * actingAs() for the session guard — auth:sanctum resolves the 'web' guard
 * here (config('sanctum.guard')), exactly what actingAs() sets.
 */
class StudentAdmissionTest extends TestCase
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

    public function test_admitting_a_student_creates_student_and_first_enrolment_atomically(): void
    {
        $admin = $this->admin();
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($admin);

        $payload = [
            'admission_number' => 'ADM-0001',
            'first_name' => 'Asha',
            'last_name' => 'Mwakasege',
            'date_of_birth' => '2012-04-15',
            'gender' => 'female',
            'residence_type' => 'boarding',
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
            'enrolled_at' => '2026-01-10',
        ];

        $response = $this->postJson($this->tenantUrl('/api/v1/students'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.admission_number', 'ADM-0001');
        $response->assertJsonPath('data.residence_type', 'boarding');
        $response->assertJsonPath('data.current_enrolment.class_id', $classRoom->id);
        $response->assertJsonPath('data.current_enrolment.academic_session_id', $session->id);
        $response->assertJsonPath('data.current_enrolment.residence_type', 'boarding');

        $this->assertDatabaseHas('students', [
            'admission_number' => 'ADM-0001',
            'first_name' => 'Asha',
            'last_name' => 'Mwakasege',
            'residence_type' => 'boarding',
            'school_id' => $this->school->id,
        ]);

        $student = Student::where('admission_number', 'ADM-0001')->first();
        $this->assertNotNull($student);

        $this->assertDatabaseHas('enrolments', [
            'student_id' => $student->id,
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
            'residence_type' => 'boarding',
            'status' => 'active',
            'school_id' => $this->school->id,
        ]);

        $this->assertSame(1, Enrolment::where('student_id', $student->id)->count());
    }

    public function test_admission_fails_validation_with_missing_required_fields(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/students'), [
            'first_name' => 'Asha',
            // missing: admission_number, last_name, residence_type, class_id, academic_session_id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'admission_number',
            'last_name',
            'residence_type',
            'class_id',
            'academic_session_id',
        ]);

        $this->assertSame(0, Student::count());
    }

    public function test_admission_rejects_invalid_class_and_session_references(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl('/api/v1/students'), [
            'admission_number' => 'ADM-0002',
            'first_name' => 'Juma',
            'last_name' => 'Kessy',
            'residence_type' => 'day',
            'class_id' => (string) Str::uuid(),
            'academic_session_id' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id', 'academic_session_id']);
    }

    public function test_role_without_create_permission_is_forbidden(): void
    {
        // 'parent' may view students (StudentPolicy::viewAny === true) but
        // not create (StudentPolicy::create requires school_admin/tenant_admin).
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');

        $this->actingAs($parent);

        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $response = $this->postJson($this->tenantUrl('/api/v1/students'), [
            'admission_number' => 'ADM-0003',
            'first_name' => 'Furaha',
            'last_name' => 'Lyimo',
            'residence_type' => 'day',
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
        ]);

        // AdmitStudentRequest::authorize() returns false -> FormRequest
        // throws AuthorizationException -> 403.
        $response->assertStatus(403);
        $this->assertSame(0, Student::count());
    }

    public function test_index_is_paginated_and_school_scoped(): void
    {
        $otherSchool = School::factory()->create();

        Student::factory()->count(3)->create(['school_id' => $this->school->id]);
        Student::factory()->count(2)->create(['school_id' => $otherSchool->id]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl('/api/v1/students?per_page=2'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 3);

        foreach ($response->json('data') as $row) {
            $this->assertSame($this->school->id, $row['school_id']);
        }
    }

    public function test_show_includes_enrolment_and_guardians(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $classRoom = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $session = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $classRoom->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $guardian = User::factory()->create(['school_id' => $this->school->id]);
        $guardian->assignRole('parent');
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $response = $this->getJson($this->tenantUrl("/api/v1/students/{$student->id}"));

        $response->assertOk();
        $response->assertJsonPath('data.id', $student->id);
        $response->assertJsonCount(1, 'data.enrolments');
        $response->assertJsonPath('data.enrolments.0.class_id', $classRoom->id);
        $response->assertJsonCount(1, 'data.guardians');
        $response->assertJsonPath('data.guardians.0.id', $guardian->id);
    }
}
