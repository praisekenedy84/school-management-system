<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FeeStructure;
use App\Models\User;

/**
 * Configuration, not personal financial data — same Phase 0/1 placeholder
 * shape as AcademicSessionPolicy/ClassRoomPolicy: `school_admin`/
 * `tenant_admin` mutate, everyone can view. RULES.md §5 additionally grants
 * `finance_manager` `finance.manage_fee_structures`, so it is included on
 * mutation checks here (unlike the academic-config policies).
 */
class FeeStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager']);
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager']);
    }

    public function delete(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager']);
    }
}
