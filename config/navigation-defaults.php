<?php

declare(strict_types=1);

/**
 * Default sidebar structure — seeded into each tenant (and central platform)
 * on provision. Admins may customize labels, order, visibility, and
 * permission gates via the navigation admin UI.
 */
return [
    'tenant' => [
        [
            'label' => 'Overview',
            'platform_only' => false,
            'items' => [
                ['label' => 'Dashboard', 'path' => '/', 'icon' => 'LayoutDashboard', 'permissions' => null],
            ],
        ],
        [
            'label' => 'Academics',
            'platform_only' => false,
            'items' => [
                ['label' => 'Students', 'path' => '/students', 'icon' => 'GraduationCap', 'permissions' => ['students.view_basic_info', 'students.view_own_children']],
                ['label' => 'Classes', 'path' => '/classes', 'icon' => 'LayoutGrid', 'permissions' => ['academic.manage_classes', 'academic.manage_class']],
                ['label' => 'Academic Sessions', 'path' => '/academic-sessions', 'icon' => 'CalendarRange', 'permissions' => ['academic.manage_timetable']],
                ['label' => 'Teacher Assignments', 'path' => '/teacher-assignments', 'icon' => 'UsersRound', 'permissions' => ['academic.manage_timetable']],
                ['label' => 'Subjects', 'path' => '/subjects', 'icon' => 'BookOpen', 'permissions' => ['academic.manage_subjects']],
                ['label' => 'Assignments', 'path' => '/assignments', 'icon' => 'ClipboardList', 'permissions' => ['academic.manage_assignments', 'academic.view_assignments']],
                ['label' => 'Attendance', 'path' => '/attendance', 'icon' => 'CalendarCheck', 'permissions' => ['attendance.take', 'attendance.view_class_summary']],
                ['label' => 'Assessments', 'path' => '/assessments', 'icon' => 'ListChecks', 'permissions' => ['assessment.manage_grading']],
                ['label' => 'Mark Entry', 'path' => '/assessments/marks', 'icon' => 'Star', 'permissions' => ['assessment.enter_marks']],
                ['label' => 'Report Cards', 'path' => '/report-cards', 'icon' => 'FileText', 'permissions' => ['assessment.assemble_report_card', 'academic.view_child_results', 'assessment.view_own_results']],
            ],
        ],
        [
            'label' => 'Finance',
            'platform_only' => false,
            'items' => [
                ['label' => 'Submit Payment Slip', 'path' => '/finance/submit-slip', 'icon' => 'Wallet', 'permissions' => ['finance.submit_slips']],
                ['label' => 'My Payment Slips', 'path' => '/finance/my-slips', 'icon' => 'Receipt', 'permissions' => ['finance.view_own_payments', 'finance.submit_slips']],
                ['label' => 'Verification Queue', 'path' => '/finance/verification-queue', 'icon' => 'ClipboardCheck', 'permissions' => ['finance.verify_slips']],
                ['label' => 'Fee Structures', 'path' => '/finance/fee-structures', 'icon' => 'FileSpreadsheet', 'permissions' => ['finance.manage_fee_structures']],
                ['label' => 'Payment Methods', 'path' => '/finance/payment-methods', 'icon' => 'Landmark', 'permissions' => ['finance.manage_fee_structures']],
            ],
        ],
        [
            'label' => 'Administration',
            'platform_only' => false,
            'items' => [
                ['label' => 'Schools', 'path' => '/admin/schools', 'icon' => 'School', 'permissions' => ['tenant.manage_schools']],
                ['label' => 'Tenant Settings', 'path' => '/admin/settings', 'icon' => 'Settings', 'permissions' => ['tenant.manage_settings', 'tenant.manage_branding', 'tenant.manage_billing']],
                ['label' => 'Users & Roles', 'path' => '/admin/users', 'icon' => 'Users', 'permissions' => ['users.manage_roles']],
                ['label' => 'Role Permissions', 'path' => '/admin/roles', 'icon' => 'ShieldCheck', 'permissions' => ['rbac.manage_roles']],
                ['label' => 'Menu Editor', 'path' => '/admin/navigation', 'icon' => 'LayoutGrid', 'permissions' => ['tenant.manage_navigation']],
            ],
        ],
        [
            'label' => 'Hostel',
            'platform_only' => false,
            'items' => [
                ['label' => 'Hostels', 'path' => '/hostels', 'icon' => 'Building2', 'permissions' => ['hostel.manage_rooms']],
                ['label' => 'Hostel Rooms', 'path' => '/hostel-rooms', 'icon' => 'BedDouble', 'permissions' => ['hostel.manage_rooms']],
                ['label' => 'Allocations', 'path' => '/hostel-allocations', 'icon' => 'DoorOpen', 'permissions' => ['hostel.manage_allocations', 'students.view_own_children']],
                ['label' => 'Meal Plans', 'path' => '/meal-plans', 'icon' => 'UtensilsCrossed', 'permissions' => ['hostel.meal_management']],
                ['label' => 'Leave Requests', 'path' => '/hostel-leave-requests', 'icon' => 'CalendarOff', 'permissions' => ['hostel.approve_leave', 'students.view_own_children']],
            ],
        ],
        [
            'label' => 'Stores',
            'platform_only' => false,
            'items' => [
                ['label' => 'Inventory Catalog', 'path' => '/stores/inventory', 'icon' => 'Package', 'permissions' => ['stores.manage_catalog']],
                ['label' => 'Low Stock', 'path' => '/stores/low-stock', 'icon' => 'AlertTriangle', 'permissions' => ['stores.view_stock']],
                ['label' => 'My Requisitions', 'path' => '/stores/my-requisitions', 'icon' => 'ClipboardPenLine', 'permissions' => ['stores.create_requisitions']],
                ['label' => 'Requisition Queue', 'path' => '/stores/requisition-queue', 'icon' => 'ClipboardCheck', 'permissions' => ['stores.approve_requisitions', 'stores.issue_requisitions', 'stores.view_requisitions']],
                ['label' => 'Purchase Requests', 'path' => '/stores/purchase-requests', 'icon' => 'ShoppingCart', 'permissions' => ['stores.create_purchase_requests']],
                ['label' => 'Procurement Queue', 'path' => '/stores/procurement-queue', 'icon' => 'FileSpreadsheet', 'permissions' => ['stores.approve_purchases']],
                ['label' => 'Fulfillment', 'path' => '/stores/fulfillment', 'icon' => 'Truck', 'permissions' => ['stores.fulfill_purchases']],
                ['label' => 'Stock Movements', 'path' => '/stores/stock-movements', 'icon' => 'ArrowLeftRight', 'permissions' => ['stores.view_movements']],
            ],
        ],
    ],
    'platform' => [
        [
            'label' => 'Platform',
            'platform_only' => true,
            'items' => [
                ['label' => 'Tenants', 'path' => '/platform/tenants', 'icon' => 'ShieldCheck', 'permissions' => null],
                ['label' => 'Platform Settings', 'path' => '/platform/settings', 'icon' => 'Settings', 'permissions' => null],
                ['label' => 'Menu Editor', 'path' => '/platform/navigation', 'icon' => 'LayoutGrid', 'permissions' => null],
                ['label' => 'Audit Log', 'path' => '/platform/audit-logs', 'icon' => 'History', 'permissions' => null],
            ],
        ],
    ],
];
