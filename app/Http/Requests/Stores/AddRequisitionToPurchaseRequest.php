<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use App\Models\PurchaseRequest;
use App\Models\StoreRequisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddRequisitionToPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addToPurchase', $this->route('storeRequisition'));
    }

    public function rules(): array
    {
        /** @var StoreRequisition $requisition */
        $requisition = $this->route('storeRequisition');

        return [
            'mode' => ['required', Rule::in(['shortfall', 'all'])],
            'purchase_request_id' => [
                'nullable',
                'uuid',
                Rule::exists(PurchaseRequest::class, 'id')
                    ->where('school_id', $requisition->school_id)
                    ->where('status', 'draft'),
            ],
        ];
    }
}
