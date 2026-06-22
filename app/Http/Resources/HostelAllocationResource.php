<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostelAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'hostel_room_id' => $this->hostel_room_id,
            'academic_session_id' => $this->academic_session_id,
            'status' => $this->status,
            'allocated_at' => $this->allocated_at?->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),
        ];
    }
}
