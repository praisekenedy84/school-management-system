<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradingScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['tenant_admin', 'school_admin', 'academic_director']) ?? false;
    }

    public function rules(): array
    {
        return [
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.grade' => ['required', 'string', 'max:5'],
            'bands.*.label' => ['nullable', 'string', 'max:50'],
        ];
    }
}
