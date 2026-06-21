<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Models\Scopes\SchoolScope;
use Illuminate\Support\Facades\DB;

/**
 * Enters or corrects a single (student, assessment) score, respecting the
 * append-only/versioned invariant on `ResultRecord` (RULES.md §1/§3):
 *
 *  - If the latest version for (student, assessment) is UNPUBLISHED (a
 *    draft — nothing official has happened to it yet), update it in place.
 *  - If the latest version IS published, a "correction" inserts a NEW row
 *    with `version` = latest + 1, `is_published = false` — the published
 *    row itself is never mutated.
 *  - If no row exists yet, create version 1.
 */
class MarkEntryService
{
    public function enter(array $data, string $enteredBy): ResultRecord
    {
        return DB::transaction(function () use ($data, $enteredBy) {
            $latest = ResultRecord::latestVersionFor($data['student_id'], $data['assessment_id'])
                ->first();

            if ($latest === null) {
                $assessment = Assessment::withoutGlobalScope(SchoolScope::class)->findOrFail($data['assessment_id']);

                return ResultRecord::create([
                    'school_id' => $assessment->school_id,
                    'student_id' => $data['student_id'],
                    'academic_session_id' => $assessment->academic_session_id,
                    'subject_id' => $assessment->subject_id,
                    'assessment_id' => $data['assessment_id'],
                    'score' => $data['score'] ?? null,
                    'grade' => $data['grade'] ?? null,
                    'version' => 1,
                    'is_published' => false,
                    'entered_by' => $enteredBy,
                ]);
            }

            if (! $latest->is_published) {
                $latest->update([
                    'score' => $data['score'] ?? null,
                    'grade' => $data['grade'] ?? null,
                    'entered_by' => $enteredBy,
                ]);

                return $latest;
            }

            return ResultRecord::create([
                'school_id' => $latest->school_id,
                'student_id' => $latest->student_id,
                'academic_session_id' => $latest->academic_session_id,
                'subject_id' => $latest->subject_id,
                'assessment_id' => $latest->assessment_id,
                'score' => $data['score'] ?? null,
                'grade' => $data['grade'] ?? null,
                'version' => $latest->version + 1,
                'is_published' => false,
                'entered_by' => $enteredBy,
            ]);
        });
    }
}
