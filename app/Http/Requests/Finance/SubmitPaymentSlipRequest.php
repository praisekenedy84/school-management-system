<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Models\AcademicSession;
use App\Models\PaymentSlip;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Rules\AllocationSumMatchesTotal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a parent (or finance staff on a parent's behalf) submitting a
 * payment slip — SKILLS Recipe D, RULES.md §6.
 *
 * Authorization mirrors PaymentSlipPolicy::create (role gate) AND adds the
 * per-student ownership rule the policy can't express at create-time because
 * the policy's `create($user)` has no model to inspect: a `parent` may only
 * submit for a student in their own `wards()`; finance staff / admins
 * (finance_manager, accountant, school_admin, tenant_admin) may submit on a
 * parent's behalf for any student in their school.
 *
 * Validation notes / judgment calls:
 * - slip_attachments[]: uses `mimes:jpg,jpeg,png,pdf` WITHOUT the `image`
 *   rule. RULES.md §6 lists `image|mimes:...|max:5120`, but Laravel's `image`
 *   rule rejects PDFs (it only passes actual raster images), which directly
 *   conflicts with the spec also allowing the `pdf` mime. Following the
 *   spec's stated intent ("slip image <= 5MB image/pdf"), `mimes` alone is
 *   the correct enforcement and still bounds the type set + 5MB size.
 * - teller_number: unique per (bank_name, deposit_date) among NON-deleted
 *   slips (`whereNull('deleted_at')`) — implements "duplicate teller per bank
 *   per date rejected". teller_number itself is nullable (cash/mobile-money
 *   may have no teller); the uniqueness rule only fires when a value is
 *   present.
 */
class SubmitPaymentSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Role gate (matches PaymentSlipPolicy::create + the verify-staff set
        // who may submit on a parent's behalf).
        if (! $user->can('create', PaymentSlip::class)
            && ! $user->hasRole(['finance_manager', 'accountant'])) {
            return false;
        }

        $studentId = $this->input('student_id');

        if (! is_string($studentId) || $studentId === '') {
            // Let rules() report the missing/invalid student_id rather than a
            // blanket 403.
            return true;
        }

        // Finance staff / admins may submit on a parent's behalf for any
        // student in their school (SchoolScope still confines them to it).
        if ($user->hasRole(['finance_manager', 'accountant', 'school_admin', 'tenant_admin'])) {
            return Student::query()->whereKey($studentId)->exists();
        }

        // Parents: only their own wards.
        if ($user->hasRole('parent')) {
            return $user->wards()->whereKey($studentId)->exists();
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')],
            'payment_method_id' => ['nullable', 'uuid', Rule::exists('payment_methods', 'id')],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'branch_name' => ['nullable', 'string', 'max:200'],
            'teller_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('payment_slips', 'teller_number')
                    ->where('bank_name', $this->input('bank_name'))
                    ->where('deposit_date', $this->input('deposit_date'))
                    ->whereNull('deleted_at'),
            ],
            'transaction_reference' => ['nullable', 'string', 'max:200'],
            'depositor_name' => ['required', 'string', 'max:300'],
            'deposit_date' => ['required', 'date', 'before_or_equal:today', 'after:2020-01-01'],
            'total_amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],

            'allocation' => ['required', 'array', new AllocationSumMatchesTotal($this->input('total_amount'))],
            'allocation.*.fee_type' => ['required', 'string', 'max:100'],
            'allocation.*.amount' => ['required', 'numeric', 'min:0.01'],
            'allocation.*.academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],

            'slip_attachments' => ['required', 'array', 'min:1', 'max:10'],
            'slip_attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * Cross-school guard: every allocation line's academic_session_id must
     * belong to the SAME school as the target student. Without this, a slip
     * could allocate against another campus' session. Mirrors the
     * `withoutGlobalScope(SchoolScope)` precedent in AssessmentRequest.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['student_id', 'allocation'])) {
                return;
            }

            $student = Student::withoutGlobalScope(SchoolScope::class)->find($this->input('student_id'));

            if ($student === null) {
                return;
            }

            foreach ((array) $this->input('allocation', []) as $index => $line) {
                $sessionId = $line['academic_session_id'] ?? null;

                if ($sessionId === null) {
                    continue;
                }

                $session = AcademicSession::withoutGlobalScope(SchoolScope::class)->find($sessionId);

                if ($session === null) {
                    continue;
                }

                if ($session->school_id !== $student->school_id) {
                    $validator->errors()->add(
                        "allocation.{$index}.academic_session_id",
                        'The academic session must belong to the same school as the student.'
                    );
                }
            }
        });
    }
}
