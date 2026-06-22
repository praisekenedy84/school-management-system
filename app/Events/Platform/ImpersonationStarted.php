<?php

declare(strict_types=1);

namespace App\Events\Platform;

use App\Contracts\AuditableEvent;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImpersonationStarted implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $target,
        public readonly PlatformAdmin $admin,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'platform_admin',
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'actor_email' => $this->admin->email,
            'action' => 'impersonation.started',
            'subject_type' => User::class,
            'subject_id' => $this->target->id,
            'changes' => ['target_name' => $this->target->name, 'target_email' => $this->target->email],
        ];
    }
}
