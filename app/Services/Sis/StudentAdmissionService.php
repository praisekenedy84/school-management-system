<?php

declare(strict_types=1);

namespace App\Services\Sis;

use App\Models\Enrolment;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admits a student and creates their first enrolment in one transaction.
 * RULES.md §1: students are append-only/soft-delete-only; this service only
 * ever inserts.
 */
class StudentAdmissionService
{
    public function admit(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $schoolId = $data['school_id'] ?? Auth::user()?->school_id;

            $student = Student::create([
                'school_id' => $schoolId,
                'admission_number' => $data['admission_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'residence_type' => $data['residence_type'],
                'status' => $data['status'] ?? 'active',
                'admitted_at' => $data['admitted_at'] ?? now()->toDateString(),
                'photo_path' => $data['photo_path'] ?? null,
            ]);

            Enrolment::create([
                'school_id' => $schoolId,
                'student_id' => $student->id,
                'class_id' => $data['class_id'],
                'academic_session_id' => $data['academic_session_id'],
                'residence_type' => $data['residence_type'],
                'status' => 'active',
                'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
            ]);

            return $student->load(['enrolments.classRoom', 'enrolments.academicSession', 'guardians']);
        });
    }
}
