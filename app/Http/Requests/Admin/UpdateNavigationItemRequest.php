<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\NavigationItem;
use App\Services\Admin\NavigationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UpdateNavigationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof NavigationItem
            && ($this->user()?->can('update', $item) ?? false);
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $allowedPaths = app(NavigationService::class)->allowedPaths();

        return [
            'label' => ['sometimes', 'string', 'max:100'],
            'path' => ['sometimes', 'string', Rule::in($allowedPaths), Rule::unique('navigation_items', 'path')->ignore($item?->id)],
            'icon' => ['sometimes', 'string', 'max:64'],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::query()->where('guard_name', 'web')->pluck('name'))],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'section_id' => ['sometimes', 'uuid', Rule::exists('navigation_sections', 'id')],
        ];
    }
}
