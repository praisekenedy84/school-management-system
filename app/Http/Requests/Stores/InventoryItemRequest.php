<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('inventoryItem');

        return $item
            ? ($this->user()?->can('update', $item) ?? false)
            : ($this->user()?->can('create', InventoryItem::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $item = $this->route('inventoryItem');

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'sku' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];

        if (! $item) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        if ($item) {
            $rules['name'][] = Rule::unique('inventory_items', 'name')
                ->where('school_id', $item->school_id)
                ->ignore($item->id);
        } elseif ($this->input('school_id')) {
            $rules['name'][] = Rule::unique('inventory_items', 'name')
                ->where('school_id', $this->input('school_id'));
        }

        return $rules;
    }
}
