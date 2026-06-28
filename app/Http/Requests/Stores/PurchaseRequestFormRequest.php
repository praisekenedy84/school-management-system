<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\Scopes\SchoolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest
            ? ($this->user()?->can('update', $purchaseRequest) ?? false)
            : ($this->user()?->can('create', PurchaseRequest::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $purchaseRequest = $this->route('purchaseRequest');

        $rules = [
            'title' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => [$purchaseRequest ? 'sometimes' : 'required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'uuid'],
            'lines.*.item_name' => ['required', 'string', 'max:200'],
            'lines.*.unit' => ['required', 'string', 'max:30'],
            'lines.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $purchaseRequest) {
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
            $purchaseRequest = $this->route('purchaseRequest');
            $schoolId = $purchaseRequest?->school_id ?? $this->input('school_id');

            if (! $schoolId || ! $this->has('lines')) {
                return;
            }

            foreach ($this->input('lines', []) as $index => $line) {
                if (empty($line['inventory_item_id'])) {
                    continue;
                }

                $exists = InventoryItem::withoutGlobalScope(SchoolScope::class)
                    ->where('school_id', $schoolId)
                    ->whereKey($line['inventory_item_id'])
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
