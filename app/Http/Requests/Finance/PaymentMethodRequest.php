<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plain CRUD validation for payment-method configuration (mirrors
 * SubjectRequest's shape). No cross-school references here — a payment method
 * is a flat per-school config row, so no withValidator() is needed.
 */
class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paymentMethod = $this->route('paymentMethod');

        return $paymentMethod instanceof PaymentMethod
            ? $this->user()?->can('update', $paymentMethod) ?? false
            : $this->user()?->can('create', PaymentMethod::class) ?? false;
    }

    /**
     * A school-scoped user's own school_id always wins over anything they
     * submit (closes a privilege-escalation gap now that this field is
     * accepted at all); a tenant-wide admin (no school_id of their own)
     * must say which school a NEW payment method belongs to.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $paymentMethod = $this->route('paymentMethod');

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['bank_transfer', 'cash_deposit', 'mobile_money', 'cheque', 'direct_cash'])],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:200'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:50'],
            'payment_instructions' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];

        // school_id is fixed once a payment method exists — only required on create.
        if (! $paymentMethod instanceof PaymentMethod) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        return $rules;
    }
}
