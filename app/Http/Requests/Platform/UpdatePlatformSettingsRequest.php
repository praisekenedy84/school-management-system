<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    public function rules(): array
    {
        return [
            'platform_name' => ['sometimes', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'default_locale' => ['sometimes', 'string', 'max:10'],
            'default_currency' => ['sometimes', 'string', 'max:3'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'max_tenants' => ['nullable', 'integer', 'min:1'],
            'branding' => ['sometimes', 'array'],
            'branding.logo_url' => ['nullable', 'string', 'max:500'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
            'branding.support_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
