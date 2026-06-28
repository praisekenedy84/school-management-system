<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use App\Models\PurchaseRequestLine;
use Illuminate\Foundation\Http\FormRequest;

class FulfillPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest !== null
            && ($this->user()?->can('fulfill', $purchaseRequest) ?? false);
    }

    public function rules(): array
    {
        return [
            'supplier_name' => ['nullable', 'string', 'max:200'],
            'supplier_reference' => ['nullable', 'string', 'max:200'],
            'fulfillment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_request_line_id' => ['required', 'uuid'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.actual_unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $purchaseRequest = $this->route('purchaseRequest');

            if (! $purchaseRequest) {
                return;
            }

            foreach ($this->input('lines', []) as $index => $line) {
                $exists = PurchaseRequestLine::query()
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->whereKey($line['purchase_request_line_id'] ?? null)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        "lines.{$index}.purchase_request_line_id",
                        'The selected line does not belong to this purchase request.'
                    );
                }
            }
        });
    }
}
