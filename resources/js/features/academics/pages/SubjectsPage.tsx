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
    MenuItem,
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
import { useQueryClient } from '@tanstack/react-query';
import { useCreateSubject, useDeleteSubject, useSubjects, useUpdateSubject, SUBJECTS_QUERY_KEY } from '../api/useSubjects';
import { useSchools } from '../api/useSchools';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { useAuth } from '../../../app/AuthProvider';
import { ExportButtons } from '../../../components/ExportButtons';
import { ImportDialog } from '../../../components/ImportDialog';
import type { School, Subject, SubjectRequest } from '../types/academic';

/** Mirrors SubjectPolicy::create/update/delete (RULES.md §5 academic.manage_subjects). */
const ROLES_THAT_CAN_MANAGE_SUBJECTS = ['tenant_admin', 'school_admin', 'academic_director'];

function SubjectDialog({
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
    initialValue: SubjectRequest;
    onClose: () => void;
    onSubmit: (values: SubjectRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
    showSchoolPicker: boolean;
    schools: School[];
}) {
    const [name, setName] = useState(initialValue.name);
    const [code, setCode] = useState(initialValue.code ?? '');
    const [schoolId, setSchoolId] = useState('');

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setCode(initialValue.code ?? '');
                    setSchoolId('');
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
                    {showSchoolPicker && (
                        <TextField
                            select
                            fullWidth
                            label="School"
                            value={schoolId}
                            onChange={(e) => setSchoolId(e.target.value)}
                            helperText="Which school does this subject belong to?"
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
                    disabled={!name || (showSchoolPicker && !schoolId) || isSubmitting}
                    onClick={() => onSubmit({ name, code: code || null, school_id: schoolId || undefined })}
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** Simple CRUD list for subjects: table + a Dialog for create/edit. */
export function SubjectsPage() {
    const { user } = useAuth();
    const queryClient = useQueryClient();
    const canManage = Boolean(user?.roles.some((role) => ROLES_THAT_CAN_MANAGE_SUBJECTS.includes(role)));
    const needsSchoolPicker = user?.school_id === null;
    const { data: schools } = useSchools();
    const { data, isLoading, isError } = useSubjects();
    const createSubject = useCreateSubject();
    const updateSubject = useUpdateSubject();
    const deleteSubject = useDeleteSubject();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<Subject | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

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
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save subject.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteSubject.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Subjects</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/subjects/export"
                        filenamePrefix="subjects"
                        onError={(message) => setExportError(message)}
                    />
                    <Button variant="outlined" onClick={() => setImportOpen(true)}>
                        Import
                    </Button>
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Subject
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
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((subject) => (
                                    <TableRow key={subject.id} hover>
                                        <TableCell>{subject.name}</TableCell>
                                        <TableCell>{subject.code ?? '—'}</TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(subject)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => handleDelete(subject.id)}>
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

            <SubjectDialog
                open={dialogOpen}
                initialValue={{ name: editingSubject?.name ?? '', code: editingSubject?.code ?? '' }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createSubject.isPending || updateSubject.isPending}
                serverError={serverError}
                showSchoolPicker={needsSchoolPicker && !editingSubject}
                schools={schools ?? []}
            />

            <ImportDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
                templateEndpoint="/subjects/import-template"
                importEndpoint="/subjects/import"
                resourceLabel="Subjects"
                showSchoolPicker={needsSchoolPicker}
                schools={schools ?? []}
                onImported={() => queryClient.invalidateQueries({ queryKey: SUBJECTS_QUERY_KEY })}
            />
        </Box>
    );
}
