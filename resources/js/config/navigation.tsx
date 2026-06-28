import {
    LayoutDashboard,
    GraduationCap,
    BookOpen,
    ClipboardList,
    CalendarCheck,
    ListChecks,
    Star,
    FileText,
    Receipt,
    Wallet,
    ClipboardCheck,
    FileSpreadsheet,
    Landmark,
    LayoutGrid,
    CalendarRange,
    UsersRound,
    Building2,
    BedDouble,
    UtensilsCrossed,
    CalendarOff,
    DoorOpen,
    ShieldCheck,
    History,
    Package,
    AlertTriangle,
    ClipboardPenLine,
    ShoppingCart,
    Truck,
    ArrowLeftRight,
} from 'lucide-react';

export type NavItem = {
    label: string;
    path: string;
    icon: JSX.Element;
    /**
     * User must hold at least one of these Spatie permissions (from `/me`).
     * `null` = visible to every authenticated tenant user (e.g. Dashboard).
     */
    permissions: string[] | null;
};

export type NavSection = {
    label: string;
    /** When true, section is shown only to platform admins (ADR-0008). */
    platformOnly?: boolean;
    items: NavItem[];
};

/**
 * Sidebar menu config — each item maps to the permission(s) that unlock it.
 * When an admin grants a permission directly (outside the default role
 * matrix), the matching item appears automatically after the next `/me`
 * refresh. Hiding items is UX only; the API still authorizes (RULES §8).
 */
export const NAV_SECTIONS: NavSection[] = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', path: '/', icon: <LayoutDashboard size={20} />, permissions: null }],
    },
    {
        label: 'Academics',
        items: [
            {
                label: 'Students',
                path: '/students',
                icon: <GraduationCap size={20} />,
                permissions: ['students.view_basic_info', 'students.view_own_children'],
            },
            {
                label: 'Classes',
                path: '/classes',
                icon: <LayoutGrid size={20} />,
                permissions: ['academic.manage_classes', 'academic.manage_class'],
            },
            {
                label: 'Academic Sessions',
                path: '/academic-sessions',
                icon: <CalendarRange size={20} />,
                permissions: ['academic.manage_timetable'],
            },
            {
                label: 'Teacher Assignments',
                path: '/teacher-assignments',
                icon: <UsersRound size={20} />,
                permissions: ['academic.manage_timetable'],
            },
            {
                label: 'Subjects',
                path: '/subjects',
                icon: <BookOpen size={20} />,
                permissions: ['academic.manage_subjects'],
            },
            {
                label: 'Assignments',
                path: '/assignments',
                icon: <ClipboardList size={20} />,
                permissions: ['academic.manage_assignments', 'academic.view_assignments'],
            },
            {
                label: 'Attendance',
                path: '/attendance',
                icon: <CalendarCheck size={20} />,
                permissions: ['attendance.take', 'attendance.view_class_summary'],
            },
            {
                label: 'Assessments',
                path: '/assessments',
                icon: <ListChecks size={20} />,
                permissions: ['assessment.manage_grading'],
            },
            {
                label: 'Mark Entry',
                path: '/assessments/marks',
                icon: <Star size={20} />,
                permissions: ['assessment.enter_marks'],
            },
            {
                label: 'Report Cards',
                path: '/report-cards',
                icon: <FileText size={20} />,
                permissions: [
                    'assessment.assemble_report_card',
                    'academic.view_child_results',
                    'assessment.view_own_results',
                ],
            },
        ],
    },
    {
        label: 'Finance',
        items: [
            {
                label: 'Submit Payment Slip',
                path: '/finance/submit-slip',
                icon: <Wallet size={20} />,
                permissions: ['finance.submit_slips'],
            },
            {
                label: 'My Payment Slips',
                path: '/finance/my-slips',
                icon: <Receipt size={20} />,
                permissions: ['finance.view_own_payments', 'finance.submit_slips'],
            },
            {
                label: 'Verification Queue',
                path: '/finance/verification-queue',
                icon: <ClipboardCheck size={20} />,
                permissions: ['finance.verify_slips'],
            },
            {
                label: 'Fee Structures',
                path: '/finance/fee-structures',
                icon: <FileSpreadsheet size={20} />,
                permissions: ['finance.manage_fee_structures'],
            },
            {
                label: 'Payment Methods',
                path: '/finance/payment-methods',
                icon: <Landmark size={20} />,
                permissions: ['finance.manage_fee_structures'],
            },
        ],
    },
    {
        label: 'Platform',
        platformOnly: true,
        items: [
            { label: 'Tenants', path: '/platform/tenants', icon: <ShieldCheck size={20} />, permissions: null },
            { label: 'Audit Log', path: '/platform/audit-logs', icon: <History size={20} />, permissions: null },
        ],
    },
    {
        label: 'Hostel',
        items: [
            {
                label: 'Hostels',
                path: '/hostels',
                icon: <Building2 size={20} />,
                permissions: ['hostel.manage_rooms'],
            },
            {
                label: 'Hostel Rooms',
                path: '/hostel-rooms',
                icon: <BedDouble size={20} />,
                permissions: ['hostel.manage_rooms'],
            },
            {
                label: 'Allocations',
                path: '/hostel-allocations',
                icon: <DoorOpen size={20} />,
                permissions: ['hostel.manage_allocations', 'students.view_own_children'],
            },
            {
                label: 'Meal Plans',
                path: '/meal-plans',
                icon: <UtensilsCrossed size={20} />,
                permissions: ['hostel.meal_management'],
            },
            {
                label: 'Leave Requests',
                path: '/hostel-leave-requests',
                icon: <CalendarOff size={20} />,
                permissions: ['hostel.approve_leave', 'students.view_own_children'],
            },
        ],
    },
    {
        label: 'Stores',
        items: [
            {
                label: 'Inventory Catalog',
                path: '/stores/inventory',
                icon: <Package size={20} />,
                permissions: ['stores.manage_catalog'],
            },
            {
                label: 'Low Stock',
                path: '/stores/low-stock',
                icon: <AlertTriangle size={20} />,
                permissions: ['stores.view_stock'],
            },
            {
                label: 'My Requisitions',
                path: '/stores/my-requisitions',
                icon: <ClipboardPenLine size={20} />,
                permissions: ['stores.create_requisitions'],
            },
            {
                label: 'Requisition Queue',
                path: '/stores/requisition-queue',
                icon: <ClipboardCheck size={20} />,
                permissions: ['stores.approve_requisitions', 'stores.issue_requisitions', 'stores.view_requisitions'],
            },
            {
                label: 'Purchase Requests',
                path: '/stores/purchase-requests',
                icon: <ShoppingCart size={20} />,
                permissions: ['stores.create_purchase_requests'],
            },
            {
                label: 'Procurement Queue',
                path: '/stores/procurement-queue',
                icon: <FileSpreadsheet size={20} />,
                permissions: ['stores.approve_purchases'],
            },
            {
                label: 'Fulfillment',
                path: '/stores/fulfillment',
                icon: <Truck size={20} />,
                permissions: ['stores.fulfill_purchases'],
            },
            {
                label: 'Stock Movements',
                path: '/stores/stock-movements',
                icon: <ArrowLeftRight size={20} />,
                permissions: ['stores.view_movements'],
            },
        ],
    },
];

/** Lookup table for route guards — derived from the nav config. */
export const ROUTE_PERMISSIONS: Record<string, string[] | null> = Object.fromEntries(
    NAV_SECTIONS.flatMap((section) => section.items.map((item) => [item.path, item.permissions])),
);
