<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrolmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'class_name' => $this->whenLoaded('classRoom', fn () => $this->classRoom->name),
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->whenLoaded('academicSession', fn () => $this->academicSession->name),
            'residence_type' => $this->residence_type,
            'status' => $this->status,
            'enrolled_at' => $this->enrolled_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
