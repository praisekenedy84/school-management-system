<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NavigationSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
            'platform_only' => (bool) ($this->platform_only ?? false),
            'is_active' => $this->is_active,
            'items' => NavigationItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
