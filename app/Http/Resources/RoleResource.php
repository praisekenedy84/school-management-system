<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'permissions' => $this->resource['permissions'],
            'is_protected' => $this->resource['is_protected'],
        ];
    }
}
