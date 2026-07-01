<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AcademicTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AcademicSession $academicSession */
        $academicSession = $this->route('academicSession');

        return $this->user()?->can('update', $academicSession) ?? false;
    }

    public function rules(): array
    {
        /** @var AcademicSession $academicSession */
        $academicSession = $this->route('academicSession');
        /** @var AcademicTerm|null $term */
        $term = $this->route('term');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_terms', 'name')
                    ->where('academic_session_id', $academicSession->id)
                    ->ignore($term?->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Terms within a session must not overlap each other.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            /** @var AcademicSession $academicSession */
            $academicSession = $this->route('academicSession');
            /** @var AcademicTerm|null $term */
            $term = $this->route('term');

            $start = $this->date('start_date');
            $end = $this->date('end_date');

            if ($start === null || $end === null) {
                return;
            }

            if ($start->lt($academicSession->start_date) || $end->gt($academicSession->end_date)) {
                $validator->errors()->add('start_date', 'Term dates must fall within the academic session.');

                return;
            }

            $overlap = AcademicTerm::query()
                ->where('academic_session_id', $academicSession->id)
                ->when($term !== null, fn ($query) => $query->whereKeyNot($term->id))
                ->where('start_date', '<=', $end)
                ->where('end_date', '>=', $start)
                ->exists();

            if ($overlap) {
                $validator->errors()->add('start_date', 'Term dates overlap with an existing term in this session.');
            }
        });
    }
}
