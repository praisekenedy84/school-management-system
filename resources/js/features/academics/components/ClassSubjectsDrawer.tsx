import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Drawer,
    IconButton,
    MenuItem,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { Plus, X } from 'lucide-react';
import { useSubjects } from '../api/useSubjects';
import { useAttachClassSubject, useClassSubjects, useDetachClassSubject } from '../api/useClassSubjects';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { ClassRoom } from '../types/academic';

/** Attach/detach subjects for a single class. */
export function ClassSubjectsDrawer({
    open,
    classRoom,
    onClose,
}: {
    open: boolean;
    classRoom: ClassRoom | null;
    onClose: () => void;
}) {
    const { data: allSubjects } = useSubjects();
    const { data: attachedSubjects, isLoading } = useClassSubjects(classRoom?.id ?? '');
    const [selectedSubjectId, setSelectedSubjectId] = useState('');
    const [serverError, setServerError] = useState<string | null>(null);

    const attachSubject = useAttachClassSubject(classRoom?.id ?? '');
    const detachSubject = useDetachClassSubject(classRoom?.id ?? '');

    const attachedIds = new Set((attachedSubjects ?? []).map((s) => s.id));
    const availableSubjects = (allSubjects?.data ?? []).filter((s) => !attachedIds.has(s.id));

    const handleAttach = async () => {
        if (!selectedSubjectId) return;
        setServerError(null);
        try {
            await attachSubject.mutateAsync(selectedSubjectId);
            setSelectedSubjectId('');
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to attach subject.'));
        }
    };

    const handleDetach = (subjectId: string) => {
        setServerError(null);
        detachSubject.mutate(subjectId, {
            onError: (error) => setServerError(getErrorMessage(error, 'Unable to detach subject.')),
        });
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose}>
            <Box sx={{ width: 380, p: 3 }}>
                <Typography variant="h6" gutterBottom>
                    Subjects — {classRoom?.name}
                </Typography>

                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                {isLoading && (
                    <Box display="flex" justifyContent="center" py={2}>
                        <CircularProgress size={24} />
                    </Box>
                )}

                <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" mb={3}>
                    {!isLoading && (attachedSubjects ?? []).length === 0 && (
                        <Typography variant="body2" color="text.secondary">
                            No subjects attached.
                        </Typography>
                    )}
                    {(attachedSubjects ?? []).map((subject) => (
                        <Chip
                            key={subject.id}
                            label={subject.name}
                            onDelete={() => handleDetach(subject.id)}
                            deleteIcon={<X size={14} />}
                        />
                    ))}
                </Stack>

                <Stack direction="row" spacing={1}>
                    <TextField
                        select
                        fullWidth
                        size="small"
                        label="Attach subject"
                        value={selectedSubjectId}
                        onChange={(e) => setSelectedSubjectId(e.target.value)}
                    >
                        {availableSubjects.map((subject) => (
                            <MenuItem key={subject.id} value={subject.id}>
                                {subject.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <IconButton
                        color="primary"
                        disabled={!selectedSubjectId || attachSubject.isPending}
                        onClick={handleAttach}
                    >
                        <Plus size={18} />
                    </IconButton>
                </Stack>

                <Box mt={3} textAlign="right">
                    <Button onClick={onClose}>Close</Button>
                </Box>
            </Box>
        </Drawer>
    );
}
