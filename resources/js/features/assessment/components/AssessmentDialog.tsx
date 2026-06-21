import { useState } from 'react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    Stack,
    TextField,
} from '@mui/material';
import { useSubjects } from '../../academics/api/useSubjects';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import type { AssessmentRequest } from '../types/assessment';

/** Dumb create/edit dialog for an Assessment definition, mirroring SubjectDialog. */
export function AssessmentDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: AssessmentRequest;
    onClose: () => void;
    onSubmit: (values: AssessmentRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const { data: subjects } = useSubjects();
    const { data: sessions } = useAcademicSessions();

    const [subjectId, setSubjectId] = useState(initialValue.subject_id);
    const [academicSessionId, setAcademicSessionId] = useState(initialValue.academic_session_id);
    const [name, setName] = useState(initialValue.name);
    const [weight, setWeight] = useState(initialValue.weight);
    const [maxScore, setMaxScore] = useState(initialValue.max_score);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setSubjectId(initialValue.subject_id);
                    setAcademicSessionId(initialValue.academic_session_id);
                    setName(initialValue.name);
                    setWeight(initialValue.weight);
                    setMaxScore(initialValue.max_score);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Assessment' : 'New Assessment'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
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
                    <TextField
                        fullWidth
                        label="Name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        autoFocus
                    />
                    <TextField
                        fullWidth
                        label="Weight (0-100)"
                        type="number"
                        value={weight}
                        onChange={(e) => setWeight(Number(e.target.value))}
                    />
                    <TextField
                        fullWidth
                        label="Max Score"
                        type="number"
                        value={maxScore}
                        onChange={(e) => setMaxScore(Number(e.target.value))}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!subjectId || !academicSessionId || !name || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            subject_id: subjectId,
                            academic_session_id: academicSessionId,
                            name,
                            weight,
                            max_score: maxScore,
                        })
                    }
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
