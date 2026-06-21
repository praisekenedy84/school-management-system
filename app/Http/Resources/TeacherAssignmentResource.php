<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded('teacher', fn () => $this->teacher->name),
            'class_id' => $this->class_id,
            'class_name' => $this->whenLoaded('classRoom', fn () => $this->classRoom->name),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject->name),
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->whenLoaded('academicSession', fn () => $this->academicSession->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
