import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    MenuItem,
    Paper,
    Stack,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useClasses } from '../../academics/api/useClasses';
import { useStudents } from '../../students/api/useStudents';
import {
    downloadClassReportCards,
    downloadStudentReportCard,
    useBulkGenerateReportCards,
    useGenerateReportCard,
    useReportCard,
} from '../api/useReportCard';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { ExcludedReportCardStudent } from '../types/assessment';

export function ReportCardPage() {
    const { data: studentsPage, isLoading: studentsLoading } = useStudents(1, 200);
    const { data: sessions, isLoading: sessionsLoading } = useAcademicSessions();
    const { data: classes, isLoading: classesLoading } = useClasses();

    const [mode, setMode] = useState<'student' | 'class'>('class');
    const [studentId, setStudentId] = useState('');
    const [classId, setClassId] = useState('');
    const [academicSessionId, setAcademicSessionId] = useState('');
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [excludedStudents, setExcludedStudents] = useState<ExcludedReportCardStudent[]>([]);
    const [downloading, setDownloading] = useState(false);

    const {
        data: reportCard,
        isLoading: reportCardLoading,
        isFetching: reportCardFetching,
        refetch: refetchReportCard,
    } = useReportCard(mode === 'student' ? studentId || undefined : undefined, academicSessionId || undefined);

    const generateReportCard = useGenerateReportCard(studentId || undefined);
    const bulkGenerate = useBulkGenerateReportCards();

    const handleGenerateStudent = async () => {
        setServerError(null);
        setSuccessMessage(null);
        try {
            const result = await generateReportCard.mutateAsync({ academic_session_id: academicSessionId });
            if (result.withheld) {
                setServerError(result.message);
            } else {
                setSuccessMessage(result.message ?? 'Report card generated successfully.');
                await refetchReportCard();
            }
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to generate report card.'));
        }
    };

    const handleGenerateClass = async () => {
        setServerError(null);
        setSuccessMessage(null);
        setExcludedStudents([]);
        try {
            const result = await bulkGenerate.mutateAsync({
                class_id: classId,
                academic_session_id: academicSessionId,
            });
            setSuccessMessage(
                `${result.message} ${result.included_count} report card(s) included in the PDF.`,
            );
            setExcludedStudents(result.excluded_students ?? []);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to generate class report cards.'));
        }
    };

    const handleDownloadStudent = async () => {
        if (!studentId || !academicSessionId) return;
        setDownloading(true);
        setServerError(null);
        try {
            await downloadStudentReportCard(studentId, academicSessionId);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to download report card.'));
        } finally {
            setDownloading(false);
        }
    };

    const handleDownloadClass = async () => {
        if (!classId || !academicSessionId) return;
        setDownloading(true);
        setServerError(null);
        try {
            await downloadClassReportCards(classId, academicSessionId);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to download class report cards.'));
        } finally {
            setDownloading(false);
        }
    };

    const canGenerateStudent = Boolean(studentId && academicSessionId);
    const canGenerateClass = Boolean(classId && academicSessionId);
    const isGenerating = generateReportCard.isPending || bulkGenerate.isPending;

    return (
        <Box maxWidth={760}>
            <Typography variant="h5" gutterBottom>
                Report Cards
            </Typography>
            <Typography variant="body2" color="text.secondary" gutterBottom>
                Generate report card PDFs for individual students or an entire class. Results must be
                published before generation.
            </Typography>

            <Tabs value={mode} onChange={(_, value) => setMode(value)} sx={{ mb: 2 }}>
                <Tab value="class" label="Whole Class" />
                <Tab value="student" label="Single Student" />
            </Tabs>

            <Paper sx={{ p: 3, mb: 3 }}>
                <Stack spacing={2}>
                    {mode === 'student' ? (
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
                    ) : (
                        <TextField
                            select
                            fullWidth
                            label="Class"
                            value={classId}
                            onChange={(e) => setClassId(e.target.value)}
                            disabled={classesLoading}
                        >
                            {(classes ?? []).map((classRoom) => (
                                <MenuItem key={classRoom.id} value={classRoom.id}>
                                    {classRoom.name}
                                </MenuItem>
                            ))}
                        </TextField>
                    )}

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

                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="contained"
                            disabled={
                                isGenerating ||
                                (mode === 'student' ? !canGenerateStudent : !canGenerateClass)
                            }
                            onClick={mode === 'student' ? handleGenerateStudent : handleGenerateClass}
                        >
                            {isGenerating ? 'Generating…' : 'Generate Report Cards'}
                        </Button>
                        <Button
                            variant="outlined"
                            disabled={
                                downloading ||
                                (mode === 'student' ? !canGenerateStudent : !canGenerateClass)
                            }
                            onClick={mode === 'student' ? handleDownloadStudent : handleDownloadClass}
                        >
                            {downloading ? 'Downloading…' : 'Download PDF'}
                        </Button>
                    </Stack>
                </Stack>
            </Paper>

            {serverError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {serverError}
                </Alert>
            )}
            {successMessage && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    {successMessage}
                </Alert>
            )}
            {excludedStudents.length > 0 && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                        Students excluded from this PDF (fee gate):
                    </Typography>
                    <ul style={{ margin: 0, paddingLeft: 20 }}>
                        {excludedStudents.map((student) => (
                            <li key={student.student_id}>
                                {student.student_name} — {student.reason}
                            </li>
                        ))}
                    </ul>
                </Alert>
            )}

            {mode === 'student' && canGenerateStudent && (reportCardLoading || reportCardFetching) && (
                <Box display="flex" justifyContent="center" py={4}>
                    <CircularProgress size={28} />
                </Box>
            )}

            {mode === 'student' && canGenerateStudent && !reportCardLoading && reportCard === null && (
                <Alert severity="info">No report card has been generated yet for this academic session.</Alert>
            )}

            {mode === 'student' && canGenerateStudent && !reportCardLoading && reportCard && (
                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Report Card Ready
                    </Typography>
                    {reportCard.withheld ? (
                        <Alert severity="warning">{reportCard.withheld_reason}</Alert>
                    ) : (
                        <>
                            <Typography variant="body2">
                                Generated at: {reportCard.generated_at ?? '—'}
                            </Typography>
                            <Button sx={{ mt: 2 }} variant="contained" onClick={handleDownloadStudent} disabled={downloading}>
                                Download PDF
                            </Button>
                        </>
                    )}
                </Paper>
            )}
        </Box>
    );
}
