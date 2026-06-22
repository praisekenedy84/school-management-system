<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'payment_slip_id' => $this->payment_slip_id,
            'receipt_number' => $this->receipt_number,
            'amount_in_words' => $this->amount_in_words,
            'payment_details' => $this->payment_details,
            'qr_code_path' => $this->qr_code_path,
            'file_path' => $this->file_path,
            'generated_by' => $this->generated_by,
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
