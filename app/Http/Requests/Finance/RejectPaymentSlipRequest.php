<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rejection action input (SKILLS Recipe E / RULES.md §6:
 * rejection reason required|string|min:20; plus a category). Same `verify`
 * authorization gate as verification — rejection is part of the same
 * finance-officer workflow.
 */
class RejectPaymentSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $slip = $this->route('paymentSlip');

        return $slip !== null
            && ($this->user()?->can('verify', $slip) ?? false);
    }

    public function rules(): array
    {
        return [
            'rejection_category' => [
                'required',
                'string',
                'max:100',
                Rule::in(['incorrect_amount', 'unclear_image', 'wrong_details', 'duplicate', 'other']),
            ],
            'rejection_reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }
}
