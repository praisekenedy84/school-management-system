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
import { useCreateHostel, useDeleteHostel, useHostels, useUpdateHostel } from '../api/useHostels';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { useAuth } from '../../../app/AuthProvider';
import { HOSTEL_STAFF_ROLES } from '../../../routes/RequireHostelStaff';
import { ExportButtons } from '../../../components/ExportButtons';
import type { Hostel, HostelGender, HostelRequest } from '../types/hostel';

const GENDER_OPTIONS: { value: HostelGender; label: string }[] = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'mixed', label: 'Mixed' },
];

function HostelDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: HostelRequest;
    onClose: () => void;
    onSubmit: (values: HostelRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [name, setName] = useState(initialValue.name);
    const [gender, setGender] = useState<HostelGender>(initialValue.gender);
    const [description, setDescription] = useState(initialValue.description ?? '');
    const [isActive, setIsActive] = useState(initialValue.is_active ?? true);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setGender(initialValue.gender);
                    setDescription(initialValue.description ?? '');
                    setIsActive(initialValue.is_active ?? true);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Hostel' : 'New Hostel'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        fullWidth
                        label="Name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        autoFocus
                    />
                    <TextField
                        select
                        fullWidth
                        label="Gender"
                        value={gender}
                        onChange={(e) => setGender(e.target.value as HostelGender)}
                    >
                        {GENDER_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Description"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
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
                    disabled={!name || isSubmitting}
                    onClick={() =>
                        onSubmit({ name, gender, description: description || null, is_active: isActive })
                    }
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** CRUD list for hostel buildings, gated to hostel_manager/school_admin/tenant_admin (RULES.md §5). */
export function HostelsPage() {
    const { user } = useAuth();
    const canManage = Boolean(user?.roles.some((role) => HOSTEL_STAFF_ROLES.includes(role)));
    const { data, isLoading, isError } = useHostels();
    const createHostel = useCreateHostel();
    const updateHostel = useUpdateHostel();
    const deleteHostel = useDeleteHostel();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingHostel, setEditingHostel] = useState<Hostel | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingHostel(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (hostel: Hostel) => {
        setEditingHostel(hostel);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: HostelRequest) => {
        setServerError(null);
        try {
            if (editingHostel) {
                await updateHostel.mutateAsync({ id: editingHostel.id, payload: values });
            } else {
                await createHostel.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save hostel.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteHostel.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Hostels</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/hostels/export"
                        filenamePrefix="hostels"
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Hostel
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

            {isError && <Alert severity="error">Unable to load hostels. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No hostels have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Gender</TableCell>
                                    <TableCell>Description</TableCell>
                                    <TableCell>Status</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((hostel) => (
                                    <TableRow key={hostel.id} hover>
                                        <TableCell>{hostel.name}</TableCell>
                                        <TableCell sx={{ textTransform: 'capitalize' }}>{hostel.gender}</TableCell>
                                        <TableCell>{hostel.description ?? '—'}</TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={hostel.is_active ? 'success' : 'default'}
                                                label={hostel.is_active ? 'Active' : 'Inactive'}
                                            />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(hostel)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => handleDelete(hostel.id)}>
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

            <HostelDialog
                open={dialogOpen}
                initialValue={{
                    name: editingHostel?.name ?? '',
                    gender: editingHostel?.gender ?? 'mixed',
                    description: editingHostel?.description ?? '',
                    is_active: editingHostel?.is_active ?? true,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createHostel.isPending || updateHostel.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
