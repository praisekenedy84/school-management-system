<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->whenLoaded('academicSession', fn () => $this->academicSession->name),
            'class_id' => $this->class_id,
            'class_name' => $this->whenLoaded('classRoom', fn () => $this->classRoom->name),
            'fee_type' => $this->fee_type,
            'amount' => $this->amount,
            'is_mandatory' => $this->is_mandatory,
            'applicable_to' => $this->applicable_to,
            'installment_allowed' => $this->installment_allowed,
            'installment_count' => $this->installment_count,
            'due_date' => $this->due_date?->toDateString(),
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
