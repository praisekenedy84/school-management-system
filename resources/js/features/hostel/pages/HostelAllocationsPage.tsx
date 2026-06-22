import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { Plus } from 'lucide-react';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useStudents } from '../../students/api/useStudents';
import { useHostelRooms } from '../api/useHostelRooms';
import { useHostels } from '../api/useHostels';
import { useAllocateHostel, useEndHostelAllocation, useHostelAllocations } from '../api/useHostelAllocations';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { useAuth } from '../../../app/AuthProvider';
import { HOSTEL_STAFF_ROLES } from '../../../routes/RequireHostelStaff';
import { ExportButtons } from '../../../components/ExportButtons';
import type { AllocateHostelRequest } from '../types/hostel';

function AllocateDialog({
    open,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    onClose: () => void;
    onSubmit: (values: AllocateHostelRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [studentId, setStudentId] = useState('');
    const [hostelRoomId, setHostelRoomId] = useState('');
    const [academicSessionId, setAcademicSessionId] = useState('');

    // Students aren't paginated heavily for most schools at this stage; fetch
    // a generous page since there's no name-search/typeahead endpoint yet
    // (see contract-gap note below).
    const { data: studentsPage } = useStudents(1, 200);
    const { data: rooms } = useHostelRooms();
    const { data: hostels } = useHostels();
    const { data: sessions } = useAcademicSessions();

    const students = studentsPage?.data ?? [];
    const hostelName = (id: string) => hostels?.find((h) => h.id === id)?.name ?? '—';

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setStudentId('');
                    setHostelRoomId('');
                    setAcademicSessionId('');
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>Allocate Room</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        select
                        fullWidth
                        label="Student"
                        value={studentId}
                        onChange={(e) => setStudentId(e.target.value)}
                    >
                        {students.map((student) => (
                            <MenuItem key={student.id} value={student.id}>
                                {student.full_name} ({student.admission_number})
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        select
                        fullWidth
                        label="Room"
                        value={hostelRoomId}
                        onChange={(e) => setHostelRoomId(e.target.value)}
                        helperText="Capacity and gender match are enforced by the server."
                    >
                        {(rooms ?? []).map((room) => (
                            <MenuItem key={room.id} value={room.id}>
                                {hostelName(room.hostel_id)} — {room.room_number} ({room.occupied}/{room.capacity})
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        select
                        fullWidth
                        label="Academic Session"
                        value={academicSessionId}
                        onChange={(e) => setAcademicSessionId(e.target.value)}
                    >
                        {(sessions ?? []).map((session) => (
                            <MenuItem key={session.id} value={session.id}>
                                {session.name}
                            </MenuItem>
                        ))}
                    </TextField>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!studentId || !hostelRoomId || !academicSessionId || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            student_id: studentId,
                            hostel_room_id: hostelRoomId,
                            academic_session_id: academicSessionId,
                        })
                    }
                >
                    {isSubmitting ? 'Allocating…' : 'Allocate'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/**
 * Hostel allocations list + (staff-only) allocate form + end action. For a
 * `parent` the GET endpoint is already auto-scoped server-side to their own
 * wards (HostelAllocationController::index), so the same list naturally
 * becomes "my child's hostel allocation" — only the allocate/end actions are
 * gated, not the page itself (RULES.md §8: hiding UI is UX only).
 */
export function HostelAllocationsPage() {
    const { user } = useAuth();
    const canManage = Boolean(user?.roles.some((role) => HOSTEL_STAFF_ROLES.includes(role)));

    const { data: allocations, isLoading, isError } = useHostelAllocations();
    const { data: studentsPage } = useStudents(1, 200);
    const { data: rooms } = useHostelRooms();
    const { data: hostels } = useHostels();

    const allocate = useAllocateHostel();
    const endAllocation = useEndHostelAllocation();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);
    const [endError, setEndError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const students = studentsPage?.data ?? [];
    const studentName = (id: string) => students.find((s) => s.id === id)?.full_name ?? id;
    const roomLabel = (id: string) => {
        const room = rooms?.find((r) => r.id === id);
        if (!room) {
            return id;
        }
        const hostel = hostels?.find((h) => h.id === room.hostel_id);
        return `${hostel?.name ?? '—'} — ${room.room_number}`;
    };

    const handleAllocate = async (values: AllocateHostelRequest) => {
        setServerError(null);
        try {
            await allocate.mutateAsync(values);
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to allocate this room.'));
        }
    };

    const handleEnd = async (id: string) => {
        setEndError(null);
        try {
            await endAllocation.mutateAsync(id);
        } catch (error) {
            setEndError(getErrorMessage(error, 'Unable to end this allocation.'));
        }
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Hostel Allocations</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/hostel-allocations/export"
                        filenamePrefix="hostel-allocations"
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={() => setDialogOpen(true)}>
                            Allocate Room
                        </Button>
                    )}
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            {endError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setEndError(null)}>
                    {endError}
                </Alert>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load hostel allocations. Please try again.</Alert>}

            {!isLoading && !isError && allocations && allocations.length === 0 && (
                <Alert severity="info">No hostel allocations found.</Alert>
            )}

            {!isLoading && !isError && allocations && allocations.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Student</TableCell>
                                    <TableCell>Room</TableCell>
                                    <TableCell>Allocated</TableCell>
                                    <TableCell>Ended</TableCell>
                                    <TableCell>Status</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {allocations.map((allocation) => (
                                    <TableRow key={allocation.id} hover>
                                        <TableCell>{studentName(allocation.student_id)}</TableCell>
                                        <TableCell>{roomLabel(allocation.hostel_room_id)}</TableCell>
                                        <TableCell>{allocation.allocated_at ?? '—'}</TableCell>
                                        <TableCell>{allocation.ended_at ?? '—'}</TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={allocation.status === 'active' ? 'success' : 'default'}
                                                label={allocation.status === 'active' ? 'Active' : 'Ended'}
                                            />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                {allocation.status === 'active' && (
                                                    <Button
                                                        size="small"
                                                        color="error"
                                                        disabled={endAllocation.isPending}
                                                        onClick={() => handleEnd(allocation.id)}
                                                    >
                                                        End
                                                    </Button>
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

            <AllocateDialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleAllocate}
                isSubmitting={allocate.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
