<?php

declare(strict_types=1);

namespace App\Events\Platform;

use App\Contracts\AuditableEvent;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a Platform Admin provisions a new tenant. Platform-level —
 * dispatched after `tenancy()->end()`, so `tenant_id` is carried explicitly
 * rather than read from the `tenant()` helper.
 */
class TenantProvisioned implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?PlatformAdmin $actor,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'actor_type' => 'platform_admin',
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'actor_email' => $this->actor?->email,
            'action' => 'tenant.provisioned',
            'subject_type' => 'Tenant',
            'subject_id' => $this->tenantId,
            'changes' => null,
        ];
    }
}
