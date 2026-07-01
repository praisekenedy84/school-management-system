import { useParams, Link as RouterLink } from 'react-router-dom';
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
import { useStudent } from '../api/useStudents';
import { usePaymentSlips } from '../../finance/api/usePaymentSlips';
import { SlipStatusBadge } from '../../finance/components/SlipStatusBadge';
import { useAttendanceForStudent } from '../../attendance/api/useAttendance';
import { useResults } from '../../assessment/api/useResults';
import { downloadStudentReportCard, useReportCard } from '../../assessment/api/useReportCard';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { FeeStatementPanel } from '../../finance/components/FeeStatementPanel';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';

/**
 * Fees & payment slips section of the ward drill-down — scoped to one child
 * via `usePaymentSlips({ student_id })` (PaymentSlipController::index ANDs
 * the student_id filter with ward-scoping for a parent).
 */
function WardFeeSlips({ studentId }: { studentId: string }) {
    const { data, isLoading, isError, error } = usePaymentSlips({ student_id: studentId });

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={3}>
                <CircularProgress size={24} />
            </Box>
        );
    }

    if (isError) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load payment slips.')}</Alert>;
    }

    if (!data || data.data.length === 0) {
        return <Alert severity="info">No payment slips submitted for this child yet.</Alert>;
    }

    return (
        <TableContainer>
            <Table size="small">
                <TableHead>
                    <TableRow>
                        <TableCell>Slip Number</TableCell>
                        <TableCell>Deposit Date</TableCell>
                        <TableCell>Amount</TableCell>
                        <TableCell>Status</TableCell>
                        <TableCell align="right">Actions</TableCell>
                    </TableRow>
                </TableHead>
                <TableBody>
                    {data.data.map((slip) => (
                        <TableRow key={slip.id} hover>
                            <TableCell>{slip.slip_number}</TableCell>
                            <TableCell>{slip.deposit_date ?? '—'}</TableCell>
                            <TableCell>{formatMoney(slip.total_amount, slip.currency)}</TableCell>
                            <TableCell>
                                <SlipStatusBadge status={slip.status} />
                            </TableCell>
                            <TableCell align="right">
                                <Button size="small" component={RouterLink} to={`/finance/my-slips/${slip.id}`}>
                                    View
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </TableContainer>
    );
}

/**
 * Attendance history section — `useAttendanceForStudent` (paginated,
 * newest-first; AttendanceController::index's `student_id` branch).
 */
function WardAttendanceHistory({ studentId }: { studentId: string }) {
    const { data, isLoading, isError, error } = useAttendanceForStudent(studentId);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={3}>
                <CircularProgress size={24} />
            </Box>
        );
    }

    if (isError) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load attendance history.')}</Alert>;
    }

    if (!data || data.data.length === 0) {
        return <Alert severity="info">No attendance records for this child yet.</Alert>;
    }

    const counts = data.data.reduce(
        (acc, record) => {
            acc[record.status] = (acc[record.status] ?? 0) + 1;
            return acc;
        },
        { present: 0, absent: 0, late: 0, excused: 0 } as Record<string, number>,
    );

    return (
        <Stack spacing={2}>
            <Stack direction="row" spacing={1} flexWrap="wrap">
                <Chip size="small" color="success" label={`Present: ${counts.present}`} />
                <Chip size="small" color="error" label={`Absent: ${counts.absent}`} />
                <Chip size="small" color="warning" label={`Late: ${counts.late}`} />
                <Chip size="small" color="info" label={`Excused: ${counts.excused}`} />
            </Stack>
            <TableContainer>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Date</TableCell>
                            <TableCell>Period</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Note</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {data.data.map((record) => (
                            <TableRow key={record.id} hover>
                                <TableCell>{record.attendance_date ?? '—'}</TableCell>
                                <TableCell>{record.period ?? '—'}</TableCell>
                                <TableCell>
                                    <Chip size="small" label={record.status} />
                                </TableCell>
                                <TableCell>{record.note ?? '—'}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>
        </Stack>
    );
}

/**
 * Report card section for parents — shows fee-gate message or download button.
 */
function WardReportCard({ studentId }: { studentId: string }) {
    const { data: sessions } = useAcademicSessions();
    const activeSession = sessions?.find((session) => session.is_current) ?? sessions?.[0];
    const [downloading, setDownloading] = useState(false);
    const [downloadError, setDownloadError] = useState<string | null>(null);

    const {
        data: reportCard,
        isLoading,
        isError,
        error,
    } = useReportCard(studentId, activeSession?.id);

    const handleDownload = async () => {
        if (!activeSession?.id) return;
        setDownloading(true);
        setDownloadError(null);
        try {
            await downloadStudentReportCard(studentId, activeSession.id);
        } catch (err) {
            setDownloadError(getErrorMessage(err, 'Unable to download report card.'));
        } finally {
            setDownloading(false);
        }
    };

    if (!activeSession) {
        return <Alert severity="info">No academic session is configured yet.</Alert>;
    }

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={3}>
                <CircularProgress size={24} />
            </Box>
        );
    }

    if (isError) {
        const message = getErrorMessage(error, 'Unable to load report card.');
        if (message.toLowerCase().includes('outstanding') || message.toLowerCase().includes('unavailable')) {
            return <Alert severity="warning">{message}</Alert>;
        }
        return <Alert severity="error">{message}</Alert>;
    }

    if (!reportCard) {
        return <Alert severity="info">No report card has been published for this term yet.</Alert>;
    }

    if (reportCard.withheld) {
        return (
            <Alert severity="warning">
                {reportCard.withheld_reason ??
                    'Report card is currently unavailable. Please ensure outstanding fees are settled.'}
            </Alert>
        );
    }

    return (
        <Stack spacing={1}>
            <Typography variant="body2">
                Report card for {activeSession.name} is ready.
            </Typography>
            {downloadError && <Alert severity="error">{downloadError}</Alert>}
            <Button variant="contained" disabled={downloading} onClick={handleDownload}>
                {downloading ? 'Downloading…' : 'Download Report Card'}
            </Button>
        </Stack>
    );
}

/**
 * Results section — `useResults({ student_id })`. The API only returns
 * published results to a parent (ResultController::index), so no client-side
 * `is_published` filtering is needed.
 */
function WardResults({ studentId }: { studentId: string }) {
    const { data, isLoading, isError, error } = useResults({ student_id: studentId });

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={3}>
                <CircularProgress size={24} />
            </Box>
        );
    }

    if (isError) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load results.')}</Alert>;
    }

    if (!data || data.data.length === 0) {
        return <Alert severity="info">No published results yet.</Alert>;
    }

    return (
        <TableContainer>
            <Table size="small">
                <TableHead>
                    <TableRow>
                        <TableCell>Subject</TableCell>
                        <TableCell>Assessment</TableCell>
                        <TableCell>Score</TableCell>
                        <TableCell>Grade</TableCell>
                    </TableRow>
                </TableHead>
                <TableBody>
                    {data.data.map((result) => (
                        <TableRow key={result.id} hover>
                            <TableCell>{result.subject_name ?? '—'}</TableCell>
                            <TableCell>{result.assessment_name ?? '—'}</TableCell>
                            <TableCell>{result.score ?? '—'}</TableCell>
                            <TableCell>{result.grade ?? '—'}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </TableContainer>
    );
}

/**
 * Parent-facing per-child drill-down (PRD §5.10) — reached only by clicking a
 * ward card on the dashboard. Deliberately NOT the staff `StudentDetailPage`
 * (which exposes guardian-linking and promote/transfer controls). Each
 * section (fees, attendance, results) loads and fails independently so one
 * section's error never blanks the whole page.
 */
export function WardDetailPage() {
    const { studentId } = useParams<{ studentId: string }>();
    const { data: student, isLoading, isError, error } = useStudent(studentId);
    const { data: sessions } = useAcademicSessions();
    const activeSession = sessions?.find((session) => session.is_current) ?? sessions?.[0];

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (isError || !student) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load this child.')}</Alert>;
    }

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="flex-start" mb={2}>
                <Box>
                    <Typography variant="h5">{student.full_name}</Typography>
                    <Typography variant="body2" color="text.secondary">
                        {student.current_enrolment?.class_name ?? 'Not enrolled'}
                    </Typography>
                </Box>
                <Chip
                    label={student.residence_type}
                    color={student.residence_type === 'boarding' ? 'secondary' : 'default'}
                />
            </Stack>

            <Stack spacing={3}>
                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Fee Statement
                    </Typography>
                    {activeSession ? (
                        <FeeStatementPanel studentId={student.id} academicSessionId={activeSession.id} />
                    ) : (
                        <Alert severity="info">No academic session is configured yet.</Alert>
                    )}
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Fees & Payment Slips
                    </Typography>
                    <WardFeeSlips studentId={student.id} />
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Attendance
                    </Typography>
                    <WardAttendanceHistory studentId={student.id} />
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Report Card
                    </Typography>
                    <WardReportCard studentId={student.id} />
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <Typography variant="subtitle1" gutterBottom>
                        Results
                    </Typography>
                    <WardResults studentId={student.id} />
                </Paper>
            </Stack>

            <Box mt={2}>
                <Button component={RouterLink} to="/">
                    Back to Dashboard
                </Button>
            </Box>
        </Box>
    );
}
