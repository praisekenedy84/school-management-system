<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class AmendPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest !== null
            && ($this->user()?->can('amend', $purchaseRequest) ?? false);
    }

    public function rules(): array
    {
        return [
            'amendment_notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'uuid'],
            'lines.*.amended_quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.amended_unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
