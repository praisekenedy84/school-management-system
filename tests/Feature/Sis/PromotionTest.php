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
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * POST /api/v1/enrolments/{enrolment}/promote — PromotionService::promote().
 * RULES §1: append-only — the OLD enrolment is never deleted, only its
 * `status` flips to 'promoted'; a brand NEW row carries the new class/session.
 */
class PromotionTest extends TestCase
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

    public function test_promoting_creates_exactly_two_enrolment_rows_old_flipped_new_active(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id, 'residence_type' => 'day']);

        $oldClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $oldSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2024/2025']);
        $newClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $newSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2025/2026']);

        $oldEnrolment = Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $oldClass->id,
            'academic_session_id' => $oldSession->id,
            'residence_type' => 'day',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/enrolments/{$oldEnrolment->id}/promote"), [
            'class_id' => $newClass->id,
            'academic_session_id' => $newSession->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.class_id', $newClass->id);
        $response->assertJsonPath('data.academic_session_id', $newSession->id);
        $response->assertJsonPath('data.status', 'active');

        $this->assertSame(2, Enrolment::where('student_id', $student->id)->count());

        $oldEnrolment->refresh();
        $this->assertSame('promoted', $oldEnrolment->status);
        $this->assertSame($oldClass->id, $oldEnrolment->class_id);
        $this->assertSame($oldSession->id, $oldEnrolment->academic_session_id);

        $newEnrolment = Enrolment::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($newEnrolment);
        $this->assertSame($newClass->id, $newEnrolment->class_id);
        $this->assertSame($newSession->id, $newEnrolment->academic_session_id);
        $this->assertNotSame($oldEnrolment->id, $newEnrolment->id);
    }

    public function test_residence_type_carries_over_when_omitted(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $newClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $oldSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2024/2025']);
        $newSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2025/2026']);

        $oldEnrolment = Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_session_id' => $oldSession->id,
            'residence_type' => 'boarding',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/enrolments/{$oldEnrolment->id}/promote"), [
            'class_id' => $newClass->id,
            'academic_session_id' => $newSession->id,
            // residence_type omitted
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.residence_type', 'boarding');
    }

    public function test_residence_type_overrides_when_provided(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $newClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $oldSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2024/2025']);
        $newSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2025/2026']);

        $oldEnrolment = Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_session_id' => $oldSession->id,
            'residence_type' => 'day',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/enrolments/{$oldEnrolment->id}/promote"), [
            'class_id' => $newClass->id,
            'academic_session_id' => $newSession->id,
            'residence_type' => 'boarding',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.residence_type', 'boarding');
    }

    /**
     * `enrolments` has a UNIQUE(student_id, academic_session_id) DB
     * constraint. Promoting into a session the student is ALREADY enrolled
     * in (e.g. promoting twice into the same target session, or any session
     * with an existing row) must surface as a clean error response, not an
     * unhandled 500 QueryException.
     */
    public function test_promoting_into_an_already_enrolled_session_fails_cleanly_not_500(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $oldClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $oldSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2024/2025']);
        $existingSession = AcademicSession::factory()->create(['school_id' => $this->school->id, 'name' => '2025/2026']);
        $newClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $oldEnrolment = Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $oldClass->id,
            'academic_session_id' => $oldSession->id,
            'status' => 'active',
        ]);

        // Student already has a (different) enrolment row in $existingSession.
        Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $newClass->id,
            'academic_session_id' => $existingSession->id,
            'status' => 'completed',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/enrolments/{$oldEnrolment->id}/promote"), [
            'class_id' => $newClass->id,
            'academic_session_id' => $existingSession->id,
        ]);

        // PromoteEnrolmentRequest validates this up front (Rule::unique on
        // enrolments scoped to student_id) instead of letting the DB's
        // UNIQUE(student_id, academic_session_id) constraint throw.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['academic_session_id']);
    }

    public function test_role_without_update_permission_cannot_promote(): void
    {
        $parent = User::factory()->create(['school_id' => $this->school->id]);
        $parent->assignRole('parent');

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $newClass = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $newSession = AcademicSession::factory()->create(['school_id' => $this->school->id]);

        $oldEnrolment = Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $this->actingAs($parent);

        $response = $this->postJson($this->tenantUrl("/api/v1/enrolments/{$oldEnrolment->id}/promote"), [
            'class_id' => $newClass->id,
            'academic_session_id' => $newSession->id,
        ]);

        $response->assertStatus(403);
        $this->assertSame(1, Enrolment::where('student_id', $student->id)->count());
    }

    public function test_promotion_fails_validation_with_missing_required_fields(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $oldEnrolment = Enrolment::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $response = $this->postJson($this->tenantUrl("/api/v1/enrolments/{$oldEnrolment->id}/promote"), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id', 'academic_session_id']);
    }
}
