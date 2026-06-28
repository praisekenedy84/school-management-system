import { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    MenuItem,
    Paper,
    Radio,
    RadioGroup,
    FormControlLabel,
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
import { useClasses } from '../../academics/api/useClasses';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { SearchableSelect } from '../../../components/SearchableSelect';
import { toNameOptions } from '../../../lib/selectOptions';
import { useStudents } from '../../students/api/useStudents';
import { useAttendanceForClass, useRecordAttendance } from '../api/useAttendance';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { ExportButtons } from '../../../components/ExportButtons';
import type { AttendanceStatus } from '../types/attendance';

const STATUS_OPTIONS: { value: AttendanceStatus; label: string }[] = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'late', label: 'Late' },
    { value: 'excused', label: 'Excused' },
];

/**
 * TODO: there is no "students in this class" endpoint yet — GET
 * /api/v1/students is school-wide, not class-filtered (a student's class
 * lives on their `current_enrolment`, but the list endpoint doesn't accept
 * a `class_id` filter). We work around this by loading every student in the
 * school (a generous `per_page`) and filtering client-side by
 * `current_enrolment.class_id`. Ask api-builder to add `?class_id=` to
 * GET /api/v1/students, or a dedicated roster endpoint, then drop this filter.
 */
export function AttendanceTakerPage() {
    const { data: classes, isLoading: classesLoading } = useClasses();
    const { data: sessions, isLoading: sessionsLoading } = useAcademicSessions();
    const { data: studentsPage, isLoading: studentsLoading } = useStudents(1, 200);

    const [classId, setClassId] = useState('');
    const [academicSessionId, setAcademicSessionId] = useState('');
    const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [period, setPeriod] = useState('');

    const { data: existingRecords, isLoading: recordsLoading } = useAttendanceForClass({
        class_id: classId || undefined,
        attendance_date: attendanceDate || undefined,
        period: period || undefined,
    });

    const recordAttendance = useRecordAttendance();
    const [serverError, setServerError] = useState<string | null>(null);
    const [savedMessage, setSavedMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);
    const [statusByStudent, setStatusByStudent] = useState<Record<string, AttendanceStatus>>({});
    const [noteByStudent, setNoteByStudent] = useState<Record<string, string>>({});

    const rosterStudents = useMemo(
        () =>
            (studentsPage?.data ?? []).filter(
                (student) => student.current_enrolment?.class_id === classId,
            ),
        [studentsPage, classId],
    );

    // Pre-fill from existing records whenever the lookup resolves.
    useEffect(() => {
        if (!existingRecords) {
            return;
        }
        const nextStatus: Record<string, AttendanceStatus> = {};
        const nextNotes: Record<string, string> = {};
        existingRecords.forEach((record) => {
            nextStatus[record.student_id] = record.status;
            nextNotes[record.student_id] = record.note ?? '';
        });
        setStatusByStudent(nextStatus);
        setNoteByStudent(nextNotes);
    }, [existingRecords]);

    const handleStatusChange = (studentId: string, status: AttendanceStatus) => {
        setStatusByStudent((prev) => ({ ...prev, [studentId]: status }));
    };

    const handleNoteChange = (studentId: string, note: string) => {
        setNoteByStudent((prev) => ({ ...prev, [studentId]: note }));
    };

    const handleSave = async () => {
        setServerError(null);
        setSavedMessage(null);
        try {
            await recordAttendance.mutateAsync({
                class_id: classId,
                academic_session_id: academicSessionId,
                attendance_date: attendanceDate,
                period: period || null,
                records: rosterStudents.map((student) => ({
                    student_id: student.id,
                    status: statusByStudent[student.id] ?? 'present',
                    note: noteByStudent[student.id] || null,
                })),
            });
            setSavedMessage('Attendance saved.');
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save attendance.'));
        }
    };

    const canSave = Boolean(classId && academicSessionId && attendanceDate && rosterStudents.length > 0);

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Take Attendance</Typography>
                {classId && attendanceDate && (
                    <ExportButtons
                        endpoint="/attendance/export"
                        filenamePrefix="attendance"
                        params={{
                            class_id: classId,
                            attendance_date: attendanceDate,
                            period: period || undefined,
                        }}
                        onError={(message) => setExportError(message)}
                    />
                )}
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            <Paper sx={{ p: 3, mb: 3 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                    <SearchableSelect
                        label="Class"
                        options={toNameOptions(classes, (item) =>
                            item.level ? `Level ${item.level}` : null,
                        )}
                        value={classId}
                        onChange={setClassId}
                        loading={classesLoading}
                        disabled={classesLoading}
                    />
                    <SearchableSelect
                        label="Academic Session"
                        options={toNameOptions(sessions)}
                        value={academicSessionId}
                        onChange={setAcademicSessionId}
                        loading={sessionsLoading}
                        disabled={sessionsLoading}
                    />
                    <TextField
                        fullWidth
                        label="Date"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={attendanceDate}
                        onChange={(e) => setAttendanceDate(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        label="Period (optional)"
                        value={period}
                        onChange={(e) => setPeriod(e.target.value)}
                    />
                </Stack>
            </Paper>

            {serverError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {serverError}
                </Alert>
            )}
            {savedMessage && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    {savedMessage}
                </Alert>
            )}

            {!classId && <Alert severity="info">Choose a class to load its roster.</Alert>}

            {classId && (studentsLoading || recordsLoading) && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {classId && !studentsLoading && !recordsLoading && rosterStudents.length === 0 && (
                <Alert severity="info">No students are currently enrolled in this class.</Alert>
            )}

            {classId && !studentsLoading && !recordsLoading && rosterStudents.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Student</TableCell>
                                    <TableCell>Admission No.</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell>Note</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {rosterStudents.map((student) => (
                                    <TableRow key={student.id} hover>
                                        <TableCell>{student.full_name}</TableCell>
                                        <TableCell>{student.admission_number}</TableCell>
                                        <TableCell>
                                            <RadioGroup
                                                row
                                                value={statusByStudent[student.id] ?? 'present'}
                                                onChange={(e) =>
                                                    handleStatusChange(
                                                        student.id,
                                                        e.target.value as AttendanceStatus,
                                                    )
                                                }
                                            >
                                                {STATUS_OPTIONS.map((option) => (
                                                    <FormControlLabel
                                                        key={option.value}
                                                        value={option.value}
                                                        control={<Radio size="small" />}
                                                        label={option.label}
                                                    />
                                                ))}
                                            </RadioGroup>
                                        </TableCell>
                                        <TableCell>
                                            <TextField
                                                size="small"
                                                fullWidth
                                                placeholder="Optional note"
                                                value={noteByStudent[student.id] ?? ''}
                                                onChange={(e) =>
                                                    handleNoteChange(student.id, e.target.value)
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                    <Box sx={{ p: 2, display: 'flex', justifyContent: 'flex-end' }}>
                        <Button
                            variant="contained"
                            disabled={!canSave || recordAttendance.isPending}
                            onClick={handleSave}
                        >
                            {recordAttendance.isPending ? 'Saving…' : 'Save Attendance'}
                        </Button>
                    </Box>
                </Paper>
            )}
        </Box>
    );
}
