import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
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
import { Plus } from 'lucide-react';
import { useAuth } from '../../../app/AuthProvider';
import { useAssignments, usePublishAssignment } from '../api/useAssignments';
import { NewAssignmentForm } from '../components/NewAssignmentForm';
import { ExportButtons } from '../../../components/ExportButtons';

const ROLES_THAT_CAN_CREATE = ['teacher', 'class_teacher'];

/**
 * Assignments visible to the current user (server-filtered — RULES §8: hide
 * UI for UX only, the API is still the source of truth). The "New Assignment"
 * form is additionally gated on role so non-teachers don't see a control
 * they'd get a 403 from.
 */
export function AssignmentsPage() {
    const { user } = useAuth();
    const { data, isLoading, isError } = useAssignments();
    const publishAssignment = usePublishAssignment();
    const [showForm, setShowForm] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const canCreate = Boolean(user?.roles.some((role) => ROLES_THAT_CAN_CREATE.includes(role)));

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
                                        <TableCell>{assignment.due_at ?? '—'}</TableCell>
                                        <TableCell>
                                            <Chip
                                                label={assignment.is_published ? 'Published' : 'Draft'}
                                                size="small"
                                                color={assignment.is_published ? 'success' : 'default'}
                                            />
                                        </TableCell>
                                        <TableCell align="right">
                                            {!assignment.is_published && (
                                                <Button
                                                    size="small"
                                                    onClick={() => publishAssignment.mutate(assignment.id)}
                                                    disabled={publishAssignment.isPending}
                                                >
                                                    Publish
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
        </Box>
    );
}
