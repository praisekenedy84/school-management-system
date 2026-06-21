<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'admission_number' => $this->admission_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'residence_type' => $this->residence_type,
            'status' => $this->status,
            'admitted_at' => $this->admitted_at?->toDateString(),
            'photo_path' => $this->photo_path,
            'current_enrolment' => $this->whenLoaded(
                'enrolments',
                fn () => EnrolmentResource::make(
                    $this->enrolments->firstWhere('status', 'active') ?? $this->enrolments->last()
                )
            ),
            'enrolments' => EnrolmentResource::collection($this->whenLoaded('enrolments')),
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
