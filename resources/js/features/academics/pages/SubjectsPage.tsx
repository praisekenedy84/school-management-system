import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
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
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { useCreateSubject, useDeleteSubject, useSubjects, useUpdateSubject } from '../api/useSubjects';
import type { Subject, SubjectRequest } from '../types/academic';

function SubjectDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: SubjectRequest;
    onClose: () => void;
    onSubmit: (values: SubjectRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [name, setName] = useState(initialValue.name);
    const [code, setCode] = useState(initialValue.code ?? '');

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setCode(initialValue.code ?? '');
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Subject' : 'New Subject'}</DialogTitle>
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
                        fullWidth
                        label="Code"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!name || isSubmitting}
                    onClick={() => onSubmit({ name, code: code || null })}
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** Simple CRUD list for subjects: table + a Dialog for create/edit. */
export function SubjectsPage() {
    const { data, isLoading, isError } = useSubjects();
    const createSubject = useCreateSubject();
    const updateSubject = useUpdateSubject();
    const deleteSubject = useDeleteSubject();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<Subject | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingSubject(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (subject: Subject) => {
        setEditingSubject(subject);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: SubjectRequest) => {
        setServerError(null);
        try {
            if (editingSubject) {
                await updateSubject.mutateAsync({ id: editingSubject.id, payload: values });
            } else {
                await createSubject.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error: any) {
            setServerError(error?.response?.data?.message ?? 'Unable to save subject.');
        }
    };

    const handleDelete = (id: string) => {
        deleteSubject.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Subjects</Typography>
                <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
                    New Subject
                </Button>
            </Stack>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load subjects. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No subjects have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Code</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((subject) => (
                                    <TableRow key={subject.id} hover>
                                        <TableCell>{subject.name}</TableCell>
                                        <TableCell>{subject.code ?? '—'}</TableCell>
                                        <TableCell align="right">
                                            <IconButton size="small" onClick={() => openEdit(subject)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                            <IconButton size="small" onClick={() => handleDelete(subject.id)}>
                                                <DeleteIcon fontSize="small" />
                                            </IconButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <SubjectDialog
                open={dialogOpen}
                initialValue={{ name: editingSubject?.name ?? '', code: editingSubject?.code ?? '' }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createSubject.isPending || updateSubject.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
