<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use App\Models\StoreRequisitionLine;
use Illuminate\Foundation\Http\FormRequest;

class IssueStoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('storeRequisition');

        return $requisition !== null
            && ($this->user()?->can('issue', $requisition) ?? false);
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $requisition = $this->route('storeRequisition');

            if (! $requisition) {
                return;
            }

            foreach ($this->input('lines', []) as $index => $line) {
                $exists = StoreRequisitionLine::query()
                    ->where('store_requisition_id', $requisition->id)
                    ->whereKey($line['line_id'] ?? null)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        "lines.{$index}.line_id",
                        'The selected line does not belong to this requisition.'
                    );
                }
            }
        });
    }
}
