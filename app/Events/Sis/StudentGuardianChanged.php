<?php

declare(strict_types=1);

namespace App\Events\Sis;

use App\Contracts\AuditableEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentGuardianChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'linked'|'unlinked'  $action
     */
    public function __construct(
        public readonly Student $student,
        public readonly string $guardianId,
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
            'action' => "student.{$this->action}",
            'subject_type' => Student::class,
            'subject_id' => $this->student->id,
            'changes' => ['guardian_id' => $this->guardianId],
        ];
    }
}
