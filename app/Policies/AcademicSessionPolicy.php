<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AcademicSession;
use App\Models\User;

/**
 * Phase 0/1 scaffolding policy (same shape as ClassRoomPolicy/SubjectPolicy).
 * `school_admin`/`tenant_admin` can mutate; everyone can view.
 */
class AcademicSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AcademicSession $academicSession): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function update(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, AcademicSession $academicSession): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }
}
