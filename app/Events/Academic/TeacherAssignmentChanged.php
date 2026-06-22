<?php

declare(strict_types=1);

namespace App\Events\Academic;

use App\Contracts\AuditableEvent;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeacherAssignmentChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'deleted'  $action
     */
    public function __construct(
        public readonly TeacherAssignment $teacherAssignment,
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
            'action' => "teacher_assignment.{$this->action}",
            'subject_type' => TeacherAssignment::class,
            'subject_id' => $this->teacherAssignment->id,
            'changes' => null,
        ];
    }
}
