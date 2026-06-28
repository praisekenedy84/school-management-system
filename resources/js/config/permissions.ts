/**
 * Spatie permission names and UI action gates — mirrors
 * `RoleAndPermissionSeeder` / RULES.md §5. Nav items and in-page actions
 * import from here so a granted permission reveals both the menu entry and
 * the related buttons.
 */

/** Individual permission string constants (for single checks). */
export const Permission = {
    tenant: {
        manageSchools: 'tenant.manage_schools',
        manageSettings: 'tenant.manage_settings',
        manageBranding: 'tenant.manage_branding',
        manageBilling: 'tenant.manage_billing',
    },
    users: {
        manageRoles: 'users.manage_roles',
    },
    rbac: {
        manageRoles: 'rbac.manage_roles',
    },
    students: {
        viewBasicInfo: 'students.view_basic_info',
        viewOwnChildren: 'students.view_own_children',
    },
    academic: {
        manageSubjects: 'academic.manage_subjects',
        manageClasses: 'academic.manage_classes',
        manageClass: 'academic.manage_class',
        manageTimetable: 'academic.manage_timetable',
        manageAssignments: 'academic.manage_assignments',
        viewAssignments: 'academic.view_assignments',
        viewChildResults: 'academic.view_child_results',
    },
    attendance: {
        take: 'attendance.take',
        viewClassSummary: 'attendance.view_class_summary',
    },
    assessment: {
        manageGrading: 'assessment.manage_grading',
        publishResults: 'assessment.publish_results',
        enterMarks: 'assessment.enter_marks',
        assembleReportCard: 'assessment.assemble_report_card',
        viewOwnResults: 'assessment.view_own_results',
    },
    finance: {
        verifySlips: 'finance.verify_slips',
        manageFeeStructures: 'finance.manage_fee_structures',
        submitSlips: 'finance.submit_slips',
        viewOwnPayments: 'finance.view_own_payments',
        viewReports: 'finance.view_reports',
    },
    hostel: {
        manageRooms: 'hostel.manage_rooms',
        manageAllocations: 'hostel.manage_allocations',
        mealManagement: 'hostel.meal_management',
        approveLeave: 'hostel.approve_leave',
        viewFinancialStatus: 'hostel.view_financial_status',
    },
    stores: {
        manageCatalog: 'stores.manage_catalog',
        viewStock: 'stores.view_stock',
        createRequisitions: 'stores.create_requisitions',
        approveRequisitions: 'stores.approve_requisitions',
        issueRequisitions: 'stores.issue_requisitions',
        viewRequisitions: 'stores.view_requisitions',
        createPurchaseRequests: 'stores.create_purchase_requests',
        approvePurchases: 'stores.approve_purchases',
        fulfillPurchases: 'stores.fulfill_purchases',
        viewMovements: 'stores.view_movements',
    },
} as const;

/**
 * Action gates: user needs **at least one** permission in the array.
 * Where no single Spatie name exists yet (e.g. tenant/school-admin-only
 * screens), we use permissions those roles hold but academic staff do not.
 */
export const ActionPermissions = {
    /** Tenant / school admin student admission (no dedicated Spatie name yet). */
    admitStudents: [Permission.tenant.manageSchools, Permission.finance.manageFeeStructures],
    manageSubjects: [Permission.academic.manageSubjects],
    manageClasses: [Permission.academic.manageClasses, Permission.academic.manageClass],
    manageAcademicSessions: [Permission.tenant.manageSchools, Permission.finance.manageFeeStructures],
    manageTeacherAssignments: [Permission.tenant.manageSchools, Permission.finance.manageFeeStructures],
    createAssignments: [Permission.academic.manageAssignments],
    takeAttendance: [Permission.attendance.take, Permission.attendance.viewClassSummary],
    manageAssessments: [Permission.assessment.manageGrading],
    publishResults: [Permission.assessment.publishResults],
    enterMarks: [Permission.assessment.enterMarks],
    verifySlips: [Permission.finance.verifySlips],
    manageFeeConfig: [Permission.finance.manageFeeStructures],
    manageHostelRooms: [Permission.hostel.manageRooms],
    manageHostelAllocations: [Permission.hostel.manageAllocations],
    manageMealPlans: [Permission.hostel.mealManagement],
    approveHostelLeave: [Permission.hostel.approveLeave],
    requestHostelLeave: [Permission.students.viewOwnChildren],
    manageInventoryCatalog: [Permission.stores.manageCatalog],
    viewStockAlerts: [Permission.stores.viewStock],
    createRequisitions: [Permission.stores.createRequisitions],
    approveRequisitions: [Permission.stores.approveRequisitions],
    issueRequisitions: [Permission.stores.issueRequisitions],
    viewRequisitionQueue: [
        Permission.stores.approveRequisitions,
        Permission.stores.issueRequisitions,
        Permission.stores.viewRequisitions,
    ],
    createPurchaseRequests: [Permission.stores.createPurchaseRequests],
    approvePurchases: [Permission.stores.approvePurchases],
    fulfillPurchases: [Permission.stores.fulfillPurchases],
    viewStockMovements: [Permission.stores.viewMovements],
    viewStudentsList: [Permission.students.viewBasicInfo, Permission.students.viewOwnChildren],
    manageTenantSchools: [Permission.tenant.manageSchools],
    manageTenantSettings: [Permission.tenant.manageSettings, Permission.tenant.manageBranding, Permission.tenant.manageBilling],
    manageUserRoles: [Permission.users.manageRoles],
} as const;

export type ActionPermissionKey = keyof typeof ActionPermissions;
