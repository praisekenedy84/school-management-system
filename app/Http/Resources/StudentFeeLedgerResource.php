<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `balance` is a Postgres STORED GENERATED column — callers must ensure the
 * model was `->refresh()`ed after any create/update before serializing, or
 * this field reflects a stale/empty in-memory value (the generated column is
 * not re-read by Eloquent on write). See StudentFeeLedger model docblock.
 */
class StudentFeeLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'academic_session_id' => $this->academic_session_id,
            'fee_details' => $this->fee_details,
            'total_assessed' => $this->total_assessed,
            'total_discounts' => $this->total_discounts,
            'total_paid' => $this->total_paid,
            'balance' => $this->balance,
            'payment_status' => $this->payment_status,
            'last_payment_date' => $this->last_payment_date?->toDateString(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
