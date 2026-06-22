<?php

declare(strict_types=1);

namespace App\Events\Assessment;

use App\Contracts\AuditableEvent;
use App\Models\ResultRecord;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on every draft entry/correction made via MarkEntryService — distinct
 * from `ResultsPublished`, which covers the separate publish-gate action.
 */
class MarkEntered implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ResultRecord $resultRecord,
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
            'action' => 'mark.entered',
            'subject_type' => ResultRecord::class,
            'subject_id' => $this->resultRecord->id,
            'changes' => [
                'student_id' => $this->resultRecord->student_id,
                'assessment_id' => $this->resultRecord->assessment_id,
                'version' => $this->resultRecord->version,
                'score' => $this->resultRecord->score,
                'grade' => $this->resultRecord->grade,
            ],
        ];
    }
}
