<?php

declare(strict_types=1);

namespace App\Events\Academic;

use App\Contracts\AuditableEvent;
use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AcademicTermChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'updated'|'deleted'  $action
     */
    public function __construct(
        public readonly AcademicTerm $term,
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
            'action' => "academic_term.{$this->action}",
            'subject_type' => AcademicTerm::class,
            'subject_id' => $this->term->id,
            'changes' => null,
        ];
    }
}
