<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a payment slip for API output. Deliberately does NOT expose
 * submission_ip / device_info (forensic fields, RULES.md §7 "Resources never
 * leak sensitive fields") and only exposes attachment file paths/metadata,
 * not signed URLs — file streaming is a separate authorized endpoint.
 */
class PaymentSlipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'slip_number' => $this->slip_number,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student->full_name),
            'submitted_by' => $this->submitted_by,
            'payment_method_id' => $this->payment_method_id,
            'bank_name' => $this->bank_name,
            'branch_name' => $this->branch_name,
            'teller_number' => $this->teller_number,
            'transaction_reference' => $this->transaction_reference,
            'depositor_name' => $this->depositor_name,
            'deposit_date' => $this->deposit_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'allocation' => $this->allocation,
            'slip_attachments' => $this->slip_attachments,
            'status' => $this->status,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_notes' => $this->verification_notes,
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_category' => $this->rejection_category,
            'rejection_reason' => $this->rejection_reason,
            'receipt_number' => $this->receipt_number,
            'receipt_generated_at' => $this->receipt_generated_at?->toIso8601String(),
            'receipt' => PaymentReceiptResource::make($this->whenLoaded('receipt')),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
