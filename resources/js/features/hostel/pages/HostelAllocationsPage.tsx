import { useMemo, useState } from 'react';
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
import { Pencil, Plus } from 'lucide-react';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useStudents } from '../../students/api/useStudents';
import { useHostelRooms } from '../api/useHostelRooms';
import { useHostels } from '../api/useHostels';
import { useMealPlans } from '../api/useMealPlans';
import {
    useAllocateHostel,
    useEndHostelAllocation,
    useHostelAllocations,
    useUpdateHostelAllocation,
} from '../api/useHostelAllocations';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { ExportButtons } from '../../../components/ExportButtons';
import type { AllocateHostelRequest, HostelAllocation, UpdateHostelAllocationRequest } from '../types/hostel';

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
    const [mealPlanId, setMealPlanId] = useState('');

    const { data: studentsPage } = useStudents(1, 200);
    const { data: rooms } = useHostelRooms();
    const { data: hostels } = useHostels();
    const { data: sessions } = useAcademicSessions();

    const selectedRoom = rooms?.find((room) => room.id === hostelRoomId);
    const { data: mealPlans } = useMealPlans({ hostel_id: selectedRoom?.hostel_id });

    const boardingStudents = useMemo(
        () => (studentsPage?.data ?? []).filter((student) => student.residence_type === 'boarding'),
        [studentsPage?.data],
    );
    const hostelName = (id: string) => hostels?.find((h) => h.id === id)?.name ?? '—';
    const activeMealPlans = (mealPlans ?? []).filter((plan) => plan.is_active);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setStudentId('');
                    setHostelRoomId('');
                    setAcademicSessionId('');
                    setMealPlanId('');
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
                        helperText="Only boarding students are listed."
                    >
                        {boardingStudents.map((student) => (
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
                        onChange={(e) => {
                            setHostelRoomId(e.target.value);
                            setMealPlanId('');
                        }}
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
                        label="Meal Plan"
                        value={mealPlanId}
                        onChange={(e) => setMealPlanId(e.target.value)}
                        disabled={!hostelRoomId}
                        helperText={
                            hostelRoomId
                                ? 'Optional. Plans are scoped to the selected room\'s hostel.'
                                : 'Select a room first to choose a meal plan.'
                        }
                    >
                        <MenuItem value="">None</MenuItem>
                        {activeMealPlans.map((plan) => (
                            <MenuItem key={plan.id} value={plan.id}>
                                {plan.name}
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
                            meal_plan_id: mealPlanId || null,
                        })
                    }
                >
                    {isSubmitting ? 'Allocating…' : 'Allocate'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

function EditMealPlanDialog({
    open,
    allocation,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    allocation: HostelAllocation | null;
    onClose: () => void;
    onSubmit: (values: UpdateHostelAllocationRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [mealPlanId, setMealPlanId] = useState('');

    const { data: rooms } = useHostelRooms();
    const room = rooms?.find((r) => r.id === allocation?.hostel_room_id);
    const { data: mealPlans } = useMealPlans({ hostel_id: room?.hostel_id });
    const activeMealPlans = (mealPlans ?? []).filter((plan) => plan.is_active);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setMealPlanId(allocation?.meal_plan_id ?? '');
                },
            }}
            fullWidth
            maxWidth="xs"
        >
            <DialogTitle>Change Meal Plan</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <TextField
                    select
                    fullWidth
                    label="Meal Plan"
                    value={mealPlanId}
                    onChange={(e) => setMealPlanId(e.target.value)}
                    sx={{ mt: 1 }}
                >
                    <MenuItem value="">None</MenuItem>
                    {activeMealPlans.map((plan) => (
                        <MenuItem key={plan.id} value={plan.id}>
                            {plan.name}
                        </MenuItem>
                    ))}
                </TextField>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={isSubmitting}
                    onClick={() => onSubmit({ meal_plan_id: mealPlanId || null })}
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
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
    const { canAction } = usePermissions();
    const canManage = canAction('manageHostelAllocations');

    const [mealPlanFilter, setMealPlanFilter] = useState('');
    const { data: allocations, isLoading, isError } = useHostelAllocations({
        meal_plan_id: mealPlanFilter || undefined,
    });
    const { data: studentsPage } = useStudents(1, 200);
    const { data: rooms } = useHostelRooms();
    const { data: hostels } = useHostels();
    const { data: allMealPlans } = useMealPlans();

    const allocate = useAllocateHostel();
    const endAllocation = useEndHostelAllocation();
    const updateAllocation = useUpdateHostelAllocation();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editAllocation, setEditAllocation] = useState<HostelAllocation | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [editError, setEditError] = useState<string | null>(null);
    const [endError, setEndError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const students = studentsPage?.data ?? [];
    const studentName = (id: string) => students.find((s) => s.id === id)?.full_name ?? id;
    const mealPlanName = (allocation: HostelAllocation) =>
        allocation.meal_plan?.name ?? allMealPlans?.find((plan) => plan.id === allocation.meal_plan_id)?.name ?? '—';
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

    const handleUpdateMealPlan = async (values: UpdateHostelAllocationRequest) => {
        if (!editAllocation) {
            return;
        }

        setEditError(null);
        try {
            await updateAllocation.mutateAsync({ id: editAllocation.id, payload: values });
            setEditAllocation(null);
        } catch (error) {
            setEditError(getErrorMessage(error, 'Unable to update the meal plan.'));
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

            <TextField
                select
                size="small"
                label="Filter by Meal Plan"
                value={mealPlanFilter}
                onChange={(e) => setMealPlanFilter(e.target.value)}
                sx={{ minWidth: 240, mb: 2 }}
            >
                <MenuItem value="">All Meal Plans</MenuItem>
                {(allMealPlans ?? []).map((plan) => (
                    <MenuItem key={plan.id} value={plan.id}>
                        {plan.name}
                    </MenuItem>
                ))}
            </TextField>

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
                                    <TableCell>Meal Plan</TableCell>
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
                                        <TableCell>{mealPlanName(allocation)}</TableCell>
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
                                                    <Stack direction="row" spacing={1} justifyContent="flex-end">
                                                        <Button
                                                            size="small"
                                                            startIcon={<Pencil size={14} />}
                                                            disabled={updateAllocation.isPending}
                                                            onClick={() => {
                                                                setEditError(null);
                                                                setEditAllocation(allocation);
                                                            }}
                                                        >
                                                            Meal Plan
                                                        </Button>
                                                        <Button
                                                            size="small"
                                                            color="error"
                                                            disabled={endAllocation.isPending}
                                                            onClick={() => handleEnd(allocation.id)}
                                                        >
                                                            End
                                                        </Button>
                                                    </Stack>
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

            <EditMealPlanDialog
                open={editAllocation !== null}
                allocation={editAllocation}
                onClose={() => setEditAllocation(null)}
                onSubmit={handleUpdateMealPlan}
                isSubmitting={updateAllocation.isPending}
                serverError={editError}
            />
        </Box>
    );
}
