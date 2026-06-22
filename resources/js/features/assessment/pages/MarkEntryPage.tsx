import { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Chip,
    CircularProgress,
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
import { useAssessments } from '../api/useAssessments';
import { useResults, useSaveResult } from '../api/useResults';
import { useStudents } from '../../students/api/useStudents';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { ResultRecord } from '../types/assessment';

/**
 * TODO: same roster gap as AttendanceTakerPage — there is no
 * "students taking this subject/assessment" endpoint. An Assessment is
 * scoped to (subject, academic_session), and Subject↔ClassRoom is
 * many-to-many, so we can't even derive a single class to filter students
 * by. For now this loads every student in the school (paginated, generous
 * per_page) and lets the teacher pick which rows to fill in — ask
 * api-builder for a roster endpoint (e.g. students enrolled in any class
 * that teaches this subject + session) to replace this.
 *
 * Saves are one POST /api/v1/results per edited row (on blur), since there
 * is no batch endpoint — simplest option per the task brief.
 */
export function MarkEntryPage() {
    const { data: assessmentsPage, isLoading: assessmentsLoading } = useAssessments();
    const { data: studentsPage, isLoading: studentsLoading } = useStudents(1, 200);

    const [assessmentId, setAssessmentId] = useState('');

    const selectedAssessment = useMemo(
        () => assessmentsPage?.data.find((assessment) => assessment.id === assessmentId) ?? null,
        [assessmentsPage, assessmentId],
    );

    const { data: resultsPage, isLoading: resultsLoading } = useResults({
        assessment_id: assessmentId || undefined,
    });

    const saveResult = useSaveResult();
    const [scoreByStudent, setScoreByStudent] = useState<Record<string, string>>({});
    const [gradeByStudent, setGradeByStudent] = useState<Record<string, string>>({});
    const [savedStudentIds, setSavedStudentIds] = useState<Set<string>>(new Set());
    const [errorByStudent, setErrorByStudent] = useState<Record<string, string>>({});

    const resultByStudent = useMemo(() => {
        const map = new Map<string, ResultRecord>();
        (resultsPage?.data ?? []).forEach((result) => map.set(result.student_id, result));
        return map;
    }, [resultsPage]);

    useEffect(() => {
        const nextScores: Record<string, string> = {};
        const nextGrades: Record<string, string> = {};
        (resultsPage?.data ?? []).forEach((result) => {
            nextScores[result.student_id] = result.score === null ? '' : String(result.score);
            nextGrades[result.student_id] = result.grade ?? '';
        });
        setScoreByStudent(nextScores);
        setGradeByStudent(nextGrades);
        setSavedStudentIds(new Set());
    }, [resultsPage]);

    const handleSaveRow = async (studentId: string) => {
        if (!assessmentId) {
            return;
        }
        setErrorByStudent((prev) => ({ ...prev, [studentId]: '' }));
        const rawScore = scoreByStudent[studentId] ?? '';
        try {
            await saveResult.mutateAsync({
                student_id: studentId,
                assessment_id: assessmentId,
                score: rawScore === '' ? null : Number(rawScore),
                grade: gradeByStudent[studentId] || null,
            });
            setSavedStudentIds((prev) => new Set(prev).add(studentId));
        } catch (error) {
            setErrorByStudent((prev) => ({
                ...prev,
                [studentId]: getErrorMessage(error, 'Unable to save this mark.'),
            }));
        }
    };

    return (
        <Box>
            <Typography variant="h5" gutterBottom>
                Mark Entry
            </Typography>

            <Paper sx={{ p: 3, mb: 3 }}>
                <TextField
                    select
                    fullWidth
                    label="Assessment"
                    value={assessmentId}
                    onChange={(e) => setAssessmentId(e.target.value)}
                    disabled={assessmentsLoading}
                >
                    {(assessmentsPage?.data ?? []).map((assessment) => (
                        <MenuItem key={assessment.id} value={assessment.id}>
                            {assessment.name} — {assessment.subject_name ?? 'Unknown subject'} (
                            {assessment.academic_session_name ?? 'Unknown session'})
                        </MenuItem>
                    ))}
                </TextField>
            </Paper>

            {!assessmentId && <Alert severity="info">Choose an assessment to enter marks for it.</Alert>}

            {assessmentId && (studentsLoading || resultsLoading) && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {assessmentId && !studentsLoading && !resultsLoading && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Student</TableCell>
                                    <TableCell>Score (max {selectedAssessment?.max_score ?? '—'})</TableCell>
                                    <TableCell>Grade</TableCell>
                                    <TableCell>Status</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {(studentsPage?.data ?? []).map((student) => {
                                    const existingResult = resultByStudent.get(student.id);
                                    const isPublished = existingResult?.is_published ?? false;

                                    return (
                                        <TableRow key={student.id} hover>
                                            <TableCell>{student.full_name}</TableCell>
                                            <TableCell>
                                                <TextField
                                                    size="small"
                                                    type="number"
                                                    disabled={isPublished}
                                                    value={scoreByStudent[student.id] ?? ''}
                                                    onChange={(e) =>
                                                        setScoreByStudent((prev) => ({
                                                            ...prev,
                                                            [student.id]: e.target.value,
                                                        }))
                                                    }
                                                    onBlur={() => handleSaveRow(student.id)}
                                                    error={Boolean(errorByStudent[student.id])}
                                                    helperText={errorByStudent[student.id]}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <TextField
                                                    size="small"
                                                    disabled={isPublished}
                                                    value={gradeByStudent[student.id] ?? ''}
                                                    onChange={(e) =>
                                                        setGradeByStudent((prev) => ({
                                                            ...prev,
                                                            [student.id]: e.target.value,
                                                        }))
                                                    }
                                                    onBlur={() => handleSaveRow(student.id)}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {isPublished ? (
                                                    <Chip label="Published" size="small" color="success" />
                                                ) : savedStudentIds.has(student.id) ? (
                                                    <Chip label="Saved" size="small" color="default" />
                                                ) : (
                                                    <Chip label="Draft" size="small" variant="outlined" />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}
        </Box>
    );
}
