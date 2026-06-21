<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ResultRecord;
use App\Models\User;

/**
 * `ResultRecord` policy with two real rules: `create()` and `publish()`.
 * `create()` gates mark entry to roles that can plausibly enter marks
 * (tenant_admin/school_admin/academic_director/class_teacher/teacher); the
 * actual per-(student, assessment) OWNERSHIP check — does this specific
 * teacher hold a TeacherAssignment for the assessment's subject + session —
 * lives in `EnterMarkRequest::withValidator()`, mirroring how
 * `AssignmentPolicy::create()` + `CreateAssignmentRequest` split the same
 * concern. `update`/`delete` remain Phase 0/1-style admin-only placeholders
 * (mark CORRECTION goes through `create()`/`MarkEntryService`, never a
 * direct update/delete on a `ResultRecord`).
 */
class ResultRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResultRecord $resultRecord): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director', 'class_teacher', 'teacher']);
    }

    public function update(User $user, ResultRecord $resultRecord): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, ResultRecord $resultRecord): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    /**
     * Publishing is gated per PRD §5.5: only an academic_director (or
     * school/tenant admin) may approve and publish a result. This is a
     * real authorization rule, not a placeholder — gated publishing is
     * the core Phase 2 requirement for this model.
     */
    public function publish(User $user, ResultRecord $resultRecord): bool
    {
        return $user->hasRole(['academic_director', 'school_admin', 'tenant_admin']);
    }
}
