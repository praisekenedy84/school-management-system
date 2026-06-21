<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use App\Services\Academic\AssignmentVisibilityService;

/**
 * Assignment is the first real read-sensitive endpoint in the project
 * (PROJECT-PLAN Phase 1 exit criteria: visible to the owning teacher,
 * school_admin/tenant_admin, and guardians of enrolled students). `view()`
 * therefore delegates to `AssignmentVisibilityService` instead of the
 * Phase-0 blanket-`true` placeholder used elsewhere. `create`/`update`/
 * `delete` remain ownership/role checks; the full scoped-permission RBAC
 * matrix (RULES.md §5) replaces the rest later.
 */
class AssignmentPolicy
{
    public function __construct(private readonly AssignmentVisibilityService $visibility) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $this->visibility->canView($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'teacher', 'class_teacher']);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole(['tenant_admin', 'school_admin'])) {
            return true;
        }

        $assignment->loadMissing('teacherAssignment');

        return $assignment->teacherAssignment?->teacher_id === $user->id;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
