<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('school');

        return $school instanceof School
            && ($this->user()?->can('updateBilling', $school) ?? false);
    }

    public function rules(): array
    {
        return [
            'billing' => ['required', 'array'],
            'billing.billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing.billing_contact_email' => ['nullable', 'email', 'max:255'],
            'billing.billing_contact_phone' => ['nullable', 'string', 'max:50'],
            'billing.tax_id' => ['nullable', 'string', 'max:100'],
            'billing.billing_address' => ['nullable', 'string', 'max:500'],
            'billing.invoice_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
