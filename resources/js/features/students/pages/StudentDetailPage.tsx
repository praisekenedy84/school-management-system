import { useState } from 'react';
import { useParams } from 'react-router-dom';
import {
    Alert,
    Box,
    Chip,
    CircularProgress,
    Divider,
    Grid,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import { useStudent } from '../api/useStudents';
import { GuardianList } from '../components/GuardianList';
import { PromoteEnrolmentForm } from '../components/PromoteEnrolmentForm';

export function StudentDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { data: student, isLoading, isError, refetch } = useStudent(id);
    const [showPromote, setShowPromote] = useState(false);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (isError || !student) {
        return <Alert severity="error">Unable to load this student.</Alert>;
    }

    const currentEnrolment = student.current_enrolment;

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="flex-start" mb={2}>
                <Box>
                    <Typography variant="h5">{student.full_name}</Typography>
                    <Typography variant="body2" color="text.secondary">
                        Admission No. {student.admission_number}
                    </Typography>
                </Box>
                <Stack direction="row" spacing={1}>
                    <Chip
                        label={student.residence_type}
                        color={student.residence_type === 'boarding' ? 'secondary' : 'default'}
                    />
                    <Chip label={student.status} variant="outlined" />
                </Stack>
            </Stack>

            <Grid container spacing={2}>
                <Grid item xs={12} md={6}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="subtitle1" gutterBottom>
                            Student Information
                        </Typography>
                        <Stack spacing={1}>
                            <Typography variant="body2">
                                <strong>Gender:</strong> {student.gender ?? '—'}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Date of Birth:</strong> {student.date_of_birth ?? '—'}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Admitted:</strong> {student.admitted_at ?? '—'}
                            </Typography>
                        </Stack>

                        <Divider sx={{ my: 2 }} />

                        <Typography variant="subtitle1" gutterBottom>
                            Current Enrolment
                        </Typography>
                        {currentEnrolment ? (
                            <Stack spacing={1}>
                                <Typography variant="body2">
                                    <strong>Class:</strong> {currentEnrolment.class_name ?? currentEnrolment.class_id}
                                </Typography>
                                <Typography variant="body2">
                                    <strong>Session:</strong>{' '}
                                    {currentEnrolment.academic_session_name ?? currentEnrolment.academic_session_id}
                                </Typography>
                                <Typography variant="body2">
                                    <strong>Status:</strong> {currentEnrolment.status}
                                </Typography>
                            </Stack>
                        ) : (
                            <Typography variant="body2" color="text.secondary">
                                No enrolment on record.
                            </Typography>
                        )}

                        {currentEnrolment && (
                            <Box mt={2}>
                                {!showPromote ? (
                                    <Box>
                                        <Typography
                                            component="button"
                                            onClick={() => setShowPromote(true)}
                                            sx={{
                                                background: 'none',
                                                border: 'none',
                                                p: 0,
                                                color: 'primary.main',
                                                cursor: 'pointer',
                                                textDecoration: 'underline',
                                            }}
                                        >
                                            Promote / transfer this student
                                        </Typography>
                                    </Box>
                                ) : (
                                    <PromoteEnrolmentForm
                                        studentId={student.id}
                                        currentEnrolment={currentEnrolment}
                                        onPromoted={() => {
                                            setShowPromote(false);
                                            refetch();
                                        }}
                                    />
                                )}
                            </Box>
                        )}
                    </Paper>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Paper sx={{ p: 3 }}>
                        <GuardianList
                            studentId={student.id}
                            schoolId={student.school_id}
                            guardians={student.guardians}
                        />
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
}
