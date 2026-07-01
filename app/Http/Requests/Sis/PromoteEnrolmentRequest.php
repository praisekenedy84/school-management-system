<?php

declare(strict_types=1);

namespace App\Http\Requests\Sis;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\Scopes\SchoolScope;
use App\Models\Stream;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromoteEnrolmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Enrolment $enrolment */
        $enrolment = $this->route('enrolment');

        return $this->user()?->can('update', $enrolment) ?? false;
    }

    public function rules(): array
    {
        /** @var Enrolment $enrolment */
        $enrolment = $this->route('enrolment');

        return [
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'stream_id' => ['nullable', 'uuid', Rule::exists('streams', 'id')],
            'academic_session_id' => [
                'required',
                'uuid',
                Rule::exists('academic_sessions', 'id'),
                // DB has UNIQUE(student_id, academic_session_id) — surface
                // the conflict as a clean validation error instead of a 500.
                Rule::unique('enrolments', 'academic_session_id')
                    ->where('student_id', $enrolment->student_id),
            ],
            'residence_type' => ['nullable', 'string', Rule::in(['day', 'boarding'])],
            'enrolled_at' => ['nullable', 'date'],
        ];
    }

    /**
     * The new class/session are only existence-checked, not school-checked —
     * a student could otherwise be promoted into a class/session belonging
     * to a DIFFERENT school than the one they're actually enrolled in.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['class_id', 'academic_session_id', 'stream_id'])) {
                return;
            }

            /** @var Enrolment $enrolment */
            $enrolment = $this->route('enrolment');

            // Bypass BelongsToSchool: must see the TRUE record even if it
            // belongs to a different campus than the acting user, or a
            // cross-school id would silently resolve to null and skip this check.
            $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)->find($this->input('class_id'));
            $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->find($this->input('academic_session_id'));
            $stream = $this->filled('stream_id')
                ? Stream::withoutGlobalScope(SchoolScope::class)->find($this->input('stream_id'))
                : null;

            if ($classRoom?->school_id !== $enrolment->school_id || $academicSession?->school_id !== $enrolment->school_id) {
                $validator->errors()->add('class_id', 'The class and academic session must belong to the student\'s school.');
            }

            if ($stream !== null) {
                if ($stream->school_id !== $enrolment->school_id) {
                    $validator->errors()->add('stream_id', 'The stream must belong to the student\'s school.');
                } elseif ($stream->class_id !== $classRoom?->id) {
                    $validator->errors()->add('stream_id', 'The stream must belong to the selected class.');
                } elseif (! $stream->is_active) {
                    $validator->errors()->add('stream_id', 'The selected stream is not active.');
                }
            }
        });
    }
}
