<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Verification action input (SKILLS Recipe E / RULES.md §6:
 * verification.notes required|string|min:10|max:1000). Authorization is the
 * `verify` ability on the route-bound slip (PaymentSlipPolicy::verify —
 * finance_manager|accountant|school_admin|tenant_admin).
 */
class VerifyPaymentSlipRequest extends FormRequest
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
            'verification_notes' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
