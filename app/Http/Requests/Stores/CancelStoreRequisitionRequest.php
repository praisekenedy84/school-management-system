<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class CancelStoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('storeRequisition'));
    }

    public function rules(): array
    {
        return [];
    }
}
