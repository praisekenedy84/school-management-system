<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use Illuminate\Foundation\Http\FormRequest;

class DecideLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $leaveRequest = $this->route('hostelLeaveRequest');

        return $this->user()?->can('decide', $leaveRequest) ?? false;
    }

    public function rules(): array
    {
        return [
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
