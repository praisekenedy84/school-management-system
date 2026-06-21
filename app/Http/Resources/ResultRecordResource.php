<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student->full_name),
            'academic_session_id' => $this->academic_session_id,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject->name),
            'assessment_id' => $this->assessment_id,
            'assessment_name' => $this->whenLoaded('assessment', fn () => $this->assessment->name),
            'score' => $this->score,
            'grade' => $this->grade,
            'version' => $this->version,
            'is_published' => $this->is_published,
            'published_by' => $this->published_by,
            'published_at' => $this->published_at?->toIso8601String(),
            'entered_by' => $this->entered_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
