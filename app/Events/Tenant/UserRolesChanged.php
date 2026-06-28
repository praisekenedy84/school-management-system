<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Contracts\AuditableEvent;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRolesChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public readonly User $target,
        public readonly array $roles,
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
            'action' => 'user.roles_updated',
            'subject_type' => User::class,
            'subject_id' => $this->target->id,
            'changes' => ['roles' => $this->roles],
        ];
    }
}
