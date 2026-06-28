<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class SubmitStoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('storeRequisition');

        return $requisition !== null
            && ($this->user()?->can('submit', $requisition) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
