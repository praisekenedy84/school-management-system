<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Contracts\AuditableEvent;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SchoolChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'updated'|'deleted'|'settings_updated'|'branding_updated'|'billing_updated'  $action
     */
    public function __construct(
        public readonly School $school,
        public readonly string $action,
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
            'action' => "school.{$this->action}",
            'subject_type' => School::class,
            'subject_id' => $this->school->id,
            'changes' => null,
        ];
    }
}
