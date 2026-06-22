<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'academic_session_id' => $this->academic_session_id,
            'file_path' => $this->file_path,
            'withheld' => $this->withheld_reason !== null,
            'withheld_reason' => $this->withheld_reason,
            'generated_by' => $this->generated_by,
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
