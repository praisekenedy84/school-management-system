<?php

declare(strict_types=1);

namespace App\Events\Platform;

use App\Contracts\AuditableEvent;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlatformSettingsChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PlatformSetting $settings,
        public readonly ?PlatformAdmin $actor,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => null,
            'actor_type' => 'platform_admin',
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'actor_email' => $this->actor?->email,
            'action' => 'platform.settings_updated',
            'subject_type' => PlatformSetting::class,
            'subject_id' => $this->settings->id,
            'changes' => null,
        ];
    }
}
