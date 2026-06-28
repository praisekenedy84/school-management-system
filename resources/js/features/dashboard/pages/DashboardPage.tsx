import { Link as RouterLink } from 'react-router-dom';
import {
    Alert,
    Box,
    Card,
    CardActionArea,
    CardContent,
    Chip,
    CircularProgress,
    Grid,
    Stack,
    Typography,
} from '@mui/material';
import { ShieldCheck, History } from 'lucide-react';
import {
    PARENT_DASHBOARD_PERMISSIONS,
    STAFF_DASHBOARD_PERMISSIONS,
    STAFF_DASHBOARD_WIDGETS,
    getWidgetValue,
} from '../../../config/dashboard';
import { NAV_SECTIONS } from '../../../config/navigation';
import { usePermissions } from '../../../lib/usePermissions';
import { useDashboardSummary, useDashboardWards } from '../api/useDashboard';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { DashboardSummary } from '../types/dashboard';

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <Grid item xs={12} sm={6} md={3}>
            <Card variant="outlined">
                <CardContent>
                    <Typography variant="body2" color="text.secondary" gutterBottom>
                        {label}
                    </Typography>
                    <Typography variant="h5">{value}</Typography>
                </CardContent>
            </Card>
        </Grid>
    );
}

/** Permission-filtered staff metrics from GET /api/v1/dashboard/summary. */
function StaffDashboard({ enabled }: { enabled: boolean }) {
    const { canAny } = usePermissions();
    const { data, isPending, isError, error } = useDashboardSummary(enabled);

    const visibleWidgets = STAFF_DASHBOARD_WIDGETS.filter((widget) => canAny(widget.permissions));

    if (!enabled || visibleWidgets.length === 0) {
        return null;
    }

    if (isPending) {
        return <CircularProgress size={24} />;
    }

    if (isError || !data) {
        return <Alert severity="error">{getErrorMessage(error, 'Could not load the dashboard summary.')}</Alert>;
    }

    const summary = data as DashboardSummary & Record<string, unknown>;

    return (
        <Stack spacing={2}>
            {summary.current_academic_session && (
                <Chip label={`Current session: ${summary.current_academic_session}`} sx={{ alignSelf: 'flex-start' }} />
            )}
            <Grid container spacing={2}>
                {visibleWidgets.map((widget) => {
                    const raw = getWidgetValue(summary, widget);
                    if (raw === null) {
                        return null;
                    }

                    const value = widget.format === 'money' ? formatMoney(raw as number) : raw;

                    return <StatCard key={widget.id} label={widget.label} value={value} />;
                })}
            </Grid>
        </Stack>
    );
}

/** GET /api/v1/dashboard/wards — per-child fee summary (PRD §5.10). */
function ParentDashboard({ enabled }: { enabled: boolean }) {
    const { data, isPending, isError, error } = useDashboardWards(enabled);

    if (!enabled) {
        return null;
    }

    if (isPending) {
        return <CircularProgress size={24} />;
    }

    if (isError || !data) {
        return <Alert severity="error">{getErrorMessage(error, "Could not load your children's summary.")}</Alert>;
    }

    if (data.length === 0) {
        return <Typography color="text.secondary">No children are linked to your account yet.</Typography>;
    }

    return (
        <Grid container spacing={2}>
            {data.map((ward) => (
                <Grid key={ward.student_id} item xs={12} sm={6} md={4}>
                    <Card variant="outlined">
                        <CardActionArea component={RouterLink} to={`/my-children/${ward.student_id}`}>
                            <CardContent>
                                <Typography variant="h6" gutterBottom>
                                    {ward.name}
                                </Typography>
                                <Typography variant="body2" color="text.secondary" gutterBottom>
                                    {ward.current_class ?? 'Not enrolled'}
                                </Typography>
                                <Stack direction="row" spacing={1} alignItems="center" mt={1}>
                                    <Typography variant="body1">{formatMoney(ward.fee_balance)}</Typography>
                                    {ward.payment_status && <Chip size="small" label={ward.payment_status} />}
                                </Stack>
                                {ward.pending_payment_slips > 0 && (
                                    <Typography variant="body2" color="warning.main" mt={1}>
                                        {ward.pending_payment_slips} slip(s) pending verification
                                    </Typography>
                                )}
                            </CardContent>
                        </CardActionArea>
                    </Card>
                </Grid>
            ))}
        </Grid>
    );
}

/** Shortcut cards for users who have module access but no staff summary widgets. */
function QuickLinksDashboard() {
    const { canAccess } = usePermissions();

    const links = NAV_SECTIONS.flatMap((section) => section.items)
        .filter((item) => item.path !== '/' && canAccess(item.permissions))
        .slice(0, 6);

    if (links.length === 0) {
        return <Typography color="text.secondary">Use the sidebar to get started.</Typography>;
    }

    return (
        <Grid container spacing={2}>
            {links.map((item) => (
                <Grid key={item.path} item xs={12} sm={6} md={4}>
                    <Card variant="outlined">
                        <CardActionArea component={RouterLink} to={item.path}>
                            <CardContent>
                                <Stack direction="row" spacing={1.5} alignItems="center" mb={1}>
                                    {item.icon}
                                    <Typography variant="h6">{item.label}</Typography>
                                </Stack>
                                <Typography variant="body2" color="text.secondary">
                                    Open {item.label}
                                </Typography>
                            </CardContent>
                        </CardActionArea>
                    </Card>
                </Grid>
            ))}
        </Grid>
    );
}

function PlatformAdminLanding() {
    return (
        <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
                <Card variant="outlined">
                    <CardActionArea component={RouterLink} to="/platform/tenants">
                        <CardContent>
                            <Stack direction="row" spacing={1.5} alignItems="center" mb={1}>
                                <ShieldCheck size={20} />
                                <Typography variant="h6">Manage Tenants</Typography>
                            </Stack>
                            <Typography variant="body2" color="text.secondary">
                                Create new tenants and view as any user in any tenant.
                            </Typography>
                        </CardContent>
                    </CardActionArea>
                </Card>
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
                <Card variant="outlined">
                    <CardActionArea component={RouterLink} to="/platform/audit-logs">
                        <CardContent>
                            <Stack direction="row" spacing={1.5} alignItems="center" mb={1}>
                                <History size={20} />
                                <Typography variant="h6">Audit Log</Typography>
                            </Stack>
                            <Typography variant="body2" color="text.secondary">
                                View activity from every tenant in one place.
                            </Typography>
                        </CardContent>
                    </CardActionArea>
                </Card>
            </Grid>
        </Grid>
    );
}

/**
 * Composes dashboard panels from permissions — staff metrics, parent wards,
 * platform landing, or quick links to permitted modules (e.g. teachers).
 */
export function DashboardPage() {
    const { user, canAny, canAccess } = usePermissions();

    if (!user) {
        return null;
    }

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
        <Box>
            <Typography variant="h5" gutterBottom>
                Welcome, {user.name}
            </Typography>

            {isPlatformAdmin && <PlatformAdminLanding />}
            {showStaffDashboard && <StaffDashboard enabled={showStaffDashboard} />}
            {showParentDashboard && <ParentDashboard enabled={showParentDashboard} />}
            {showQuickLinks && <QuickLinksDashboard />}
            {!isPlatformAdmin && !showStaffDashboard && !showParentDashboard && !showQuickLinks && (
                <Typography color="text.secondary">Use the sidebar to get started.</Typography>
            )}
        </Box>
    );
}
