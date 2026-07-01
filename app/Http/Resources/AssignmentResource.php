<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'teacher_assignment_id' => $this->teacher_assignment_id,
            'class_id' => $this->whenLoaded('teacherAssignment', fn () => $this->teacherAssignment->class_id),
            'class_name' => $this->whenLoaded(
                'teacherAssignment',
                fn () => $this->teacherAssignment->classRoom?->name
            ),
            'subject_name' => $this->whenLoaded(
                'teacherAssignment',
                fn () => $this->teacherAssignment->subject?->name
            ),
            'teacher_name' => $this->whenLoaded(
                'teacherAssignment',
                fn () => $this->teacherAssignment->teacher?->name
            ),
            'title' => $this->title,
            'description' => $this->description,
            'due_at' => $this->due_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'is_published' => $this->isPublished(),
            'is_archived' => $this->isArchived(),
            'status' => $this->status(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
