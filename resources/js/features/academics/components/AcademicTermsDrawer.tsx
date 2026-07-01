import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
    Drawer,
    FormControlLabel,
    IconButton,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import {
    useAcademicTerms,
    useCreateAcademicTerm,
    useDeleteAcademicTerm,
    useUpdateAcademicTerm,
} from '../api/useAcademicTerms';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { AcademicSession, AcademicTerm, AcademicTermRequest } from '../types/academic';

function TermForm({
    initial,
    onSubmit,
    onCancel,
    isSubmitting,
}: {
    initial: AcademicTermRequest;
    onSubmit: (values: AcademicTermRequest) => void;
    onCancel: () => void;
    isSubmitting: boolean;
}) {
    const [name, setName] = useState(initial.name);
    const [startDate, setStartDate] = useState(initial.start_date);
    const [endDate, setEndDate] = useState(initial.end_date);
    const [isCurrent, setIsCurrent] = useState(initial.is_current ?? false);

    return (
        <Stack spacing={2} mb={2}>
            <TextField
                size="small"
                fullWidth
                label="Term name"
                value={name}
                onChange={(e) => setName(e.target.value)}
            />
            <Stack direction="row" spacing={1}>
                <TextField
                    size="small"
                    fullWidth
                    label="Start date"
                    type="date"
                    InputLabelProps={{ shrink: true }}
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                />
                <TextField
                    size="small"
                    fullWidth
                    label="End date"
                    type="date"
                    InputLabelProps={{ shrink: true }}
                    value={endDate}
                    onChange={(e) => setEndDate(e.target.value)}
                />
            </Stack>
            <FormControlLabel
                control={<Checkbox checked={isCurrent} onChange={(e) => setIsCurrent(e.target.checked)} />}
                label="Set as active term"
            />
            <Stack direction="row" spacing={1}>
                <Button
                    variant="contained"
                    size="small"
                    disabled={!name || !startDate || !endDate || isSubmitting}
                    onClick={() => onSubmit({ name, start_date: startDate, end_date: endDate, is_current: isCurrent })}
                >
                    Save
                </Button>
                <Button size="small" onClick={onCancel}>
                    Cancel
                </Button>
            </Stack>
        </Stack>
    );
}

/** Manage terms within an academic session. */
export function AcademicTermsDrawer({
    open,
    session,
    onClose,
}: {
    open: boolean;
    session: AcademicSession | null;
    onClose: () => void;
}) {
    const sessionId = session?.id ?? '';
    const { data: terms, isLoading } = useAcademicTerms(sessionId);
    const createTerm = useCreateAcademicTerm(sessionId);
    const updateTerm = useUpdateAcademicTerm(sessionId);
    const deleteTerm = useDeleteAcademicTerm(sessionId);

    const [showForm, setShowForm] = useState(false);
    const [editingTerm, setEditingTerm] = useState<AcademicTerm | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const handleCreate = async (values: AcademicTermRequest) => {
        setServerError(null);
        try {
            await createTerm.mutateAsync(values);
            setShowForm(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to create term.'));
        }
    };

    const handleUpdate = async (values: AcademicTermRequest) => {
        if (!editingTerm) return;
        setServerError(null);
        try {
            await updateTerm.mutateAsync({ id: editingTerm.id, payload: values });
            setEditingTerm(null);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to update term.'));
        }
    };

    const handleDelete = (termId: string) => {
        setServerError(null);
        deleteTerm.mutate(termId, {
            onError: (error) => setServerError(getErrorMessage(error, 'Unable to delete term.')),
        });
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose}>
            <Box sx={{ width: 480, p: 3 }}>
                <Typography variant="h6" gutterBottom>
                    Terms — {session?.name}
                </Typography>

                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                {isLoading && (
                    <Box display="flex" justifyContent="center" py={2}>
                        <CircularProgress size={24} />
                    </Box>
                )}

                {!isLoading && (
                    <Table size="small" sx={{ mb: 2 }}>
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Dates</TableCell>
                                <TableCell>Active</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {(terms ?? []).length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={4}>
                                        <Typography variant="body2" color="text.secondary">
                                            No terms defined yet.
                                        </Typography>
                                    </TableCell>
                                </TableRow>
                            )}
                            {(terms ?? []).map((term) => (
                                <TableRow key={term.id}>
                                    <TableCell>{term.name}</TableCell>
                                    <TableCell>
                                        {term.start_date} — {term.end_date}
                                    </TableCell>
                                    <TableCell>{term.is_current ? 'Yes' : '—'}</TableCell>
                                    <TableCell align="right">
                                        <IconButton size="small" onClick={() => setEditingTerm(term)}>
                                            <Pencil size={14} />
                                        </IconButton>
                                        <IconButton size="small" onClick={() => handleDelete(term.id)}>
                                            <Trash2 size={14} />
                                        </IconButton>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                {editingTerm && (
                    <TermForm
                        initial={{
                            name: editingTerm.name,
                            start_date: editingTerm.start_date ?? '',
                            end_date: editingTerm.end_date ?? '',
                            is_current: editingTerm.is_current,
                        }}
                        onSubmit={handleUpdate}
                        onCancel={() => setEditingTerm(null)}
                        isSubmitting={updateTerm.isPending}
                    />
                )}

                {showForm && !editingTerm && (
                    <TermForm
                        initial={{ name: '', start_date: '', end_date: '', is_current: false }}
                        onSubmit={handleCreate}
                        onCancel={() => setShowForm(false)}
                        isSubmitting={createTerm.isPending}
                    />
                )}

                {!showForm && !editingTerm && (
                    <Button startIcon={<Plus size={16} />} onClick={() => setShowForm(true)} sx={{ mb: 2 }}>
                        Add Term
                    </Button>
                )}

                <Box textAlign="right">
                    <Button onClick={onClose}>Close</Button>
                </Box>
            </Box>
        </Drawer>
    );
}
