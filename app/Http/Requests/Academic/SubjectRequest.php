<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()?->can('update', $subject) ?? false
            : $this->user()?->can('create', Subject::class) ?? false;
    }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;
        $subject = $this->route('subject');

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('subjects', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($subject?->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
