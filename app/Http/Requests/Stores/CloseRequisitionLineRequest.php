<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class CloseRequisitionLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('storeRequisition');

        return $requisition !== null
            && ($this->user()?->can('closeLine', $requisition) ?? false);
    }

    public function rules(): array
    {
        return [
            'line_id' => ['required', 'uuid'],
            'line_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
