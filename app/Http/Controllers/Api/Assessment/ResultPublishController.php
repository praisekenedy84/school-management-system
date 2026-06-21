<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResultRecordResource;
use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Services\Assessment\ResultPublishingService;

class ResultPublishController extends Controller
{
    public function __construct(private readonly ResultPublishingService $publishing) {}

    /**
     * Publishes every latest-version ResultRecord for this assessment.
     *
     * `ResultRecordPolicy::publish()` is keyed to a `ResultRecord` instance,
     * not an `Assessment` — there is no per-assessment ability to call
     * directly. We authorize against a REPRESENTATIVE record (one of the
     * assessment's own result records) when one exists; the policy's rule
     * is a pure role check (academic_director|school_admin|tenant_admin)
     * and doesn't actually inspect the record's attributes, so this is
     * equivalent in practice. If no result records exist yet at all, build
     * a transient unsaved instance scoped to this assessment/school so the
     * policy still has something typed to check against.
     */
    public function __invoke(Assessment $assessment)
    {
        $representative = ResultRecord::query()
            ->where('assessment_id', $assessment->id)
            ->first();

        if ($representative === null) {
            $representative = new ResultRecord([
                'school_id' => $assessment->school_id,
                'assessment_id' => $assessment->id,
                'academic_session_id' => $assessment->academic_session_id,
                'subject_id' => $assessment->subject_id,
            ]);
        }

        $this->authorize('publish', $representative);

        $resultRecords = $this->publishing->publish($assessment, request()->user()->id);

        return ResultRecordResource::collection($resultRecords->loadMissing(['student', 'subject', 'assessment']));
    }
}
