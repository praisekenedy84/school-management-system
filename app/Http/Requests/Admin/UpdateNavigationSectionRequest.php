<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\NavigationSection;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNavigationSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof NavigationSection
            && ($this->user()?->can('update', $section) ?? false);
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
