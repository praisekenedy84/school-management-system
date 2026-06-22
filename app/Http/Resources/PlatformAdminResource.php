<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => null,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => null,
            'locale' => null,
            // Platform Admin has no Spatie roles/permissions (central, not
            // tenant-scoped) — empty arrays keep the shape compatible with
            // the frontend's `User` type, which calls .includes()/.some() on
            // these unconditionally (AppLayout nav filter, RequireFinanceStaff,
            // RequirePermission, DashboardPage's staff/parent branch).
            'roles' => [],
            'permissions' => [],
            'type' => 'platform_admin',
        ];
    }
}
