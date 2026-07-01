import { Link as RouterLink } from 'react-router-dom';
import { ShieldCheck, History, Loader2 } from 'lucide-react';
import {
    PARENT_DASHBOARD_PERMISSIONS,
    STAFF_DASHBOARD_PERMISSIONS,
    STAFF_DASHBOARD_WIDGETS,
    getWidgetValue,
} from '@/config/dashboard';
import { NAV_SECTIONS } from '@/config/navigation';
import { usePermissions } from '@/lib/usePermissions';
import { useDashboardSummary, useDashboardWards } from '../api/useDashboard';
import { formatMoney } from '@/lib/formatMoney';
import { getErrorMessage } from '@/lib/getErrorMessage';
import { PageHeader } from '@/components/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { DashboardSummary } from '../types/dashboard';

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function StaffDashboard({ enabled }: { enabled: boolean }) {
    const { canAny } = usePermissions();
    const { data, isPending, isError, error } = useDashboardSummary(enabled);

    const visibleWidgets = STAFF_DASHBOARD_WIDGETS.filter((widget) => canAny(widget.permissions));

    if (!enabled || visibleWidgets.length === 0) {
        return null;
    }

    if (isPending) {
        return (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-24 rounded-xl" />
                ))}
            </div>
        );
    }

    if (isError || !data) {
        return (
            <Alert variant="destructive">
                <AlertDescription>{getErrorMessage(error, 'Could not load the dashboard summary.')}</AlertDescription>
            </Alert>
        );
    }

    const summary = data as DashboardSummary & Record<string, unknown>;

    return (
        <div className="space-y-4">
            {summary.current_academic_session && (
                <Badge variant="secondary" className="text-sm">
                    Current session: {summary.current_academic_session}
                </Badge>
            )}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {visibleWidgets.map((widget) => {
                    const raw = getWidgetValue(summary, widget);
                    if (raw === null) return null;
                    const value = widget.format === 'money' ? formatMoney(raw as number) : raw;
                    return <StatCard key={widget.id} label={widget.label} value={value} />;
                })}
            </div>
        </div>
    );
}

function ParentDashboard({ enabled }: { enabled: boolean }) {
    const { data, isPending, isError, error } = useDashboardWards(enabled);

    if (!enabled) return null;

    if (isPending) return <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />;

    if (isError || !data) {
        return (
            <Alert variant="destructive">
                <AlertDescription>{getErrorMessage(error, "Could not load your children's summary.")}</AlertDescription>
            </Alert>
        );
    }

    if (data.length === 0) {
        return <p className="text-muted-foreground">No children are linked to your account yet.</p>;
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {data.map((ward) => (
                <RouterLink key={ward.student_id} to={`/my-children/${ward.student_id}`}>
                    <Card className="transition-colors hover:bg-accent/50">
                        <CardHeader>
                            <CardTitle>{ward.name}</CardTitle>
                            <CardDescription>{ward.current_class ?? 'Not enrolled'}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <span className="font-medium">{formatMoney(ward.fee_balance)}</span>
                                {ward.payment_status && <Badge variant="outline">{ward.payment_status}</Badge>}
                            </div>
                            {ward.pending_payment_slips > 0 && (
                                <p className="mt-2 text-sm text-amber-600">
                                    {ward.pending_payment_slips} slip(s) pending verification
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </RouterLink>
            ))}
        </div>
    );
}

function QuickLinksDashboard() {
    const { canAccess } = usePermissions();

    const links = NAV_SECTIONS.flatMap((section) => section.items)
        .filter((item) => item.path !== '/' && canAccess(item.permissions))
        .slice(0, 6);

    if (links.length === 0) {
        return <p className="text-muted-foreground">Use the sidebar to get started.</p>;
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {links.map((item) => (
                <RouterLink key={item.path} to={item.path}>
                    <Card className="transition-colors hover:bg-accent/50">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                {item.icon}
                                <CardTitle className="text-lg">{item.label}</CardTitle>
                            </div>
                            <CardDescription>Open {item.label}</CardDescription>
                        </CardHeader>
                    </Card>
                </RouterLink>
            ))}
        </div>
    );
}

function PlatformAdminLanding() {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink to="/platform/tenants">
                <Card className="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-5 w-5" />
                            <CardTitle className="text-lg">Manage Tenants</CardTitle>
                        </div>
                        <CardDescription>Create new tenants and view as any user in any tenant.</CardDescription>
                    </CardHeader>
                </Card>
            </RouterLink>
            <RouterLink to="/platform/audit-logs">
                <Card className="transition-colors hover:bg-accent/50">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <History className="h-5 w-5" />
                            <CardTitle className="text-lg">Audit Log</CardTitle>
                        </div>
                        <CardDescription>View activity from every tenant in one place.</CardDescription>
                    </CardHeader>
                </Card>
            </RouterLink>
        </div>
    );
}

export function DashboardPage() {
    const { user, canAny, canAccess } = usePermissions();

    if (!user) return null;

    const isPlatformAdmin = user.type === 'platform_admin';
    const showStaffDashboard = !isPlatformAdmin && canAny([...STAFF_DASHBOARD_PERMISSIONS]);
    const showParentDashboard =
        !isPlatformAdmin && canAny([...PARENT_DASHBOARD_PERMISSIONS]) && !showStaffDashboard;
    const showQuickLinks =
        !isPlatformAdmin &&
        !showStaffDashboard &&
        !showParentDashboard &&
        NAV_SECTIONS.some((section) =>
            section.items.some((item) => item.path !== '/' && canAccess(item.permissions)),
        );

    return (
        <div className="space-y-6">
            <PageHeader title={`Welcome, ${user.name}`} description="Overview of your school at a glance" />

            {isPlatformAdmin && <PlatformAdminLanding />}
            {showStaffDashboard && <StaffDashboard enabled={showStaffDashboard} />}
            {showParentDashboard && <ParentDashboard enabled={showParentDashboard} />}
            {showQuickLinks && <QuickLinksDashboard />}
            {!isPlatformAdmin && !showStaffDashboard && !showParentDashboard && !showQuickLinks && (
                <p className="text-muted-foreground">Use the sidebar to get started.</p>
            )}
        </div>
    );
}
