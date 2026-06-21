import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    IconButton,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { useAuth } from '../../../app/AuthProvider';
import {
    useAssessments,
    useCreateAssessment,
    useDeleteAssessment,
    usePublishAssessment,
    useUpdateAssessment,
} from '../api/useAssessments';
import { AssessmentDialog } from '../components/AssessmentDialog';
import type { Assessment, AssessmentRequest } from '../types/assessment';

const ROLES_THAT_CAN_PUBLISH = ['academic_director', 'school_admin', 'tenant_admin'];

/**
 * CRUD list for assessment definitions, mirroring SubjectsPage's table +
 * Dialog shape. Adds a per-row "Publish" action gated to the roles
 * ResultRecordPolicy::publish() actually grants — hiding it for other roles
 * is UX only, the API authorizes again server-side (RULES §8).
 */
export function AssessmentsPage() {
    const { user } = useAuth();
    const { data, isLoading, isError } = useAssessments();
    const createAssessment = useCreateAssessment();
    const updateAssessment = useUpdateAssessment();
    const deleteAssessment = useDeleteAssessment();
    const publishAssessment = usePublishAssessment();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingAssessment, setEditingAssessment] = useState<Assessment | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const canPublish = Boolean(user?.roles.some((role) => ROLES_THAT_CAN_PUBLISH.includes(role)));

    const openCreate = () => {
        setEditingAssessment(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (assessment: Assessment) => {
        setEditingAssessment(assessment);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: AssessmentRequest) => {
        setServerError(null);
        try {
            if (editingAssessment) {
                await updateAssessment.mutateAsync({ id: editingAssessment.id, payload: values });
            } else {
                await createAssessment.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error: any) {
            setServerError(error?.response?.data?.message ?? 'Unable to save assessment.');
        }
    };

    const handleDelete = (id: string) => {
        deleteAssessment.mutate(id);
    };

    const handlePublish = (id: string) => {
        publishAssessment.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Assessments</Typography>
                <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
                    New Assessment
                </Button>
            </Stack>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load assessments. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No assessments have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Subject</TableCell>
                                    <TableCell>Academic Session</TableCell>
                                    <TableCell>Weight</TableCell>
                                    <TableCell>Max Score</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((assessment) => (
                                    <TableRow key={assessment.id} hover>
                                        <TableCell>{assessment.name}</TableCell>
                                        <TableCell>{assessment.subject_name ?? '—'}</TableCell>
                                        <TableCell>{assessment.academic_session_name ?? '—'}</TableCell>
                                        <TableCell>{assessment.weight}%</TableCell>
                                        <TableCell>{assessment.max_score}</TableCell>
                                        <TableCell align="right">
                                            {canPublish && (
                                                <Button
                                                    size="small"
                                                    onClick={() => handlePublish(assessment.id)}
                                                    disabled={publishAssessment.isPending}
                                                >
                                                    Publish
                                                </Button>
                                            )}
                                            <IconButton size="small" onClick={() => openEdit(assessment)}>
                                                <EditIcon fontSize="small" />
                                            </IconButton>
                                            <IconButton size="small" onClick={() => handleDelete(assessment.id)}>
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

            <AssessmentDialog
                open={dialogOpen}
                initialValue={{
                    subject_id: editingAssessment?.subject_id ?? '',
                    academic_session_id: editingAssessment?.academic_session_id ?? '',
                    name: editingAssessment?.name ?? '',
                    weight: editingAssessment?.weight ?? 0,
                    max_score: editingAssessment?.max_score ?? 100,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createAssessment.isPending || updateAssessment.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
