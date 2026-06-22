<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReportCard;
use App\Models\User;

/**
 * Phase 0/1-style scaffolding policy, matching `StudentPolicy`/
 * `ResultRecordPolicy`. `school_admin`/`tenant_admin`/`academic_director`
 * can generate; `view` itself is open because `ReportCardController`
 * always calls `authorize('view', $student)` first — that's where the
 * real narrowing happens (staff: unrestricted; parent: own wards only via
 * `StudentPolicy::view`). Student-portal ownership scoping is still
 * deferred — students don't authenticate as `User`s yet (PROJECT-PLAN.md
 * Phase 5).
 */
class ReportCardPolicy
{
    public function view(User $user, ReportCard $reportCard): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director', 'class_teacher']);
    }
}
