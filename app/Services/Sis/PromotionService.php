<?php

declare(strict_types=1);

namespace App\Services\Sis;

use App\Models\Enrolment;
use Illuminate\Support\Facades\DB;

/**
 * Promotes a student to a new class/academic session. Append-only per
 * RULES.md §1: the OLD enrolment row is never updated in place beyond its
 * `status` flip — a brand NEW row carries the new class/session/history
 * forward.
 */
class PromotionService
{
    public function promote(Enrolment $enrolment, array $data): Enrolment
    {
        return DB::transaction(function () use ($enrolment, $data) {
            $enrolment->update(['status' => 'promoted']);

            return Enrolment::create([
                'school_id' => $enrolment->school_id,
                'student_id' => $enrolment->student_id,
                'class_id' => $data['class_id'],
                'academic_session_id' => $data['academic_session_id'],
                'residence_type' => $data['residence_type'] ?? $enrolment->residence_type,
                'status' => 'active',
                'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
            ])->load(['classRoom', 'academicSession', 'student']);
        });
    }
}
