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
import { Plus, Pencil, Trash2, BookOpen } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useClasses, CLASSES_QUERY_KEY } from '../api/useClasses';
import { useCreateClassRoom, useDeleteClassRoom, useUpdateClassRoom } from '../api/useClassRoomMutations';
import { useSchools } from '../api/useSchools';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { ClassSubjectsDrawer } from '../components/ClassSubjectsDrawer';
import { ExportButtons } from '../../../components/ExportButtons';
import { ImportDialog } from '../../../components/ImportDialog';
import type { ClassRoom, ClassRoomRequest, School } from '../types/academic';

/** Mirrors ClassRoomPolicy::create/update/delete (RULES.md §5 academic.manage_classes). */

function ClassRoomDialog({
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
    initialValue: ClassRoomRequest;
    onClose: () => void;
    onSubmit: (values: ClassRoomRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
    showSchoolPicker: boolean;
    schools: School[];
}) {
    const [name, setName] = useState(initialValue.name);
    const [level, setLevel] = useState(initialValue.level != null ? String(initialValue.level) : '');
    const [schoolId, setSchoolId] = useState('');

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setLevel(initialValue.level != null ? String(initialValue.level) : '');
                    setSchoolId('');
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Class' : 'New Class'}</DialogTitle>
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
                            helperText="Which school does this class belong to?"
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
                        label="Level"
                        type="number"
                        value={level}
                        onChange={(e) => setLevel(e.target.value)}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!name || (showSchoolPicker && !schoolId) || isSubmitting}
                    onClick={() =>
                        onSubmit({ name, level: level ? Number(level) : null, school_id: schoolId || undefined })
                    }
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** CRUD list for classes, plus a per-class subjects drawer (attach/detach). */
export function ClassesPage() {
    const { user, canAction } = usePermissions();
    const queryClient = useQueryClient();
    const canManage = canAction('manageClasses');
    const needsSchoolPicker = user?.school_id === null;
    const { data: schools } = useSchools();
    const { data, isLoading, isError } = useClasses();
    const createClassRoom = useCreateClassRoom();
    const updateClassRoom = useUpdateClassRoom();
    const deleteClassRoom = useDeleteClassRoom();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingClass, setEditingClass] = useState<ClassRoom | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [subjectsDrawerClass, setSubjectsDrawerClass] = useState<ClassRoom | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingClass(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (classRoom: ClassRoom) => {
        setEditingClass(classRoom);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: ClassRoomRequest) => {
        setServerError(null);
        try {
            if (editingClass) {
                await updateClassRoom.mutateAsync({ id: editingClass.id, payload: values });
            } else {
                await createClassRoom.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save class.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteClassRoom.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Classes</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/classes/export"
                        filenamePrefix="classes"
                        onError={(message) => setExportError(message)}
                    />
                    <Button variant="outlined" onClick={() => setImportOpen(true)}>
                        Import
                    </Button>
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Class
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

            {isError && <Alert severity="error">Unable to load classes. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No classes have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Level</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((classRoom) => (
                                    <TableRow key={classRoom.id} hover>
                                        <TableCell>{classRoom.name}</TableCell>
                                        <TableCell>{classRoom.level ?? '—'}</TableCell>
                                        <TableCell align="right">
                                            <IconButton
                                                size="small"
                                                onClick={() => setSubjectsDrawerClass(classRoom)}
                                                title="Manage subjects"
                                            >
                                                <BookOpen size={16} />
                                            </IconButton>
                                            {canManage && (
                                                <>
                                                    <IconButton size="small" onClick={() => openEdit(classRoom)}>
                                                        <Pencil size={16} />
                                                    </IconButton>
                                                    <IconButton
                                                        size="small"
                                                        onClick={() => handleDelete(classRoom.id)}
                                                    >
                                                        <Trash2 size={16} />
                                                    </IconButton>
                                                </>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <ClassRoomDialog
                open={dialogOpen}
                initialValue={{
                    name: editingClass?.name ?? '',
                    level: editingClass?.level != null ? Number(editingClass.level) : null,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createClassRoom.isPending || updateClassRoom.isPending}
                serverError={serverError}
                showSchoolPicker={needsSchoolPicker && !editingClass}
                schools={schools ?? []}
            />

            <ClassSubjectsDrawer
                open={Boolean(subjectsDrawerClass)}
                classRoom={subjectsDrawerClass}
                onClose={() => setSubjectsDrawerClass(null)}
            />

            <ImportDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
                templateEndpoint="/classes/import-template"
                importEndpoint="/classes/import"
                resourceLabel="Classes"
                showSchoolPicker={needsSchoolPicker}
                schools={schools ?? []}
                onImported={() => queryClient.invalidateQueries({ queryKey: CLASSES_QUERY_KEY })}
            />
        </Box>
    );
}
