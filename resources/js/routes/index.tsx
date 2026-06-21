import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
import { LoginPage } from '../features/auth/pages/LoginPage';
import { DashboardPage } from '../features/dashboard/pages/DashboardPage';
import { StudentsListPage } from '../features/students/pages/StudentsListPage';
import { StudentAdmissionPage } from '../features/students/pages/StudentAdmissionPage';
import { StudentDetailPage } from '../features/students/pages/StudentDetailPage';
import { SubjectsPage } from '../features/academics/pages/SubjectsPage';
import { AssignmentsPage } from '../features/academics/pages/AssignmentsPage';
import { AttendanceTakerPage } from '../features/attendance/pages/AttendanceTakerPage';
import { AssessmentsPage } from '../features/assessment/pages/AssessmentsPage';
import { MarkEntryPage } from '../features/assessment/pages/MarkEntryPage';
import { ReportCardPage } from '../features/assessment/pages/ReportCardPage';
import { RequireAuth } from './RequireAuth';

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
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
