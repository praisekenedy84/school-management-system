<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('lookup', [User::class, 'teacher'])
            || $user->can('lookup', [User::class, 'parent']);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['teacher', 'parent'])],
            'search' => ['nullable', 'string', 'max:100'],
            'school_id' => ['nullable', 'uuid', Rule::exists('schools', 'id')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
