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
import { Plus, Pencil } from 'lucide-react';
import { useClasses } from '../api/useClasses';
import { useSubjects } from '../api/useSubjects';
import { usePermissions } from '../../../lib/usePermissions';
import {
    useArchiveAssignment,
    useAssignments,
    usePublishAssignment,
    useUpdateAssignment,
} from '../api/useAssignments';
import { NewAssignmentForm } from '../components/NewAssignmentForm';
import { ExportButtons } from '../../../components/ExportButtons';
import type { Assignment, AssignmentFilters, UpdateAssignmentRequest } from '../types/academic';

function EditAssignmentDialog({
    assignment,
    open,
    onClose,
}: {
    assignment: Assignment | null;
    open: boolean;
    onClose: () => void;
}) {
    const updateAssignment = useUpdateAssignment();
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [dueAt, setDueAt] = useState('');
    const [error, setError] = useState<string | null>(null);

    const handleSave = async () => {
        if (!assignment) return;
        setError(null);
        try {
            const payload: UpdateAssignmentRequest = {
                title,
                description: description || null,
                due_at: dueAt || null,
            };
            await updateAssignment.mutateAsync({ id: assignment.id, payload });
            onClose();
        } catch {
            setError('Unable to save changes.');
        }
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setTitle(assignment?.title ?? '');
                    setDescription(assignment?.description ?? '');
                    setDueAt(assignment?.due_at ? assignment.due_at.slice(0, 16) : '');
                    setError(null);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>Edit Draft Assignment</DialogTitle>
            <DialogContent>
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField fullWidth label="Title" value={title} onChange={(e) => setTitle(e.target.value)} />
                    <TextField
                        fullWidth
                        multiline
                        minRows={3}
                        label="Description"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        label="Due At"
                        type="datetime-local"
                        InputLabelProps={{ shrink: true }}
                        value={dueAt}
                        onChange={(e) => setDueAt(e.target.value)}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="contained" disabled={!title || updateAssignment.isPending} onClick={handleSave}>
                    Save
                </Button>
            </DialogActions>
        </Dialog>
    );
}

export function AssignmentsPage() {
    const { canAction } = usePermissions();
    const [filters, setFilters] = useState<AssignmentFilters>({ status: '' });
    const { data, isLoading, isError } = useAssignments(filters);
    const publishAssignment = usePublishAssignment();
    const archiveAssignment = useArchiveAssignment();
    const { data: classes } = useClasses();
    const { data: subjects } = useSubjects();

    const [showForm, setShowForm] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);
    const [editing, setEditing] = useState<Assignment | null>(null);

    const canCreate = canAction('createAssignments');

    const statusColor = (status: Assignment['status']) => {
        if (status === 'published') return 'success';
        if (status === 'archived') return 'default';
        return 'warning';
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Assignments</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/assignments/export"
                        filenamePrefix="assignments"
                        onError={(message) => setExportError(message)}
                    />
                    {canCreate && (
                        <Button
                            variant="contained"
                            startIcon={<Plus size={18} />}
                            onClick={() => setShowForm((prev) => !prev)}
                        >
                            {showForm ? 'Close' : 'New Assignment'}
                        </Button>
                    )}
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            <Paper sx={{ p: 2, mb: 2 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                    <TextField
                        select
                        size="small"
                        label="Status"
                        sx={{ minWidth: 160 }}
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            setFilters((f) => ({ ...f, status: e.target.value as AssignmentFilters['status'] }))
                        }
                    >
                        <MenuItem value="">Active (non-archived)</MenuItem>
                        <MenuItem value="draft">Draft</MenuItem>
                        <MenuItem value="published">Published</MenuItem>
                        <MenuItem value="archived">Archived</MenuItem>
                    </TextField>
                    <TextField
                        select
                        size="small"
                        label="Class"
                        sx={{ minWidth: 160 }}
                        value={filters.class_id ?? ''}
                        onChange={(e) => setFilters((f) => ({ ...f, class_id: e.target.value }))}
                    >
                        <MenuItem value="">All classes</MenuItem>
                        {(classes ?? []).map((c) => (
                            <MenuItem key={c.id} value={c.id}>
                                {c.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        select
                        size="small"
                        label="Subject"
                        sx={{ minWidth: 160 }}
                        value={filters.subject_id ?? ''}
                        onChange={(e) => setFilters((f) => ({ ...f, subject_id: e.target.value }))}
                    >
                        <MenuItem value="">All subjects</MenuItem>
                        {(subjects?.data ?? []).map((s) => (
                            <MenuItem key={s.id} value={s.id}>
                                {s.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        size="small"
                        label="Due from"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={filters.due_from ?? ''}
                        onChange={(e) => setFilters((f) => ({ ...f, due_from: e.target.value }))}
                    />
                    <TextField
                        size="small"
                        label="Due to"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={filters.due_to ?? ''}
                        onChange={(e) => setFilters((f) => ({ ...f, due_to: e.target.value }))}
                    />
                </Stack>
            </Paper>

            {canCreate && showForm && (
                <Paper sx={{ mb: 3 }}>
                    <NewAssignmentForm onCreated={() => setShowForm(false)} />
                </Paper>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load assignments. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No assignments to show.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Title</TableCell>
                                    <TableCell>Class</TableCell>
                                    <TableCell>Subject</TableCell>
                                    <TableCell>Due</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((assignment) => (
                                    <TableRow key={assignment.id} hover>
                                        <TableCell>{assignment.title}</TableCell>
                                        <TableCell>{assignment.class_name ?? '—'}</TableCell>
                                        <TableCell>{assignment.subject_name ?? '—'}</TableCell>
                                        <TableCell>
                                            {assignment.due_at
                                                ? new Date(assignment.due_at).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={assignment.status}
                                                size="small"
                                                color={statusColor(assignment.status)}
                                                variant={assignment.status === 'archived' ? 'outlined' : 'filled'}
                                            />
                                        </TableCell>
                                        <TableCell align="right">
                                            {assignment.status === 'draft' && (
                                                <>
                                                    <IconButton size="small" onClick={() => setEditing(assignment)}>
                                                        <Pencil size={16} />
                                                    </IconButton>
                                                    <Button
                                                        size="small"
                                                        onClick={() => publishAssignment.mutate(assignment.id)}
                                                        disabled={publishAssignment.isPending}
                                                    >
                                                        Publish
                                                    </Button>
                                                </>
                                            )}
                                            {assignment.status === 'published' && (
                                                <Button
                                                    size="small"
                                                    onClick={() => archiveAssignment.mutate(assignment.id)}
                                                    disabled={archiveAssignment.isPending}
                                                >
                                                    Archive
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <EditAssignmentDialog
                assignment={editing}
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
            />
        </Box>
    );
}
