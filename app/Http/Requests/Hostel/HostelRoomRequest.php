<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\Scopes\SchoolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HostelRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $room = $this->route('hostelRoom');

        return $room
            ? ($this->user()?->can('update', $room) ?? false)
            : ($this->user()?->can('create', HostelRoom::class) ?? false);
    }

    /**
     * school_id is derived from hostel_id, mirroring MealPlanRequest — never
     * left to BelongsToSchool's `auth()->user()->school_id` stamp, which is
     * null for a tenant-wide admin and would violate the NOT NULL constraint.
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
            'room_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
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
