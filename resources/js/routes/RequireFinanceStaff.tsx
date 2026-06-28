import type { ReactNode } from 'react';
import { RequireAnyPermission } from './RequireAnyPermission';

/** Permissions that unlock finance-staff pages (verification, fee config). */
export const FINANCE_VERIFY_PERMISSIONS = ['finance.verify_slips'];
export const FINANCE_CONFIG_PERMISSIONS = ['finance.manage_fee_structures'];

/** @deprecated Prefer permission constants + `RequireAnyPermission`. Kept for pages that still reference role lists. */
export const FINANCE_STAFF_ROLES = ['finance_manager', 'accountant', 'school_admin', 'tenant_admin'];

export function RequireFinanceStaff({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={FINANCE_VERIFY_PERMISSIONS}>{children}</RequireAnyPermission>;
}

export function RequireFinanceConfig({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={FINANCE_CONFIG_PERMISSIONS}>{children}</RequireAnyPermission>;
}
