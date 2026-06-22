import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, Box, Button, Stack, TextField, Typography } from '@mui/material';
import { useCreateAssignment } from '../api/useAssignments';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { CreateAssignmentRequest } from '../types/academic';

/**
 * TODO: teacher_assignment_id is a free-text UUID input because there is no
 * "my teaching assignments" picker UI yet — GET /api/v1/teacher-assignments
 * exists but this form doesn't cross-wire to it to keep scope tight. Wire a
 * <Select> populated from useTeacherAssignments({ teacher_id: user.id }) next.
 */
export function NewAssignmentForm({ onCreated }: { onCreated: () => void }) {
    const createAssignment = useCreateAssignment();
    const [serverError, setServerError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<CreateAssignmentRequest>({
        defaultValues: { teacher_assignment_id: '', title: '', description: '', due_at: '' },
    });

    const onSubmit = async (values: CreateAssignmentRequest) => {
        setServerError(null);
        try {
            await createAssignment.mutateAsync({
                ...values,
                description: values.description || null,
                due_at: values.due_at || null,
            });
            reset();
            onCreated();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to create assignment.'));
        }
    };

    return (
        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate sx={{ p: 3 }}>
            <Typography variant="subtitle1" gutterBottom>
                New Assignment
            </Typography>

            {serverError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {serverError}
                </Alert>
            )}

            <Stack spacing={2}>
                <TextField
                    fullWidth
                    label="Teacher Assignment ID (UUID)"
                    error={Boolean(errors.teacher_assignment_id)}
                    helperText={errors.teacher_assignment_id?.message}
                    {...register('teacher_assignment_id', { required: 'Teacher assignment is required' })}
                />
                <TextField
                    fullWidth
                    label="Title"
                    error={Boolean(errors.title)}
                    helperText={errors.title?.message}
                    {...register('title', { required: 'Title is required' })}
                />
                <TextField
                    fullWidth
                    multiline
                    minRows={3}
                    label="Description"
                    {...register('description')}
                />
                <TextField
                    fullWidth
                    label="Due At"
                    type="datetime-local"
                    InputLabelProps={{ shrink: true }}
                    {...register('due_at')}
                />
                <Box>
                    <Button type="submit" variant="contained" disabled={createAssignment.isPending}>
                        {createAssignment.isPending ? 'Creating…' : 'Create Assignment'}
                    </Button>
                </Box>
            </Stack>
        </Box>
    );
}
