import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
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
import { useAcademicSessions } from '../api/useAcademicSessions';
import {
    useCreateAcademicSession,
    useDeleteAcademicSession,
    useUpdateAcademicSession,
} from '../api/useAcademicSessionMutations';
import { useSchools } from '../api/useSchools';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { ExportButtons } from '../../../components/ExportButtons';
import type { AcademicSession, AcademicSessionRequest, School } from '../types/academic';

/**
 * Mirrors AcademicSessionPolicy::create/update/delete — tenant_admin/
 * school_admin ONLY (no academic_director here, unlike ClassRoomPolicy).
 */

function AcademicSessionDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
    showSchoolPicker,
    schools,
}: {
    open: boolean;
    initialValue: AcademicSessionRequest;
    onClose: () => void;
    onSubmit: (values: AcademicSessionRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
    showSchoolPicker: boolean;
    schools: School[];
}) {
    const [name, setName] = useState(initialValue.name);
    const [startDate, setStartDate] = useState(initialValue.start_date);
    const [endDate, setEndDate] = useState(initialValue.end_date);
    const [isCurrent, setIsCurrent] = useState(initialValue.is_current ?? false);
    const [schoolId, setSchoolId] = useState('');

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setStartDate(initialValue.start_date);
                    setEndDate(initialValue.end_date);
                    setIsCurrent(initialValue.is_current ?? false);
                    setSchoolId('');
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Academic Session' : 'New Academic Session'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    {showSchoolPicker && (
                        <TextField
                            select
                            fullWidth
                            label="School"
                            value={schoolId}
                            onChange={(e) => setSchoolId(e.target.value)}
                            helperText="Which school does this session belong to?"
                        >
                            {schools.map((school) => (
                                <MenuItem key={school.id} value={school.id}>
                                    {school.name}
                                </MenuItem>
                            ))}
                        </TextField>
                    )}
                    <TextField
                        fullWidth
                        label="Name"
                        placeholder="e.g. 2026/2027"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        autoFocus
                    />
                    <TextField
                        fullWidth
                        label="Start Date"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={startDate}
                        onChange={(e) => setStartDate(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        label="End Date"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={endDate}
                        onChange={(e) => setEndDate(e.target.value)}
                    />
                    <FormControlLabel
                        control={
                            <Checkbox checked={isCurrent} onChange={(e) => setIsCurrent(e.target.checked)} />
                        }
                        label="Set as the current session (demotes any other current session for this school)"
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!name || !startDate || !endDate || (showSchoolPicker && !schoolId) || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            name,
                            start_date: startDate,
                            end_date: endDate,
                            is_current: isCurrent,
                            school_id: schoolId || undefined,
                        })
                    }
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** CRUD list for academic sessions; shows which one is current with a chip. */
export function AcademicSessionsPage() {
    const { user, canAction } = usePermissions();
    const canManage = canAction('manageAcademicSessions');
    const needsSchoolPicker = user?.school_id === null;
    const { data: schools } = useSchools();
    const { data, isLoading, isError } = useAcademicSessions();
    const createSession = useCreateAcademicSession();
    const updateSession = useUpdateAcademicSession();
    const deleteSession = useDeleteAcademicSession();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingSession, setEditingSession] = useState<AcademicSession | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingSession(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (session: AcademicSession) => {
        setEditingSession(session);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: AcademicSessionRequest) => {
        setServerError(null);
        try {
            if (editingSession) {
                await updateSession.mutateAsync({ id: editingSession.id, payload: values });
            } else {
                await createSession.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save academic session.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteSession.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Academic Sessions</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/academic-sessions/export"
                        filenamePrefix="academic-sessions"
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Session
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

            {isError && <Alert severity="error">Unable to load academic sessions. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No academic sessions have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Start Date</TableCell>
                                    <TableCell>End Date</TableCell>
                                    <TableCell>Status</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((session) => (
                                    <TableRow key={session.id} hover>
                                        <TableCell>{session.name}</TableCell>
                                        <TableCell>{session.start_date ?? '—'}</TableCell>
                                        <TableCell>{session.end_date ?? '—'}</TableCell>
                                        <TableCell>
                                            {session.is_current && (
                                                <Chip label="Current" color="primary" size="small" />
                                            )}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(session)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => handleDelete(session.id)}>
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

            <AcademicSessionDialog
                open={dialogOpen}
                initialValue={{
                    name: editingSession?.name ?? '',
                    start_date: editingSession?.start_date ?? '',
                    end_date: editingSession?.end_date ?? '',
                    is_current: editingSession?.is_current ?? false,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createSession.isPending || updateSession.isPending}
                serverError={serverError}
                showSchoolPicker={needsSchoolPicker && !editingSession}
                schools={schools ?? []}
            />
        </Box>
    );
}
