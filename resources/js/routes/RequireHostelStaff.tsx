import type { ReactNode } from 'react';
import { RequireAnyPermission } from './RequireAnyPermission';

export const HOSTEL_MANAGE_ROOMS_PERMISSIONS = ['hostel.manage_rooms'];

/** @deprecated Prefer permission constants + `RequireAnyPermission`. */
export const HOSTEL_STAFF_ROLES = ['hostel_manager', 'school_admin', 'tenant_admin'];

export function RequireHostelStaff({ children }: { children: ReactNode }) {
    return <RequireAnyPermission permissions={HOSTEL_MANAGE_ROOMS_PERMISSIONS}>{children}</RequireAnyPermission>;
}
