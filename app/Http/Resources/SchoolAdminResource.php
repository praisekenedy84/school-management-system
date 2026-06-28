<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Full school payload for tenant administration screens. */
class SchoolAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'branding' => $this->branding ?? [],
            'calendar_type' => $this->calendar_type,
            'grading_scale' => $this->grading_scale ?? [],
            'fee_terms' => $this->fee_terms ?? [],
            'billing' => $this->billing ?? [],
            'hostel_available' => $this->hostel_available,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
