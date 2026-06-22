<?php

declare(strict_types=1);

namespace App\Events\Platform;

use App\Contracts\AuditableEvent;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImpersonationEnded implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{platform_admin_id: string, platform_admin_name: string, started_at: string}  $impersonation
     */
    public function __construct(
        public readonly ?User $target,
        public readonly array $impersonation,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'platform_admin',
            'actor_id' => $this->impersonation['platform_admin_id'] ?? null,
            'actor_name' => $this->impersonation['platform_admin_name'] ?? null,
            'actor_email' => null,
            'action' => 'impersonation.ended',
            'subject_type' => User::class,
            'subject_id' => $this->target?->id,
            'changes' => ['started_at' => $this->impersonation['started_at'] ?? null],
        ];
    }
}
