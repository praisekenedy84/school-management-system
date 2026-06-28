<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Contracts\AuditableEvent;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RolePermissionsChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roleName,
        public readonly array $permissions,
        public readonly ?User $actor,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'actor_email' => $this->actor?->email,
            'action' => 'role.permissions_updated',
            'subject_type' => 'role',
            'subject_id' => null,
            'changes' => ['role' => $this->roleName, 'permissions' => $this->permissions],
        ];
    }
}
