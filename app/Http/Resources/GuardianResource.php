<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A guardian as seen through a student's `guardians` relationship — a
 * `User` row plus the `student_guardians` pivot fields. Deliberately
 * narrower than `UserResource`: no roles/permissions leak here.
 */
class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'relationship' => $this->pivot?->relationship,
            'is_primary' => (bool) ($this->pivot?->is_primary ?? false),
        ];
    }
}
