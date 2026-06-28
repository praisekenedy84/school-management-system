import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
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
import { Plus, Pencil, Trash2 } from 'lucide-react';
import {
    useAdminSchools,
    useCreateSchool,
    useDeleteSchool,
    useUpdateSchool,
} from '../api/useAdmin';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { Permission } from '../../../config/permissions';
import type { SchoolAdmin, SchoolRequest } from '../types/admin';

const EMPTY: SchoolRequest = { name: '', code: '', locale: 'en', currency: 'TZS', timezone: 'Africa/Dar_es_Salaam' };

function SchoolDialog({
    open,
    initial,
    onClose,
}: {
    open: boolean;
    initial: SchoolRequest & { id?: string };
    onClose: () => void;
}) {
    const [form, setForm] = useState(initial);
    const [error, setError] = useState<string | null>(null);
    const create = useCreateSchool();
    const update = useUpdateSchool();
    const isEdit = Boolean(initial.id);
    const busy = create.isPending || update.isPending;

    const handleSave = async () => {
        setError(null);
        try {
            if (isEdit && initial.id) {
                await update.mutateAsync({ ...form, id: initial.id });
            } else {
                await create.mutateAsync(form);
            }
            onClose();
        } catch (e) {
            setError(getErrorMessage(e, 'Unable to save school.'));
        }
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullWidth
            maxWidth="sm"
            TransitionProps={{ onEnter: () => setForm(initial) }}
        >
            <DialogTitle>{isEdit ? 'Edit School' : 'New School'}</DialogTitle>
            <DialogContent>
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        label="Name"
                        value={form.name}
                        onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                        fullWidth
                        autoFocus
                    />
                    <TextField
                        label="Code"
                        value={form.code}
                        onChange={(e) => setForm((p) => ({ ...p, code: e.target.value.toUpperCase() }))}
                        fullWidth
                    />
                    <TextField
                        label="Locale"
                        value={form.locale ?? 'en'}
                        onChange={(e) => setForm((p) => ({ ...p, locale: e.target.value }))}
                        fullWidth
                    />
                    <TextField
                        label="Currency"
                        value={form.currency ?? 'TZS'}
                        onChange={(e) => setForm((p) => ({ ...p, currency: e.target.value }))}
                        fullWidth
                    />
                    <TextField
                        label="Timezone"
                        value={form.timezone ?? ''}
                        onChange={(e) => setForm((p) => ({ ...p, timezone: e.target.value }))}
                        fullWidth
                    />
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={form.hostel_available ?? false}
                                onChange={(e) => setForm((p) => ({ ...p, hostel_available: e.target.checked }))}
                            />
                        }
                        label="Hostel available"
                    />
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={form.is_active ?? true}
                                onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))}
                            />
                        }
                        label="Active"
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="contained" onClick={handleSave} disabled={busy || !form.name || !form.code}>
                    Save
                </Button>
            </DialogActions>
        </Dialog>
    );
}

export function SchoolsAdminPage() {
    const { can } = usePermissions();
    const canManage = can(Permission.tenant.manageSchools);
    const { data: schools, isLoading, error } = useAdminSchools();
    const deleteSchool = useDeleteSchool();
    const [dialog, setDialog] = useState<(SchoolRequest & { id?: string }) | null>(null);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load schools.')}</Alert>;
    }

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={3}>
                <Typography variant="h5">Schools</Typography>
                {canManage && (
                    <Button variant="contained" startIcon={<Plus size={18} />} onClick={() => setDialog(EMPTY)}>
                        New School
                    </Button>
                )}
            </Stack>

            <TableContainer component={Paper}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Name</TableCell>
                            <TableCell>Code</TableCell>
                            <TableCell>Locale</TableCell>
                            <TableCell>Active</TableCell>
                            {canManage && <TableCell align="right">Actions</TableCell>}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {(schools ?? []).map((school: SchoolAdmin) => (
                            <TableRow key={school.id}>
                                <TableCell>{school.name}</TableCell>
                                <TableCell>{school.code}</TableCell>
                                <TableCell>{school.locale}</TableCell>
                                <TableCell>{school.is_active ? 'Yes' : 'No'}</TableCell>
                                {canManage && (
                                    <TableCell align="right">
                                        <IconButton
                                            aria-label="Edit"
                                            onClick={() =>
                                                setDialog({
                                                    id: school.id,
                                                    name: school.name,
                                                    code: school.code,
                                                    locale: school.locale,
                                                    currency: school.currency,
                                                    timezone: school.timezone,
                                                    hostel_available: school.hostel_available,
                                                    is_active: school.is_active,
                                                })
                                            }
                                        >
                                            <Pencil size={18} />
                                        </IconButton>
                                        <IconButton
                                            aria-label="Delete"
                                            onClick={() => deleteSchool.mutate(school.id)}
                                            disabled={deleteSchool.isPending}
                                        >
                                            <Trash2 size={18} />
                                        </IconButton>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

            {dialog && <SchoolDialog open onClose={() => setDialog(null)} initial={dialog} />}
        </Box>
    );
}
