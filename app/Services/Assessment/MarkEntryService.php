<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Events\Assessment\MarkEntered;
use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Support\Facades\Auth;
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
    public function __construct(private readonly GradingScaleService $gradingScale) {}

    public function enter(array $data, string $enteredBy): ResultRecord
    {
        return DB::transaction(function () use ($data, $enteredBy) {
            $latest = ResultRecord::latestVersionFor($data['student_id'], $data['assessment_id'])
                ->first();

            if ($latest === null) {
                $assessment = Assessment::withoutGlobalScope(SchoolScope::class)->findOrFail($data['assessment_id']);
                $school = School::findOrFail($assessment->school_id);
                $grade = $data['grade'] ?? $this->gradingScale->gradeForScore(
                    isset($data['score']) ? (float) $data['score'] : null,
                    (float) $assessment->max_score,
                    $school,
                );

                $result = ResultRecord::create([
                    'school_id' => $assessment->school_id,
                    'student_id' => $data['student_id'],
                    'academic_session_id' => $assessment->academic_session_id,
                    'subject_id' => $assessment->subject_id,
                    'assessment_id' => $data['assessment_id'],
                    'score' => $data['score'] ?? null,
                    'grade' => $grade,
                    'version' => 1,
                    'is_published' => false,
                    'entered_by' => $enteredBy,
                ]);

                MarkEntered::dispatch($result, Auth::user());

                return $result;
            }

            if (! $latest->is_published) {
                $assessment = Assessment::withoutGlobalScope(SchoolScope::class)->findOrFail($data['assessment_id']);
                $school = School::findOrFail($assessment->school_id);
                $grade = $data['grade'] ?? $this->gradingScale->gradeForScore(
                    isset($data['score']) ? (float) $data['score'] : null,
                    (float) $assessment->max_score,
                    $school,
                );

                $latest->update([
                    'score' => $data['score'] ?? null,
                    'grade' => $grade,
                    'entered_by' => $enteredBy,
                ]);

                MarkEntered::dispatch($latest, Auth::user());

                return $latest;
            }

            $assessment = Assessment::withoutGlobalScope(SchoolScope::class)->findOrFail($data['assessment_id']);
            $school = School::findOrFail($assessment->school_id);
            $grade = $data['grade'] ?? $this->gradingScale->gradeForScore(
                isset($data['score']) ? (float) $data['score'] : null,
                (float) $assessment->max_score,
                $school,
            );

            $result = ResultRecord::create([
                'school_id' => $latest->school_id,
                'student_id' => $latest->student_id,
                'academic_session_id' => $latest->academic_session_id,
                'subject_id' => $latest->subject_id,
                'assessment_id' => $latest->assessment_id,
                'score' => $data['score'] ?? null,
                'grade' => $grade,
                'version' => $latest->version + 1,
                'is_published' => false,
                'entered_by' => $enteredBy,
            ]);

            MarkEntered::dispatch($result, Auth::user());

            return $result;
        });
    }
}
