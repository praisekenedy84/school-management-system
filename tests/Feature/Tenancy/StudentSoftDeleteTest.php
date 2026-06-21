<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Student;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * RULES.md §1 / §3: financial + critical records (students included, per
 * RULES.md §3 "Soft deletes on: students, all finance tables, …") are never
 * hard-deleted. Confirms Student::destroy() soft-deletes only.
 */
class StudentSoftDeleteTest extends TestCase
{
    use CreatesTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAndInitializeTenant();
    }

    protected function tearDown(): void
    {
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_destroy_soft_deletes_and_does_not_remove_the_row(): void
    {
        $student = Student::factory()->create();

        Student::destroy($student->id);

        $this->assertNull(Student::find($student->id));

        $trashed = Student::withTrashed()->find($student->id);

        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);

        // The row is genuinely still in the table, not gone.
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
        ]);
    }
}
