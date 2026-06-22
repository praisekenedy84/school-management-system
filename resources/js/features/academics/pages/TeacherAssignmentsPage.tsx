import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
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
import { Trash2 } from 'lucide-react';
import { useClasses } from '../api/useClasses';
import { useAcademicSessions } from '../api/useAcademicSessions';
import { useSubjects } from '../api/useSubjects';
import { useCreateTeacherAssignment, useDeleteTeacherAssignment, useTeacherAssignments } from '../api/useTeacherAssignments';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { useAuth } from '../../../app/AuthProvider';
import { ExportButtons } from '../../../components/ExportButtons';

/** Mirrors TeacherAssignmentPolicy::create/delete — tenant_admin/school_admin ONLY. */
const ROLES_THAT_CAN_MANAGE_TEACHER_ASSIGNMENTS = ['tenant_admin', 'school_admin'];

/**
 * Filterable list + create form for teacher↔(class, subject, session)
 * assignments. There is no users/teachers-listing endpoint anywhere in the
 * backend yet (checked routes/tenant.php) — the same gap GuardianList.tsx
 * already lives with for guardian linking — so the teacher picker is a
 * free-text user-id field, same as that established precedent, with a TODO
 * to replace it once a teacher lookup endpoint exists.
 */
export function TeacherAssignmentsPage() {
    const { user } = useAuth();
    const canManage = Boolean(
        user?.roles.some((role) => ROLES_THAT_CAN_MANAGE_TEACHER_ASSIGNMENTS.includes(role)),
    );

    const { data: classes } = useClasses();
    const { data: sessions } = useAcademicSessions();
    const { data: subjects } = useSubjects();

    const [classFilter, setClassFilter] = useState('');
    const { data, isLoading, isError } = useTeacherAssignments(classFilter ? { class_id: classFilter } : {});

    const createAssignment = useCreateTeacherAssignment();
    const deleteAssignment = useDeleteTeacherAssignment();

    const [teacherId, setTeacherId] = useState('');
    const [classId, setClassId] = useState('');
    const [subjectId, setSubjectId] = useState('');
    const [academicSessionId, setAcademicSessionId] = useState('');
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const canCreate = Boolean(teacherId && classId && subjectId && academicSessionId);

    const handleCreate = async () => {
        setServerError(null);
        try {
            await createAssignment.mutateAsync({
                teacher_id: teacherId,
                class_id: classId,
                subject_id: subjectId,
                academic_session_id: academicSessionId,
            });
            setTeacherId('');
            setClassId('');
            setSubjectId('');
            setAcademicSessionId('');
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to create teacher assignment.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteAssignment.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Teacher Assignments</Typography>
                <Stack direction="row" spacing={2} alignItems="center">
                    <TextField
                        select
                        size="small"
                        label="Filter by class"
                        value={classFilter}
                        onChange={(e) => setClassFilter(e.target.value)}
                        sx={{ minWidth: 220 }}
                    >
                        <MenuItem value="">All classes</MenuItem>
                        {(classes ?? []).map((classRoom) => (
                            <MenuItem key={classRoom.id} value={classRoom.id}>
                                {classRoom.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <ExportButtons
                        endpoint="/teacher-assignments/export"
                        filenamePrefix="teacher-assignments"
                        params={classFilter ? { class_id: classFilter } : undefined}
                        onError={(message) => setExportError(message)}
                    />
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            {canManage && (
                <Paper sx={{ p: 3, mb: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        New Assignment
                    </Typography>

                    {serverError && (
                        <Alert severity="error" sx={{ mb: 2 }}>
                            {serverError}
                        </Alert>
                    )}

                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} mb={2}>
                        {/* TODO: replace free-text teacher_id with a teacher search/picker once a
                            users/teachers lookup endpoint exists (no such endpoint exists today —
                            see routes/tenant.php; same gap as GuardianList.tsx's guardian_id field). */}
                        <TextField
                            fullWidth
                            label="Teacher User ID (UUID)"
                            value={teacherId}
                            onChange={(e) => setTeacherId(e.target.value)}
                        />
                        <TextField
                            select
                            fullWidth
                            label="Class"
                            value={classId}
                            onChange={(e) => setClassId(e.target.value)}
                        >
                            {(classes ?? []).map((classRoom) => (
                                <MenuItem key={classRoom.id} value={classRoom.id}>
                                    {classRoom.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            fullWidth
                            label="Subject"
                            value={subjectId}
                            onChange={(e) => setSubjectId(e.target.value)}
                        >
                            {(subjects?.data ?? []).map((subject) => (
                                <MenuItem key={subject.id} value={subject.id}>
                                    {subject.name}
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

                    <Button
                        variant="contained"
                        disabled={!canCreate || createAssignment.isPending}
                        onClick={handleCreate}
                    >
                        {createAssignment.isPending ? 'Saving…' : 'Assign'}
                    </Button>
                </Paper>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load teacher assignments. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No teacher assignments have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Teacher</TableCell>
                                    <TableCell>Class</TableCell>
                                    <TableCell>Subject</TableCell>
                                    <TableCell>Academic Session</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((assignment) => (
                                    <TableRow key={assignment.id} hover>
                                        <TableCell>{assignment.teacher_name ?? assignment.teacher_id}</TableCell>
                                        <TableCell>{assignment.class_name ?? assignment.class_id}</TableCell>
                                        <TableCell>{assignment.subject_name ?? assignment.subject_id}</TableCell>
                                        <TableCell>
                                            {assignment.academic_session_name ?? assignment.academic_session_id}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton
                                                    size="small"
                                                    onClick={() => handleDelete(assignment.id)}
                                                >
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
        </Box>
    );
}
