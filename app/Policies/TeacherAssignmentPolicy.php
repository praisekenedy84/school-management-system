<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Phase 0/1 scaffolding policy. Assigning a teacher to a (class, subject,
 * session) is a school-admin-level action; everyone authenticated may view
 * the assignment list/detail (teachers need to see their own; admins need
 * to see all). The full scoped-permission RBAC matrix (RULES.md §5)
 * replaces these checks later.
 */
class TeacherAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TeacherAssignment $teacherAssignment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, TeacherAssignment $teacherAssignment): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }
}
