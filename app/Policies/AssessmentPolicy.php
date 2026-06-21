<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Policy for the `Assessment` model (assessment *definitions* — not to be
 * confused with `AssignmentPolicy`, which guards the unrelated
 * `Assignment`/homework model). RULES.md §5 grants `academic_director` the
 * `assessment.manage_grading` permission, so create/update include that
 * role as a real rule, not a placeholder. `delete` stays admin-only
 * (removing a definition orphans any entered marks against it).
 */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director']);
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director']);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }
}
