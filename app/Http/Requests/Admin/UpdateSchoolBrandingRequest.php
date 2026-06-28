<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('school');

        return $school instanceof School
            && ($this->user()?->can('updateBranding', $school) ?? false);
    }

    public function rules(): array
    {
        return [
            'branding' => ['required', 'array'],
            'branding.logo_url' => ['nullable', 'string', 'max:500'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
            'branding.secondary_color' => ['nullable', 'string', 'max:20'],
            'branding.tagline' => ['nullable', 'string', 'max:255'],
        ];
    }
}
