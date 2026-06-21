import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    MenuItem,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useStudents } from '../../students/api/useStudents';
import { useGenerateReportCard, useReportCard } from '../api/useReportCard';

/**
 * Pick a student + academic session, queue PDF generation, and show the
 * stored pointer once ready. There is no download/stream endpoint yet —
 * see the TODO below — so this only displays `file_path` and `generated_at`.
 */
export function ReportCardPage() {
    const { data: studentsPage, isLoading: studentsLoading } = useStudents(1, 200);
    const { data: sessions, isLoading: sessionsLoading } = useAcademicSessions();

    const [studentId, setStudentId] = useState('');
    const [academicSessionId, setAcademicSessionId] = useState('');
    const [queuedMessage, setQueuedMessage] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const {
        data: reportCard,
        isLoading: reportCardLoading,
        isFetching: reportCardFetching,
    } = useReportCard(studentId || undefined, academicSessionId || undefined);

    const generateReportCard = useGenerateReportCard(studentId || undefined);

    const handleGenerate = async () => {
        setServerError(null);
        setQueuedMessage(null);
        try {
            const result = await generateReportCard.mutateAsync({ academic_session_id: academicSessionId });
            setQueuedMessage(result.message);
        } catch (error: any) {
            setServerError(error?.response?.data?.message ?? 'Unable to queue report card generation.');
        }
    };

    const canGenerate = Boolean(studentId && academicSessionId);

    return (
        <Box maxWidth={720}>
            <Typography variant="h5" gutterBottom>
                Report Card
            </Typography>

            <Paper sx={{ p: 3, mb: 3 }}>
                <Stack spacing={2}>
                    <TextField
                        select
                        fullWidth
                        label="Student"
                        value={studentId}
                        onChange={(e) => setStudentId(e.target.value)}
                        disabled={studentsLoading}
                    >
                        {(studentsPage?.data ?? []).map((student) => (
                            <MenuItem key={student.id} value={student.id}>
                                {student.full_name} ({student.admission_number})
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        select
                        fullWidth
                        label="Academic Session"
                        value={academicSessionId}
                        onChange={(e) => setAcademicSessionId(e.target.value)}
                        disabled={sessionsLoading}
                    >
                        {(sessions ?? []).map((session) => (
                            <MenuItem key={session.id} value={session.id}>
                                {session.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <Box>
                        <Button
                            variant="contained"
                            disabled={!canGenerate || generateReportCard.isPending}
                            onClick={handleGenerate}
                        >
                            {generateReportCard.isPending ? 'Queuing…' : 'Generate'}
                        </Button>
                    </Box>
                </Stack>
            </Paper>

            {serverError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {serverError}
                </Alert>
            )}
            {queuedMessage && (
                <Alert severity="info" sx={{ mb: 2 }}>
                    {queuedMessage} Generation runs in the background — refresh in a moment to see it below.
                </Alert>
            )}

            {canGenerate && (reportCardLoading || reportCardFetching) && (
                <Box display="flex" justifyContent="center" py={4}>
                    <CircularProgress size={28} />
                </Box>
            )}

            {canGenerate && !reportCardLoading && reportCard === null && (
                <Alert severity="info">No report card has been generated yet for this academic session.</Alert>
            )}

            {canGenerate && !reportCardLoading && reportCard && (
                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Report Card Ready
                    </Typography>
                    <Typography variant="body2">
                        Generated at: {reportCard.generated_at ?? '—'}
                    </Typography>
                    <Typography variant="body2" sx={{ mt: 1 }}>
                        Storage path: {reportCard.file_path ?? '—'}
                    </Typography>
                    <Alert severity="warning" sx={{ mt: 2 }}>
                        TODO: there is no download/view endpoint yet — `file_path` is a server storage
                        path, not a URL. Ask api-builder for a signed download route (e.g.
                        GET /api/v1/students/&#123;student&#125;/report-card/download) before linking this
                        to an actual PDF.
                    </Alert>
                </Paper>
            )}
        </Box>
    );
}
