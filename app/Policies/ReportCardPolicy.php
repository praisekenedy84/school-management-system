<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReportCard;
use App\Models\User;

/**
 * Phase 0/1-style scaffolding policy, matching `StudentPolicy`/
 * `ResultRecordPolicy`. `school_admin`/`tenant_admin`/`academic_director`
 * can generate; everyone can view (the controller's `student` ownership
 * check — once parent/student portal auth exists — narrows this further).
 * This is a placeholder — the full scoped-permission RBAC matrix
 * (RULES.md §5) replaces these checks later.
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
