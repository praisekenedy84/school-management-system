<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\FeeStructure;
use App\Models\Scopes\SchoolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Plain CRUD validation for fee structures (no service needed — mirrors
 * AssessmentRequest). Cross-school membership of class_id / academic_session_id
 * is checked in withValidator() using the same `withoutGlobalScope(SchoolScope)`
 * precedent established in AssessmentRequest::withValidator (Phase 2).
 */
class FeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $feeStructure = $this->route('feeStructure');

        return $feeStructure instanceof FeeStructure
            ? $this->user()?->can('update', $feeStructure) ?? false
            : $this->user()?->can('create', FeeStructure::class) ?? false;
    }

    /**
     * school_id is derived from class_id (withValidator below already
     * requires it to match academic_session_id's school) — never left to
     * BelongsToSchool's `auth()->user()->school_id` stamp, which is null
     * for a tenant-wide admin and would violate the NOT NULL constraint.
     */
    protected function prepareForValidation(): void
    {
        $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)->find($this->input('class_id'));

        if ($classRoom !== null) {
            $this->merge(['school_id' => $classRoom->school_id]);
        }
    }

    public function rules(): array
    {
        $feeStructure = $this->route('feeStructure');

        return [
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'school_id' => ['nullable', 'uuid'],
            'fee_type' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fee_structures', 'fee_type')
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->where('class_id', $this->input('class_id'))
                    ->ignore($feeStructure?->id),
            ],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_mandatory' => ['boolean'],
            'applicable_to' => ['required', Rule::in(['all', 'day_only', 'boarding_only'])],
            'installment_allowed' => ['boolean'],
            'installment_count' => ['nullable', 'integer', 'min:1', 'max:24'],
            'due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * class_id / academic_session_id existence checks alone don't constrain
     * school — without this, a school_admin could pair a class and session
     * from two different campuses, or reference another campus' rows.
     * Mirrors AssessmentRequest::withValidator exactly.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['class_id', 'academic_session_id'])) {
                return;
            }

            $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)->find($this->input('class_id'));
            $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->find($this->input('academic_session_id'));

            if ($classRoom === null || $academicSession === null) {
                return;
            }

            if ($classRoom->school_id !== $academicSession->school_id) {
                $validator->errors()->add('class_id', 'The class and academic session must belong to the same school.');
            }

            $actingSchoolId = $this->user()?->school_id;

            if ($actingSchoolId !== null && $classRoom->school_id !== $actingSchoolId) {
                $validator->errors()->add('class_id', 'The class and academic session must belong to your school.');
            }
        });
    }
}
