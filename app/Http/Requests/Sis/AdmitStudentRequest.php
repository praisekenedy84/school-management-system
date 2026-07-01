<?php

declare(strict_types=1);

namespace App\Http\Requests\Sis;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Scopes\SchoolScope;
use App\Models\Stream;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdmitStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'admission_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'transferred', 'graduated', 'withdrawn'])],
            'admitted_at' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string', 'max:500'],

            // First enrolment, created in the same transaction.
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'stream_id' => ['nullable', 'uuid', Rule::exists('streams', 'id')],
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
            'residence_type' => ['required', 'string', Rule::in(['day', 'boarding'])],
            'enrolled_at' => ['nullable', 'date'],
        ];
    }

    /**
     * The student's school_id comes from the acting user (StudentAdmissionService
     * falls back to Auth::user()->school_id), but `class_id`/`academic_session_id`
     * are only existence-checked, not school-checked — a school_admin could
     * otherwise admit a student into a class belonging to a DIFFERENT campus.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['class_id', 'academic_session_id', 'stream_id'])) {
                return;
            }

            $actingSchoolId = $this->user()?->school_id;

            if ($actingSchoolId === null) {
                // tenant_admin: no single campus to enforce against here.
                return;
            }

            // Bypass BelongsToSchool: must see the TRUE record even if it
            // belongs to a different campus than the acting user, or a
            // cross-school id would silently resolve to null and skip this check.
            $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)->find($this->input('class_id'));
            $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->find($this->input('academic_session_id'));
            $stream = $this->filled('stream_id')
                ? Stream::withoutGlobalScope(SchoolScope::class)->find($this->input('stream_id'))
                : null;

            if ($classRoom?->school_id !== $actingSchoolId || $academicSession?->school_id !== $actingSchoolId) {
                $validator->errors()->add('class_id', 'The class and academic session must belong to your school.');
            }

            if ($stream !== null) {
                if ($stream->school_id !== $actingSchoolId) {
                    $validator->errors()->add('stream_id', 'The stream must belong to your school.');
                } elseif ($stream->class_id !== $classRoom?->id) {
                    $validator->errors()->add('stream_id', 'The stream must belong to the selected class.');
                } elseif (! $stream->is_active) {
                    $validator->errors()->add('stream_id', 'The selected stream is not active.');
                }
            }
        });
    }
}
