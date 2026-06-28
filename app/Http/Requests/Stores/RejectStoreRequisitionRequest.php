<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class RejectStoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('storeRequisition');

        return $requisition !== null
            && ($this->user()?->can('reject', $requisition) ?? false);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }
}
