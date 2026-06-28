<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Services\Admin\NavigationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformNavigationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $allowedPaths = app(NavigationService::class)->allowedPaths(true);

        return [
            'label' => ['sometimes', 'string', 'max:100'],
            'path' => ['sometimes', 'string', Rule::in($allowedPaths), Rule::unique('platform_navigation_items', 'path')->ignore($item?->id)],
            'icon' => ['sometimes', 'string', 'max:64'],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
