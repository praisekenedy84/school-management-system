<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Assessment;
use App\Models\ResultRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per publish action (one assessment's results going live), not
 * once per ResultRecord — keeps event volume sane. Listeners (snapshot
 * version, notify guardians, audit — ARCHITECTURE.md §5) receive the
 * assessment plus the full set of records that were just published.
 */
class ResultsPublished
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
}
