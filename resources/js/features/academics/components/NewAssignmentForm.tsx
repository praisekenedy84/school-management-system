import { useMemo, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { Alert, Box, Button, Stack, TextField, Typography } from '@mui/material';
import { SearchableSelect } from '../../../components/SearchableSelect';
import { useAuth } from '../../../app/AuthProvider';
import { useCreateAssignment } from '../api/useAssignments';
import { useTeacherAssignments } from '../api/useTeacherAssignments';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { CreateAssignmentRequest } from '../types/academic';

export function NewAssignmentForm({ onCreated }: { onCreated: () => void }) {
    const { user } = useAuth();
    const createAssignment = useCreateAssignment();
    const { data: teacherAssignments, isLoading: assignmentsLoading } = useTeacherAssignments(
        user ? { teacher_id: user.id } : {},
    );
    const [serverError, setServerError] = useState<string | null>(null);

    const assignmentOptions = useMemo(
        () =>
            (teacherAssignments?.data ?? []).map((assignment) => ({
                id: assignment.id,
                label: [assignment.class_name, assignment.subject_name, assignment.academic_session_name]
                    .filter(Boolean)
                    .join(' · '),
            })),
        [teacherAssignments],
    );

    const {
        control,
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
                <Controller
                    name="teacher_assignment_id"
                    control={control}
                    rules={{ required: 'Teaching assignment is required' }}
                    render={({ field }) => (
                        <SearchableSelect
                            label="Class & Subject"
                            options={assignmentOptions}
                            value={field.value}
                            onChange={field.onChange}
                            loading={assignmentsLoading}
                            required
                            error={Boolean(errors.teacher_assignment_id)}
                            helperText={
                                errors.teacher_assignment_id?.message ??
                                (assignmentOptions.length === 0 && !assignmentsLoading
                                    ? 'No teaching assignments on your account yet.'
                                    : undefined)
                            }
                        />
                    )}
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
