import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
import { ROUTE_PERMISSIONS } from '../config/navigation';
import { useRoutePermissions } from '../app/NavigationProvider';
import { LoginPage } from '../features/auth/pages/LoginPage';
import { DashboardPage } from '../features/dashboard/pages/DashboardPage';
import { StudentsListPage } from '../features/students/pages/StudentsListPage';
import { StudentAdmissionPage } from '../features/students/pages/StudentAdmissionPage';
import { StudentDetailPage } from '../features/students/pages/StudentDetailPage';
import { WardDetailPage } from '../features/students/pages/WardDetailPage';
import { SubjectsPage } from '../features/academics/pages/SubjectsPage';
import { AssignmentsPage } from '../features/academics/pages/AssignmentsPage';
import { ClassesPage } from '../features/academics/pages/ClassesPage';
import { AcademicSessionsPage } from '../features/academics/pages/AcademicSessionsPage';
import { TeacherAssignmentsPage } from '../features/academics/pages/TeacherAssignmentsPage';
import { AttendanceTakerPage } from '../features/attendance/pages/AttendanceTakerPage';
import { AssessmentsPage } from '../features/assessment/pages/AssessmentsPage';
import { MarkEntryPage } from '../features/assessment/pages/MarkEntryPage';
import { ReportCardPage } from '../features/assessment/pages/ReportCardPage';
import { SubmitSlipPage } from '../features/finance/pages/SubmitSlipPage';
import { MySlipsPage } from '../features/finance/pages/MySlipsPage';
import { SlipDetailPage } from '../features/finance/pages/SlipDetailPage';
import { VerificationQueuePage } from '../features/finance/pages/VerificationQueuePage';
import { FeeStructuresPage } from '../features/finance/pages/FeeStructuresPage';
import { PaymentMethodsPage } from '../features/finance/pages/PaymentMethodsPage';
import { HostelsPage } from '../features/hostel/pages/HostelsPage';
import { HostelRoomsPage } from '../features/hostel/pages/HostelRoomsPage';
import { HostelAllocationsPage } from '../features/hostel/pages/HostelAllocationsPage';
import { MealPlansPage } from '../features/hostel/pages/MealPlansPage';
import { HostelLeaveRequestsPage } from '../features/hostel/pages/HostelLeaveRequestsPage';
import { InventoryItemsPage } from '../features/stores/pages/InventoryItemsPage';
import { LowStockPage } from '../features/stores/pages/LowStockPage';
import { MyRequisitionsPage } from '../features/stores/pages/MyRequisitionsPage';
import { RequisitionQueuePage } from '../features/stores/pages/RequisitionQueuePage';
import { PurchaseRequestsPage } from '../features/stores/pages/PurchaseRequestsPage';
import { ProcurementQueuePage } from '../features/stores/pages/ProcurementQueuePage';
import { FulfillmentPage } from '../features/stores/pages/FulfillmentPage';
import { StockMovementsPage } from '../features/stores/pages/StockMovementsPage';
import { TenantsPage } from '../features/platform/pages/TenantsPage';
import { AuditLogPage } from '../features/platform/pages/AuditLogPage';
import { PlatformSettingsPage } from '../features/platform/pages/PlatformSettingsPage';
import { SchoolsAdminPage } from '../features/admin/pages/SchoolsAdminPage';
import { TenantSettingsPage } from '../features/admin/pages/TenantSettingsPage';
import { UserRolesPage } from '../features/admin/pages/UserRolesPage';
import { RolesPage } from '../features/admin/pages/RolesPage';
import { NavigationEditorPage } from '../features/admin/pages/NavigationEditorPage';
import { PlatformNavigationPage } from '../features/platform/pages/PlatformNavigationPage';
import { RequireAuth } from './RequireAuth';
import { RequireAnyPermission } from './RequireAnyPermission';
import { RequireFinanceConfig, RequireFinanceStaff } from './RequireFinanceStaff';
import { RequireHostelStaff } from './RequireHostelStaff';
import { RequirePlatformAdmin } from './RequirePlatformAdmin';
import {
    RequireFulfillmentStaff,
    RequireKitchenStaff,
    RequireProcurementStaff,
    RequirePurchaseRequests,
    RequireRequisitionQueue,
    RequireStockMovements,
    RequireStoreCatalog,
    RequireStoreStock,
} from './RequireStoresStaff';

/**
 * Route table with permission guards aligned to the live navigation API
 * (fallback: `config/navigation.tsx`). Menu visibility and route access
 * share the same permission lists.
 */
export function AppRoutes() {
    const { routePermissions } = useRoutePermissions();

    function permissionGuard(path: string, page: JSX.Element) {
        const permissions = routePermissions[path] ?? ROUTE_PERMISSIONS[path];

        if (permissions === undefined || permissions === null) {
            return page;
        }

        return <RequireAnyPermission permissions={permissions}>{page}</RequireAnyPermission>;
    }

    function authedPage(path: string, page: JSX.Element) {
        return (
            <RequireAuth>
                <AppLayout>{permissionGuard(path, page)}</AppLayout>
            </RequireAuth>
        );
    }

    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/" element={authedPage('/', <DashboardPage />)} />
            <Route path="/students" element={authedPage('/students', <StudentsListPage />)} />
            <Route
                path="/students/new"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <StudentAdmissionPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/students/:id"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <StudentDetailPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/my-children/:studentId"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireAnyPermission permissions={['students.view_own_children']}>
                                <WardDetailPage />
                            </RequireAnyPermission>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route path="/classes" element={authedPage('/classes', <ClassesPage />)} />
            <Route path="/academic-sessions" element={authedPage('/academic-sessions', <AcademicSessionsPage />)} />
            <Route path="/teacher-assignments" element={authedPage('/teacher-assignments', <TeacherAssignmentsPage />)} />
            <Route path="/subjects" element={authedPage('/subjects', <SubjectsPage />)} />
            <Route path="/assignments" element={authedPage('/assignments', <AssignmentsPage />)} />
            <Route path="/attendance" element={authedPage('/attendance', <AttendanceTakerPage />)} />
            <Route path="/assessments" element={authedPage('/assessments', <AssessmentsPage />)} />
            <Route path="/assessments/marks" element={authedPage('/assessments/marks', <MarkEntryPage />)} />
            <Route path="/report-cards" element={authedPage('/report-cards', <ReportCardPage />)} />
            <Route path="/finance/submit-slip" element={authedPage('/finance/submit-slip', <SubmitSlipPage />)} />
            <Route path="/finance/my-slips" element={authedPage('/finance/my-slips', <MySlipsPage />)} />
            <Route
                path="/finance/my-slips/:id"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireAnyPermission permissions={['finance.view_own_payments', 'finance.submit_slips']}>
                                <SlipDetailPage />
                            </RequireAnyPermission>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/verification-queue"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireFinanceStaff>
                                <VerificationQueuePage />
                            </RequireFinanceStaff>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/fee-structures"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireFinanceConfig>
                                <FeeStructuresPage />
                            </RequireFinanceConfig>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/payment-methods"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireFinanceConfig>
                                <PaymentMethodsPage />
                            </RequireFinanceConfig>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/hostels"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireHostelStaff>
                                <HostelsPage />
                            </RequireHostelStaff>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/hostel-rooms"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireHostelStaff>
                                <HostelRoomsPage />
                            </RequireHostelStaff>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route path="/hostel-allocations" element={authedPage('/hostel-allocations', <HostelAllocationsPage />)} />
            <Route
                path="/meal-plans"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireAnyPermission permissions={['hostel.meal_management']}>
                                <MealPlansPage />
                            </RequireAnyPermission>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route path="/hostel-leave-requests" element={authedPage('/hostel-leave-requests', <HostelLeaveRequestsPage />)} />
            <Route
                path="/stores/inventory"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireStoreCatalog>
                                <InventoryItemsPage />
                            </RequireStoreCatalog>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/low-stock"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireStoreStock>
                                <LowStockPage />
                            </RequireStoreStock>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/my-requisitions"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireKitchenStaff>
                                <MyRequisitionsPage />
                            </RequireKitchenStaff>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/requisition-queue"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireRequisitionQueue>
                                <RequisitionQueuePage />
                            </RequireRequisitionQueue>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/purchase-requests"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequirePurchaseRequests>
                                <PurchaseRequestsPage />
                            </RequirePurchaseRequests>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/procurement-queue"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireProcurementStaff>
                                <ProcurementQueuePage />
                            </RequireProcurementStaff>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/fulfillment"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireFulfillmentStaff>
                                <FulfillmentPage />
                            </RequireFulfillmentStaff>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/stores/stock-movements"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <RequireStockMovements>
                                <StockMovementsPage />
                            </RequireStockMovements>
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route path="/admin/schools" element={authedPage('/admin/schools', <SchoolsAdminPage />)} />
            <Route path="/admin/settings" element={authedPage('/admin/settings', <TenantSettingsPage />)} />
            <Route path="/admin/users" element={authedPage('/admin/users', <UserRolesPage />)} />
            <Route path="/admin/roles" element={authedPage('/admin/roles', <RolesPage />)} />
            <Route path="/admin/navigation" element={authedPage('/admin/navigation', <NavigationEditorPage />)} />
            <Route
                path="/platform/tenants"
                element={
                    <RequireAuth>
                        <RequirePlatformAdmin>
                            <AppLayout>
                                <TenantsPage />
                            </AppLayout>
                        </RequirePlatformAdmin>
                    </RequireAuth>
                }
            />
            <Route
                path="/platform/navigation"
                element={
                    <RequireAuth>
                        <RequirePlatformAdmin>
                            <AppLayout>
                                <PlatformNavigationPage />
                            </AppLayout>
                        </RequirePlatformAdmin>
                    </RequireAuth>
                }
            />
            <Route
                path="/platform/settings"
                element={
                    <RequireAuth>
                        <RequirePlatformAdmin>
                            <AppLayout>
                                <PlatformSettingsPage />
                            </AppLayout>
                        </RequirePlatformAdmin>
                    </RequireAuth>
                }
            />
            <Route
                path="/platform/audit-logs"
                element={
                    <RequireAuth>
                        <RequirePlatformAdmin>
                            <AppLayout>
                                <AuditLogPage />
                            </AppLayout>
                        </RequirePlatformAdmin>
                    </RequireAuth>
                }
            />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
