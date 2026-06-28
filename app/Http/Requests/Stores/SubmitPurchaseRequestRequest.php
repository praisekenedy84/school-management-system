<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest !== null
            && ($this->user()?->can('submit', $purchaseRequest) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
