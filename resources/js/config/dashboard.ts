import { Permission } from './permissions';

/** One stat card on the staff dashboard — gated by Spatie permissions. */
export type DashboardWidget = {
    id: string;
    label: string;
    permissions: string[];
    /** Key on `DashboardSummary` (dot path for nested values). */
    valuePath: string;
    format?: 'money';
};

/**
 * Staff dashboard widgets — each appears only when the user holds at least
 * one listed permission (including direct grants from an admin).
 */
export const STAFF_DASHBOARD_WIDGETS: DashboardWidget[] = [
    {
        id: 'active_students',
        label: 'Active students',
        permissions: [
            Permission.tenant.manageSchools,
            Permission.finance.manageFeeStructures,
            Permission.academic.manageSubjects,
            Permission.academic.manageTimetable,
        ],
        valuePath: 'active_students',
    },
    {
        id: 'attendance_present',
        label: 'Present today',
        permissions: [Permission.attendance.viewClassSummary],
        valuePath: 'attendance_today.present',
    },
    {
        id: 'attendance_absent',
        label: 'Absent today',
        permissions: [Permission.attendance.viewClassSummary],
        valuePath: 'attendance_today.absent',
    },
    {
        id: 'slips_pending',
        label: 'Pending payment slips',
        permissions: [Permission.finance.verifySlips, Permission.finance.viewReports],
        valuePath: 'payment_slips.pending',
    },
    {
        id: 'slips_verified_today',
        label: 'Verified today',
        permissions: [Permission.finance.verifySlips, Permission.finance.viewReports],
        valuePath: 'payment_slips.verified_today_total',
        format: 'money',
    },
    {
        id: 'hostel_rooms',
        label: 'Hostel rooms',
        permissions: [
            Permission.hostel.manageRooms,
            Permission.hostel.manageAllocations,
            Permission.hostel.viewFinancialStatus,
        ],
        valuePath: 'hostel_occupancy.rooms',
    },
    {
        id: 'hostel_capacity',
        label: 'Hostel bed capacity',
        permissions: [
            Permission.hostel.manageRooms,
            Permission.hostel.manageAllocations,
            Permission.hostel.viewFinancialStatus,
        ],
        valuePath: 'hostel_occupancy.capacity',
    },
    {
        id: 'stores_low_stock',
        label: 'Low stock items',
        permissions: [
            Permission.stores.manageCatalog,
            Permission.stores.viewStock,
            Permission.stores.viewRequisitions,
            Permission.stores.approveRequisitions,
            Permission.stores.issueRequisitions,
        ],
        valuePath: 'stores.low_stock_items',
    },
    {
        id: 'stores_pending_reqs',
        label: 'Pending requisitions',
        permissions: [
            Permission.stores.manageCatalog,
            Permission.stores.viewStock,
            Permission.stores.viewRequisitions,
            Permission.stores.approveRequisitions,
            Permission.stores.issueRequisitions,
        ],
        valuePath: 'stores.pending_requisitions',
    },
];

/** Flat list of every permission that can unlock at least one staff widget. */
export const STAFF_DASHBOARD_PERMISSIONS = [
    ...new Set(STAFF_DASHBOARD_WIDGETS.flatMap((widget) => widget.permissions)),
];

/** Parent dashboard — per-child fee / slip summary (PRD §5.10). */
export const PARENT_DASHBOARD_PERMISSIONS = [Permission.students.viewOwnChildren];

function readPath(obj: Record<string, unknown>, path: string): unknown {
    return path.split('.').reduce<unknown>((current, key) => {
        if (current && typeof current === 'object' && key in (current as object)) {
            return (current as Record<string, unknown>)[key];
        }

        return undefined;
    }, obj);
}

export function getWidgetValue(summary: Record<string, unknown>, widget: DashboardWidget): string | number | null {
    const value = readPath(summary, widget.valuePath);

    if (value === undefined || value === null) {
        return null;
    }

    return value as string | number;
}
