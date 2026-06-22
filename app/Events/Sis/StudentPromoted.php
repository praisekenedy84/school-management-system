<?php

declare(strict_types=1);

namespace App\Events\Sis;

use App\Contracts\AuditableEvent;
use App\Models\Enrolment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentPromoted implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Enrolment $enrolment,
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
            'action' => 'student.promoted',
            'subject_type' => Student::class,
            'subject_id' => $this->enrolment->student_id,
            'changes' => [
                'enrolment_id' => $this->enrolment->id,
                'class_id' => $this->enrolment->class_id,
                'academic_session_id' => $this->enrolment->academic_session_id,
            ],
        ];
    }
}
