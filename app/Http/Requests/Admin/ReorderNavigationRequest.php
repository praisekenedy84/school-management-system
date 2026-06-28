<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\NavigationItem;
use Illuminate\Foundation\Http\FormRequest;

class ReorderNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', NavigationItem::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'sections' => ['sometimes', 'array'],
            'sections.*.id' => ['required', 'uuid'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
            'items.*.section_id' => ['sometimes', 'uuid'],
        ];
    }
}
