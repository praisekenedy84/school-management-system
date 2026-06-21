<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Events\ResultsPublished;
use App\Models\Assessment;
use App\Models\ResultRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Publishes ALL latest-version `ResultRecord`s for one assessment in a
 * single transaction. These rows are drafts being finalized for the FIRST
 * time, so updating them in place here is correct — this is NOT the
 * "correction after publish" scenario (that's `MarkEntryService`, which
 * never mutates a published row).
 */
class ResultPublishingService
{
    public function publish(Assessment $assessment, string $publishedBy): Collection
    {
        return DB::transaction(function () use ($assessment, $publishedBy) {
            $latestIds = ResultRecord::query()
                ->where('assessment_id', $assessment->id)
                ->whereIn('id', function ($sub) use ($assessment) {
                    $sub->selectRaw('DISTINCT ON (student_id) id')
                        ->from('result_records')
                        ->where('assessment_id', $assessment->id)
                        ->orderBy('student_id')
                        ->orderByDesc('version');
                })
                ->lockForUpdate()
                ->get();

            $now = now();

            foreach ($latestIds as $resultRecord) {
                $resultRecord->update([
                    'is_published' => true,
                    'published_by' => $publishedBy,
                    'published_at' => $now,
                ]);
            }

            ResultsPublished::dispatch($assessment, $latestIds, $publishedBy);

            return $latestIds;
        });
    }
}
