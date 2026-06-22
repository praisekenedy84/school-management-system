<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AuditableEvent;
use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per publish action (one assessment's results going live), not
 * once per ResultRecord — keeps event volume sane. SnapshotVersion is
 * already handled structurally (append-only at the model layer, see
 * ResultRecord); NotifyGuardians is still not wired (no notification engine
 * yet). LogAudit now is, via the `AuditableEvent` contract.
 */
class ResultsPublished implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, ResultRecord>  $resultRecords
     */
    public function __construct(
        public readonly Assessment $assessment,
        public readonly Collection $resultRecords,
        public readonly string $publishedBy,
    ) {}

    public function toAuditLog(): array
    {
        $actor = User::find($this->publishedBy);

        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'action' => 'assessment.results_published',
            'subject_type' => Assessment::class,
            'subject_id' => $this->assessment->id,
            'changes' => ['result_count' => $this->resultRecords->count()],
        ];
    }
}
