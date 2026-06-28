<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class ApproveStoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('storeRequisition');

        return $requisition !== null
            && ($this->user()?->can('approve', $requisition) ?? false);
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
