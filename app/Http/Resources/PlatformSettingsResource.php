<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform_name' => $this->platform_name,
            'support_email' => $this->support_email,
            'default_locale' => $this->default_locale,
            'default_currency' => $this->default_currency,
            'maintenance_mode' => $this->maintenance_mode,
            'max_tenants' => $this->max_tenants,
            'branding' => $this->branding ?? [],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
