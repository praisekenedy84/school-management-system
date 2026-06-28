<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use App\Models\InventoryItem;
use App\Models\Scopes\SchoolScope;
use App\Models\StoreRequisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('storeRequisition');

        return $requisition
            ? ($this->user()?->can('update', $requisition) ?? false)
            : ($this->user()?->can('create', StoreRequisition::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $requisition = $this->route('storeRequisition');
        $schoolId = $requisition?->school_id ?? $this->input('school_id');

        $rules = [
            'purpose' => ['nullable', 'string', 'max:2000'],
            'needed_by' => ['nullable', 'date'],
            'lines' => [$requisition ? 'sometimes' : 'required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'uuid'],
            'lines.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $requisition) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $requisition = $this->route('storeRequisition');
            $schoolId = $requisition?->school_id ?? $this->input('school_id');

            if (! $schoolId || ! $this->has('lines')) {
                return;
            }

            foreach ($this->input('lines', []) as $index => $line) {
                $exists = InventoryItem::withoutGlobalScope(SchoolScope::class)
                    ->where('school_id', $schoolId)
                    ->whereKey($line['inventory_item_id'] ?? null)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        "lines.{$index}.inventory_item_id",
                        'The selected inventory item does not belong to this school.'
                    );
                }
            }
        });
    }
}
