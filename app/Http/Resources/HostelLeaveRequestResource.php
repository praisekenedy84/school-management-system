<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostelLeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'hostel_allocation_id' => $this->hostel_allocation_id,
            'reason' => $this->reason,
            'depart_at' => $this->depart_at?->toDateString(),
            'return_at' => $this->return_at?->toDateString(),
            'status' => $this->status,
            'decision_notes' => $this->decision_notes,
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}
