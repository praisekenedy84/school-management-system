<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use App\Models\HostelAllocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateHostelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HostelAllocation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')],
            'hostel_room_id' => ['required', 'uuid', Rule::exists('hostel_rooms', 'id')],
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
        ];
    }
}
