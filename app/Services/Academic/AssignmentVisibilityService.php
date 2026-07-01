<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Assignment;
use App\Models\Enrolment;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Centralises "who may see this assignment" so the rule lives in one place
 * (used by both the index query scope and `AssignmentPolicy::view()`)
 * rather than being duplicated ad-hoc in controllers.
 *
 * Visible to:
 *  - school_admin / tenant_admin (full school visibility, drafts included)
 *  - the teacher who owns the assignment (via teacher_assignments.teacher_id —
 *    including their own unpublished drafts)
 *  - a guardian linked to a student currently enrolled in the assignment's
 *    class, but ONLY once it's published (per PRD §5.3: "an assigned teacher
 *    publishes an assignment visible to that class + guardians" — drafts
 *    are not visible to guardians/students)
 *
 * NOTE: students do not yet authenticate directly as `users` (no
 * `students.user_id` link exists in Phase 0/1's schema — that's student
 * portal auth, deferred). Once that lands, add a branch here for "the
 * student themself" rather than duplicating the rule elsewhere.
 */
class AssignmentVisibilityService
{
    public function canView(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole(['tenant_admin', 'school_admin'])) {
            return true;
        }

        $assignment->loadMissing('teacherAssignment');
        $teacherAssignment = $assignment->teacherAssignment;

        if ($teacherAssignment === null) {
            return false;
        }

        if ($teacherAssignment->teacher_id === $user->id) {
            return true;
        }

        if ($assignment->published_at === null || $assignment->isArchived()) {
            return false;
        }

        // Guardian of a student currently enrolled in the assignment's class.
        $studentIds = Enrolment::query()
            ->where('class_id', $teacherAssignment->class_id)
            ->where('status', 'active')
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return false;
        }

        return StudentGuardian::query()
            ->where('guardian_id', $user->id)
            ->whereIn('student_id', $studentIds)
            ->exists();
    }

    /**
     * Scope a query to only the assignments a given user may see.
     *
     * @param  Builder<Assignment>  $query
     * @return Builder<Assignment>
     */
    public function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->hasRole(['tenant_admin', 'school_admin'])) {
            return $query;
        }

        $guardianStudentIds = StudentGuardian::query()
            ->where('guardian_id', $user->id)
            ->pluck('student_id');

        $guardianClassIds = $guardianStudentIds->isNotEmpty()
            ? Enrolment::query()
                ->whereIn('student_id', $guardianStudentIds)
                ->where('status', 'active')
                ->pluck('class_id')
            : collect();

        return $query->where(function (Builder $visibilityQuery) use ($user, $guardianClassIds) {
            $visibilityQuery->whereHas(
                'teacherAssignment',
                fn (Builder $taQuery) => $taQuery->where('teacher_id', $user->id)
            );

            if ($guardianClassIds->isNotEmpty()) {
                $visibilityQuery->orWhere(function (Builder $guardianQuery) use ($guardianClassIds) {
                    $guardianQuery->whereNotNull('published_at')
                        ->whereNull('archived_at')
                        ->whereHas(
                            'teacherAssignment',
                            fn (Builder $taQuery) => $taQuery->whereIn('class_id', $guardianClassIds)
                        );
                });
            }
        });
    }
}
