<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentSlip;
use App\Models\User;

/**
 * Real rules, not a placeholder — finance data sensitivity demands it
 * (RULES.md §7 security checklist: "parent endpoints check slip/child
 * ownership"; RULES.md §5 RBAC matrix).
 *
 * - `school_admin`/`tenant_admin`: full access within their scope.
 * - `finance_manager`/`accountant`: can view all slips in the school and
 *   run the verification workflow (`verify()`); these roles hold
 *   `finance.verify_slips` per RULES.md §5.
 * - `parent`: may `create` (submit a slip) and `view` ONLY slips for
 *   students in their own `wards()` relation (Student/User many-to-many via
 *   `student_guardians`, RULES.md §"submitter↔slip" / "guardian↔student").
 *   A parent never gets `update`/`delete`/`verify` — submission is
 *   append-only from their side; corrections go through Finance.
 *
 * `update`/`delete` are intentionally NOT exposed to anyone outside
 * school_admin/tenant_admin: per RULES.md §1/§3 a slip's status/amount must
 * never be mutated by a bare update — that's PaymentSlipService's job via
 * the verify/reject/clarify actions, gated by `verify()` below, not by
 * Eloquent's generic update/delete.
 */
class PaymentSlipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentSlip $paymentSlip): bool
    {
        if ($user->hasRole(['tenant_admin', 'school_admin', 'finance_manager', 'accountant'])) {
            return true;
        }

        if ($user->hasRole('parent')) {
            return $user->wards()->whereKey($paymentSlip->student_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'parent']);
    }

    public function update(User $user, PaymentSlip $paymentSlip): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, PaymentSlip $paymentSlip): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    /**
     * Gates the verification workflow (verify/reject/clarify transitions),
     * not the generic Eloquent `update()`. RULES.md §5: `finance_manager`
     * and `accountant` both hold `finance.verify_slips`.
     */
    public function verify(User $user, PaymentSlip $paymentSlip): bool
    {
        return $user->hasRole(['finance_manager', 'accountant', 'school_admin', 'tenant_admin']);
    }
}
