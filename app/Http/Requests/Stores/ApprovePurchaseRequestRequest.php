<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest !== null
            && ($this->user()?->can('approve', $purchaseRequest) ?? false);
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
