<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Services\Sis\StudentAdmissionService;
use App\Support\Import\ImportResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Bulk student admission import (PRD §5.2: "bulk CSV/Excel import" —
 * deferred in Phase 1, built now). Columns: admission_number, first_name,
 * last_name, date_of_birth, gender, residence_type, class, academic_session.
 * `class`/`academic_session` are names, not UUIDs — resolved per row,
 * scoped to the one school this whole batch is being admitted into (same
 * single-school-per-import rule as SubjectsImport/ClassesImport). Reuses
 * StudentAdmissionService so an imported row is admitted exactly the same
 * way (atomic Student + Enrolment) as one entered through the form.
 */
class StudentsImport implements ToCollection, WithHeadingRow
{
    private readonly ImportResult $result;

    public function __construct(
        private readonly StudentAdmissionService $admissionService,
        private readonly string $schoolId,
    ) {
        $this->result = new ImportResult;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $this->importRow($row, $rowNumber);
            } catch (Throwable $e) {
                $this->result->recordError($rowNumber, $e->getMessage());
            }
        }
    }

    private function importRow(Collection $row, int $rowNumber): void
    {
        $admissionNumber = trim((string) ($row['admission_number'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $className = trim((string) ($row['class'] ?? ''));
        $sessionName = trim((string) ($row['academic_session'] ?? ''));
        $residenceType = strtolower(trim((string) ($row['residence_type'] ?? 'day')));
        $gender = trim((string) ($row['gender'] ?? ''));

        if ($admissionNumber === '' || $firstName === '' || $lastName === '') {
            $this->result->recordError($rowNumber, 'admission_number, first_name, and last_name are required.');

            return;
        }

        if (! in_array($residenceType, ['day', 'boarding'], true)) {
            $this->result->recordError($rowNumber, 'residence_type must be "day" or "boarding".');

            return;
        }

        if (Student::where('school_id', $this->schoolId)->where('admission_number', $admissionNumber)->exists()) {
            $this->result->recordError($rowNumber, "Admission number \"{$admissionNumber}\" is already in use.");

            return;
        }

        $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $this->schoolId)
            ->where('name', $className)
            ->first();

        if ($classRoom === null) {
            $this->result->recordError($rowNumber, "Class \"{$className}\" was not found for this school.");

            return;
        }

        $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $this->schoolId)
            ->where('name', $sessionName)
            ->first();

        if ($academicSession === null) {
            $this->result->recordError($rowNumber, "Academic session \"{$sessionName}\" was not found for this school.");

            return;
        }

        $this->admissionService->admit([
            'school_id' => $this->schoolId,
            'admission_number' => $admissionNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $this->normalizeDate($row['date_of_birth'] ?? null),
            'gender' => $gender !== '' ? strtolower($gender) : null,
            'residence_type' => $residenceType,
            'class_id' => $classRoom->id,
            'academic_session_id' => $academicSession->id,
        ]);

        $this->result->recordCreated();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    public function result(): ImportResult
    {
        return $this->result;
    }
}
