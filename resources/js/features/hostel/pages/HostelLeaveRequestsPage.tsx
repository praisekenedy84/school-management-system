import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Typography,
} from '@mui/material';
import { Plus } from 'lucide-react';
import { useStudents } from '../../students/api/useStudents';
import { useHostelAllocations } from '../api/useHostelAllocations';
import { useHostelLeaveRequests } from '../api/useHostelLeaveRequests';
import { LeaveStatusBadge } from '../components/LeaveStatusBadge';
import { DecideLeaveDialog } from '../components/DecideLeaveDialog';
import { RequestLeaveDialog } from '../components/RequestLeaveDialog';
import { useAuth } from '../../../app/AuthProvider';
import { HOSTEL_STAFF_ROLES } from '../../../routes/RequireHostelStaff';
import { ExportButtons } from '../../../components/ExportButtons';
import type { HostelLeaveRequest } from '../types/hostel';

/**
 * Hostel leave/exeat requests:
 *  - hostel_manager/school_admin/tenant_admin see every request (queue-style,
 *    mirrors finance's VerificationQueuePage) with Approve/Reject actions.
 *  - parent sees only their own wards' requests (already server-scoped — see
 *    useHostelLeaveRequests' doc comment) plus a "Request Leave" form built
 *    off their own wards' hostel allocations (also already server-scoped).
 */
export function HostelLeaveRequestsPage() {
    const { user } = useAuth();
    const isHostelStaff = Boolean(user?.roles.some((role) => HOSTEL_STAFF_ROLES.includes(role)));
    const isParent = Boolean(user?.roles.includes('parent'));

    const { data: leaveRequests, isLoading, isError } = useHostelLeaveRequests();
    const { data: allocations } = useHostelAllocations();
    const { data: studentsPage } = useStudents(1, 200);

    const [decideTarget, setDecideTarget] = useState<HostelLeaveRequest | null>(null);
    const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
    const [requestDialogOpen, setRequestDialogOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const students = studentsPage?.data ?? [];
    const studentName = (id: string) => students.find((s) => s.id === id)?.full_name ?? id;

    const openDecide = (leaveRequest: HostelLeaveRequest, nextDecision: 'approve' | 'reject') => {
        setDecideTarget(leaveRequest);
        setDecision(nextDecision);
    };

    const closeDecide = () => {
        setDecideTarget(null);
        setDecision(null);
    };

    // For a parent, only "active" allocations make sense to request leave
    // against; the list is already scoped server-side to their own wards.
    const myAllocations = (allocations ?? []).filter((allocation) => allocation.status === 'active');

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Hostel Leave Requests</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/hostel-leave-requests/export"
                        filenamePrefix="hostel-leave-requests"
                        onError={(message) => setExportError(message)}
                    />
                    {isParent && (
                        <Button
                            variant="contained"
                            startIcon={<Plus size={18} />}
                            onClick={() => setRequestDialogOpen(true)}
                        >
                            Request Leave
                        </Button>
                    )}
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load leave requests. Please try again.</Alert>}

            {!isLoading && !isError && leaveRequests && leaveRequests.length === 0 && (
                <Alert severity="info">No leave requests found.</Alert>
            )}

            {!isLoading && !isError && leaveRequests && leaveRequests.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Student</TableCell>
                                    <TableCell>Reason</TableCell>
                                    <TableCell>Depart</TableCell>
                                    <TableCell>Return</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell>Decision Notes</TableCell>
                                    {isHostelStaff && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {leaveRequests.map((leaveRequest) => (
                                    <TableRow key={leaveRequest.id} hover>
                                        <TableCell>{studentName(leaveRequest.student_id)}</TableCell>
                                        <TableCell>{leaveRequest.reason}</TableCell>
                                        <TableCell>{leaveRequest.depart_at ?? '—'}</TableCell>
                                        <TableCell>{leaveRequest.return_at ?? '—'}</TableCell>
                                        <TableCell>
                                            <LeaveStatusBadge status={leaveRequest.status} />
                                        </TableCell>
                                        <TableCell>{leaveRequest.decision_notes ?? '—'}</TableCell>
                                        {isHostelStaff && (
                                            <TableCell align="right">
                                                {leaveRequest.status === 'pending' && (
                                                    <>
                                                        <Button
                                                            size="small"
                                                            color="success"
                                                            onClick={() => openDecide(leaveRequest, 'approve')}
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="small"
                                                            color="error"
                                                            onClick={() => openDecide(leaveRequest, 'reject')}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <DecideLeaveDialog leaveRequest={decideTarget} decision={decision} onClose={closeDecide} />

            <RequestLeaveDialog
                open={requestDialogOpen}
                allocations={myAllocations}
                studentName={studentName}
                onClose={() => setRequestDialogOpen(false)}
            />
        </Box>
    );
}
