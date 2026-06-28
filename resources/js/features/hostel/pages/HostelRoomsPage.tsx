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
    FormControlLabel,
    IconButton,
    MenuItem,
    Paper,
    Stack,
    Switch,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { Plus, Pencil, Trash2 } from 'lucide-react';
import { useHostels } from '../api/useHostels';
import {
    useCreateHostelRoom,
    useDeleteHostelRoom,
    useHostelRooms,
    useUpdateHostelRoom,
} from '../api/useHostelRooms';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { ExportButtons } from '../../../components/ExportButtons';
import type { HostelRoom, HostelRoomRequest } from '../types/hostel';

function HostelRoomDialog({
    open,
    initialValue,
    hostelOptions,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: HostelRoomRequest;
    hostelOptions: { id: string; name: string }[];
    onClose: () => void;
    onSubmit: (values: HostelRoomRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [hostelId, setHostelId] = useState(initialValue.hostel_id);
    const [roomNumber, setRoomNumber] = useState(initialValue.room_number);
    const [capacity, setCapacity] = useState(String(initialValue.capacity || ''));
    const [isActive, setIsActive] = useState(initialValue.is_active ?? true);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setHostelId(initialValue.hostel_id);
                    setRoomNumber(initialValue.room_number);
                    setCapacity(String(initialValue.capacity || ''));
                    setIsActive(initialValue.is_active ?? true);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.room_number ? 'Edit Room' : 'New Room'}</DialogTitle>
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
                        label="Hostel"
                        value={hostelId}
                        onChange={(e) => setHostelId(e.target.value)}
                    >
                        {hostelOptions.map((hostel) => (
                            <MenuItem key={hostel.id} value={hostel.id}>
                                {hostel.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        fullWidth
                        label="Room Number"
                        value={roomNumber}
                        onChange={(e) => setRoomNumber(e.target.value)}
                        autoFocus
                    />
                    <TextField
                        fullWidth
                        type="number"
                        label="Capacity"
                        value={capacity}
                        onChange={(e) => setCapacity(e.target.value)}
                        inputProps={{ min: 1, max: 50 }}
                    />
                    <FormControlLabel
                        control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />}
                        label="Active"
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!hostelId || !roomNumber || !capacity || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            hostel_id: hostelId,
                            room_number: roomNumber,
                            capacity: Number(capacity),
                            is_active: isActive,
                        })
                    }
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** CRUD list for hostel rooms, filterable by hostel, gated to hostel staff (RULES.md §5). */
export function HostelRoomsPage() {
    const { user, canAction } = usePermissions();
    const canManage = canAction('manageHostelRooms');

    const [hostelFilter, setHostelFilter] = useState<string>('');
    const { data: hostels } = useHostels();
    const { data, isLoading, isError } = useHostelRooms({ hostel_id: hostelFilter || undefined });
    const createRoom = useCreateHostelRoom();
    const updateRoom = useUpdateHostelRoom();
    const deleteRoom = useDeleteHostelRoom();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRoom, setEditingRoom] = useState<HostelRoom | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const hostelOptions = hostels ?? [];
    const hostelName = (id: string) => hostelOptions.find((h) => h.id === id)?.name ?? '—';

    const openCreate = () => {
        setEditingRoom(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (room: HostelRoom) => {
        setEditingRoom(room);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: HostelRoomRequest) => {
        setServerError(null);
        try {
            if (editingRoom) {
                await updateRoom.mutateAsync({ id: editingRoom.id, payload: values });
            } else {
                await createRoom.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save room.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteRoom.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Hostel Rooms</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/hostel-rooms/export"
                        filenamePrefix="hostel-rooms"
                        params={hostelFilter ? { hostel_id: hostelFilter } : undefined}
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Room
                        </Button>
                    )}
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            <TextField
                select
                size="small"
                label="Filter by Hostel"
                value={hostelFilter}
                onChange={(e) => setHostelFilter(e.target.value)}
                sx={{ minWidth: 240, mb: 2 }}
            >
                <MenuItem value="">All Hostels</MenuItem>
                {hostelOptions.map((hostel) => (
                    <MenuItem key={hostel.id} value={hostel.id}>
                        {hostel.name}
                    </MenuItem>
                ))}
            </TextField>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load hostel rooms. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No hostel rooms have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Room Number</TableCell>
                                    <TableCell>Hostel</TableCell>
                                    <TableCell>Occupancy</TableCell>
                                    <TableCell>Status</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((room) => (
                                    <TableRow key={room.id} hover>
                                        <TableCell>{room.room_number}</TableCell>
                                        <TableCell>{hostelName(room.hostel_id)}</TableCell>
                                        <TableCell>
                                            {room.occupied}/{room.capacity}
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={room.is_active ? 'success' : 'default'}
                                                label={room.is_active ? 'Active' : 'Inactive'}
                                            />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(room)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => handleDelete(room.id)}>
                                                    <Trash2 size={16} />
                                                </IconButton>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <HostelRoomDialog
                open={dialogOpen}
                initialValue={{
                    hostel_id: editingRoom?.hostel_id ?? hostelFilter ?? '',
                    room_number: editingRoom?.room_number ?? '',
                    capacity: editingRoom?.capacity ?? 1,
                    is_active: editingRoom?.is_active ?? true,
                }}
                hostelOptions={hostelOptions}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createRoom.isPending || updateRoom.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
