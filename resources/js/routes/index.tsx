import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
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
import { TenantsPage } from '../features/platform/pages/TenantsPage';
import { AuditLogPage } from '../features/platform/pages/AuditLogPage';
import { RequireAuth } from './RequireAuth';
import { RequireFinanceStaff } from './RequireFinanceStaff';
import { RequireHostelStaff } from './RequireHostelStaff';
import { RequirePlatformAdmin } from './RequirePlatformAdmin';

/**
 * Phase 1/2 route table: a public /login route plus the authenticated SIS
 * (students), academics (subjects, assignments), attendance, and assessment
 * (assessments, mark entry, report cards) feature pages. Each authenticated
 * route is wrapped in <RequireAuth>; finer <RequirePermission> gating can be
 * layered in once per-route permission requirements are settled (today the
 * pages self-gate create/edit actions off `user.roles`/`user.permissions`,
 * and the API authorizes regardless).
 */
export function AppRoutes() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
                path="/"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <DashboardPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/students"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <StudentsListPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
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
                            <WardDetailPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/classes"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <ClassesPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/academic-sessions"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <AcademicSessionsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/teacher-assignments"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <TeacherAssignmentsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/subjects"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <SubjectsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/assignments"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <AssignmentsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/attendance"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <AttendanceTakerPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/assessments"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <AssessmentsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/assessments/marks"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <MarkEntryPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/report-cards"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <ReportCardPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/submit-slip"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <SubmitSlipPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/my-slips"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <MySlipsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/my-slips/:id"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <SlipDetailPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/verification-queue"
                element={
                    <RequireAuth>
                        <RequireFinanceStaff>
                            <AppLayout>
                                <VerificationQueuePage />
                            </AppLayout>
                        </RequireFinanceStaff>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/fee-structures"
                element={
                    <RequireAuth>
                        <RequireFinanceStaff>
                            <AppLayout>
                                <FeeStructuresPage />
                            </AppLayout>
                        </RequireFinanceStaff>
                    </RequireAuth>
                }
            />
            <Route
                path="/finance/payment-methods"
                element={
                    <RequireAuth>
                        <RequireFinanceStaff>
                            <AppLayout>
                                <PaymentMethodsPage />
                            </AppLayout>
                        </RequireFinanceStaff>
                    </RequireAuth>
                }
            />
            <Route
                path="/hostels"
                element={
                    <RequireAuth>
                        <RequireHostelStaff>
                            <AppLayout>
                                <HostelsPage />
                            </AppLayout>
                        </RequireHostelStaff>
                    </RequireAuth>
                }
            />
            <Route
                path="/hostel-rooms"
                element={
                    <RequireAuth>
                        <RequireHostelStaff>
                            <AppLayout>
                                <HostelRoomsPage />
                            </AppLayout>
                        </RequireHostelStaff>
                    </RequireAuth>
                }
            />
            <Route
                path="/hostel-allocations"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <HostelAllocationsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
            <Route
                path="/meal-plans"
                element={
                    <RequireAuth>
                        <RequireHostelStaff>
                            <AppLayout>
                                <MealPlansPage />
                            </AppLayout>
                        </RequireHostelStaff>
                    </RequireAuth>
                }
            />
            <Route
                path="/hostel-leave-requests"
                element={
                    <RequireAuth>
                        <AppLayout>
                            <HostelLeaveRequestsPage />
                        </AppLayout>
                    </RequireAuth>
                }
            />
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
