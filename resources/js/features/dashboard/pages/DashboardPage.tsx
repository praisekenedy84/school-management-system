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
import { useAuth } from '../../../app/AuthProvider';
import { useDashboardSummary, useDashboardWards } from '../api/useDashboard';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';

const STAFF_ROLES = ['tenant_admin', 'school_admin', 'finance_manager', 'accountant', 'academic_director'];

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

/** GET /api/v1/dashboard/summary — counts across enrolment, attendance, finance, hostel (PRD §5.9). */
function StaffSummary() {
    const { data, isPending, isError, error } = useDashboardSummary(true);

    if (isPending) {
        return <CircularProgress size={24} />;
    }

    if (isError || !data) {
        return <Alert severity="error">{getErrorMessage(error, 'Could not load the dashboard summary.')}</Alert>;
    }

    return (
        <Stack spacing={2}>
            {data.current_academic_session && (
                <Chip label={`Current session: ${data.current_academic_session}`} sx={{ alignSelf: 'flex-start' }} />
            )}
            <Grid container spacing={2}>
                <StatCard label="Active students" value={data.active_students} />
                <StatCard label="Present today" value={data.attendance_today.present} />
                <StatCard label="Absent today" value={data.attendance_today.absent} />
                <StatCard label="Pending payment slips" value={data.payment_slips.pending} />
                <StatCard label="Verified today" value={formatMoney(data.payment_slips.verified_today_total)} />
                <StatCard label="Hostel rooms" value={data.hostel_occupancy.rooms} />
                <StatCard label="Hostel bed capacity" value={data.hostel_occupancy.capacity} />
            </Grid>
        </Stack>
    );
}

/** GET /api/v1/dashboard/wards — per-child fee summary (PRD §5.10), scoped to the parent's own children. */
function ParentWards() {
    const { data, isPending, isError, error } = useDashboardWards(true);

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

/** Platform Admin landing — not tenant-scoped, so the staff/parent summaries above don't apply at all. */
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
 * Cross-module dashboard (PRD §5.9/§5.10) — renders the staff summary or the
 * parent's per-child summary depending on role. A Platform Admin (central,
 * not tenant-scoped — ADR-0008) gets its own landing instead, since none of
 * the tenant-scoped summaries apply. A user with neither (e.g. a teacher)
 * sees a plain welcome; teachers/class_teachers have their own feature pages
 * (attendance, mark entry) as their real landing point.
 */
export function DashboardPage() {
    const { user } = useAuth();

    if (!user) {
        return null;
    }

    const isPlatformAdmin = user.type === 'platform_admin';
    const isStaff = user.roles.some((role) => STAFF_ROLES.includes(role));
    const isParent = user.roles.includes('parent');

    return (
        <Box>
            <Typography variant="h5" gutterBottom>
                Welcome, {user.name}
            </Typography>

            {isPlatformAdmin && <PlatformAdminLanding />}
            {!isPlatformAdmin && isStaff && <StaffSummary />}
            {!isPlatformAdmin && isParent && <ParentWards />}
            {!isPlatformAdmin && !isStaff && !isParent && (
                <Typography color="text.secondary">Use the sidebar to get started.</Typography>
            )}
        </Box>
    );
}
