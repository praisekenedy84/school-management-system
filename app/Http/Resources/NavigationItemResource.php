<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NavigationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'label' => $this->label,
            'path' => $this->path,
            'icon' => $this->icon,
            'permissions' => $this->permissions,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
        ];
    }
}
