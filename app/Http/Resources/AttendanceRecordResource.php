<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student->full_name),
            'class_id' => $this->class_id,
            'academic_session_id' => $this->academic_session_id,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'period' => $this->period,
            'status' => $this->status,
            'note' => $this->note,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
