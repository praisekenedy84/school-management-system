<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Contracts\AuditableEvent;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NavigationChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $scope,
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
            'action' => 'navigation.updated',
            'subject_type' => 'navigation',
            'subject_id' => null,
            'changes' => ['scope' => $this->scope],
        ];
    }
}
