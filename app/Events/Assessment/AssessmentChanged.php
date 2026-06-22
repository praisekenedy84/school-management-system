<?php

declare(strict_types=1);

namespace App\Events\Assessment;

use App\Contracts\AuditableEvent;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'updated'|'deleted'  $action
     */
    public function __construct(
        public readonly Assessment $assessment,
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
            'action' => "assessment.{$this->action}",
            'subject_type' => Assessment::class,
            'subject_id' => $this->assessment->id,
            'changes' => null,
        ];
    }
}
