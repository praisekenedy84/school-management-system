<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use App\Models\Hostel;
use App\Models\MealPlan;
use App\Models\Scopes\SchoolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mealPlan = $this->route('mealPlan');

        return $mealPlan
            ? ($this->user()?->can('update', $mealPlan) ?? false)
            : ($this->user()?->can('create', MealPlan::class) ?? false);
    }

    /**
     * school_id is derived from hostel_id (every meal plan belongs to
     * whichever school its hostel belongs to) — never asked of the client,
     * and never left to BelongsToSchool's `auth()->user()->school_id`
     * stamp, which is null for a tenant-wide admin and would otherwise
     * violate the column's NOT NULL constraint.
     */
    protected function prepareForValidation(): void
    {
        $hostel = Hostel::withoutGlobalScope(SchoolScope::class)->find($this->input('hostel_id'));

        if ($hostel !== null) {
            $this->merge(['school_id' => $hostel->school_id]);
        }
    }

    public function rules(): array
    {
        return [
            'hostel_id' => ['required', 'uuid', Rule::exists('hostels', 'id')],
            'school_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * hostel_id's existence check alone doesn't constrain school — a
     * school_admin could otherwise reference another campus' hostel.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('hostel_id')) {
                return;
            }

            $hostel = Hostel::withoutGlobalScope(SchoolScope::class)->find($this->input('hostel_id'));
            $actingSchoolId = $this->user()?->school_id;

            if ($hostel !== null && $actingSchoolId !== null && $hostel->school_id !== $actingSchoolId) {
                $validator->errors()->add('hostel_id', 'That hostel does not belong to your school.');
            }
        });
    }
}
