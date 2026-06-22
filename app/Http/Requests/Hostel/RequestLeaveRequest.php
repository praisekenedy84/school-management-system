<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use App\Models\HostelAllocation;
use App\Models\HostelLeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RequestLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HostelLeaveRequest::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'hostel_allocation_id' => ['required', 'uuid', Rule::exists('hostel_allocations', 'id')],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'depart_at' => ['required', 'date', 'after_or_equal:today'],
            'return_at' => ['required', 'date', 'after:depart_at'],
        ];
    }

    /**
     * A parent may only request leave for their own ward's allocation;
     * admins/hostel_manager can request on anyone's behalf.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user === null || ! $user->hasRole('parent')) {
                return;
            }

            if ($validator->errors()->has('hostel_allocation_id')) {
                return;
            }

            $allocation = HostelAllocation::find($this->input('hostel_allocation_id'));

            if ($allocation !== null && ! $user->wards()->whereKey($allocation->student_id)->exists()) {
                $validator->errors()->add('hostel_allocation_id', 'You may only request leave for your own ward.');
            }
        });
    }
}
