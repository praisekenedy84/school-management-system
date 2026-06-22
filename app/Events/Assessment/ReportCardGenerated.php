<?php

declare(strict_types=1);

namespace App\Events\Assessment;

use App\Contracts\AuditableEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when report-card generation is requested (queued), not when the
 * PDF job finishes — the subject is the student the card is being
 * generated for.
 */
class ReportCardGenerated implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $academicSessionId,
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
            'action' => 'report_card.generated',
            'subject_type' => Student::class,
            'subject_id' => $this->studentId,
            'changes' => ['academic_session_id' => $this->academicSessionId],
        ];
    }
}
