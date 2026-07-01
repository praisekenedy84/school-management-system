<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Assignment $assignment */
        $assignment = $this->route('assignment');

        return $this->user()?->can('update', $assignment) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Assignment $assignment */
            $assignment = $this->route('assignment');

            if ($assignment->isPublished() || $assignment->isArchived()) {
                $validator->errors()->add('title', 'Only draft assignments can be edited.');
            }
        });
    }
}
