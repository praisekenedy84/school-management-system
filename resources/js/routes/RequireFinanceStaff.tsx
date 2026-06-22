import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../app/AuthProvider';

/** Roles that staff the finance verification queue + manage fee/payment-method config. */
export const FINANCE_STAFF_ROLES = ['finance_manager', 'accountant', 'school_admin', 'tenant_admin'];

/**
 * Route guard for finance-staff-only pages (verification queue, fee
 * structures, payment methods) — mirrors Phase 2's publish-gating pattern.
 * Redirects a non-staff user (e.g. a parent) back to the dashboard; this is
 * UX only, the API still authorizes every action server-side (RULES §8).
 */
export function RequireFinanceStaff({ children }: { children: ReactNode }) {
    const { user } = useAuth();
    const isFinanceStaff = Boolean(user?.roles.some((role) => FINANCE_STAFF_ROLES.includes(role)));

    if (!isFinanceStaff) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}
