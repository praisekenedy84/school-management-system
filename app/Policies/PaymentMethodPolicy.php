<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Configuration, not personal financial data — same Phase 0/1 placeholder
 * shape as AcademicSessionPolicy/ClassRoomPolicy: `school_admin`/
 * `tenant_admin` mutate, everyone can view (parents need to see active
 * payment methods to know where to deposit). `finance_manager` also
 * manages these per RULES.md §5 (`finance.manage_fee_structures` implies
 * the adjacent payment-channel configuration).
 */
class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager']);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager']);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager']);
    }
}
