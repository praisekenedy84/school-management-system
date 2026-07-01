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
import { Plus, Pencil, Trash2 } from 'lucide-react';
import { usePermissions } from '../../../lib/usePermissions';
import {
    useAssessments,
    useCreateAssessment,
    useDeleteAssessment,
    usePublishAssessment,
    useUpdateAssessment,
} from '../api/useAssessments';
import { AssessmentDialog } from '../components/AssessmentDialog';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { ExportButtons } from '../../../components/ExportButtons';
import type { Assessment, AssessmentRequest } from '../types/assessment';

export function AssessmentsPage() {
    const { canAction } = usePermissions();
    const { data, isLoading, isError } = useAssessments();
    const createAssessment = useCreateAssessment();
    const updateAssessment = useUpdateAssessment();
    const deleteAssessment = useDeleteAssessment();
    const publishAssessment = usePublishAssessment();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingAssessment, setEditingAssessment] = useState<Assessment | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const canPublish = canAction('publishResults');
    const canManage = canAction('manageAssessments');

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
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save assessment.'));
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
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/assessments/export"
                        filenamePrefix="assessments"
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Assessment
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
                                    <TableCell>Category</TableCell>
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
                                        <TableCell>{assessment.category_label ?? assessment.category}</TableCell>
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
                                            {canManage && (
                                                <>
                                                    <IconButton size="small" onClick={() => openEdit(assessment)}>
                                                        <Pencil size={16} />
                                                    </IconButton>
                                                    <IconButton size="small" onClick={() => handleDelete(assessment.id)}>
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

            <AssessmentDialog
                open={dialogOpen}
                initialValue={{
                    subject_id: editingAssessment?.subject_id ?? '',
                    academic_session_id: editingAssessment?.academic_session_id ?? '',
                    name: editingAssessment?.name ?? '',
                    category: editingAssessment?.category ?? 'continuous_assessment',
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
