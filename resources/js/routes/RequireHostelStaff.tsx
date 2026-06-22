import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../app/AuthProvider';

/** Roles that manage hostels/rooms/meal plans and decide leave requests. */
export const HOSTEL_STAFF_ROLES = ['hostel_manager', 'school_admin', 'tenant_admin'];

/**
 * Route guard for hostel-staff-only pages (hostels, hostel rooms, meal
 * plans) — mirrors RequireFinanceStaff. Redirects a non-staff user back to
 * the dashboard; this is UX only, the API still authorizes every action
 * server-side (RULES §8).
 */
export function RequireHostelStaff({ children }: { children: ReactNode }) {
    const { user } = useAuth();
    const isHostelStaff = Boolean(user?.roles.some((role) => HOSTEL_STAFF_ROLES.includes(role)));

    if (!isHostelStaff) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}
