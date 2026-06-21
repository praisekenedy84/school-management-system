import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { Alert, Box, Button, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { usePromoteEnrolment } from '../api/useEnrolments';
import type { Enrolment, PromoteEnrolmentRequest } from '../types/student';

/**
 * TODO: class_id / academic_session_id are free-text UUID inputs — same
 * reason as StudentAdmissionPage (no GET /api/v1/classes or
 * /api/v1/academic-sessions endpoint yet).
 */
export function PromoteEnrolmentForm({
    studentId,
    currentEnrolment,
    onPromoted,
}: {
    studentId: string;
    currentEnrolment: Enrolment;
    onPromoted: () => void;
}) {
    const promote = usePromoteEnrolment(studentId);
    const [serverError, setServerError] = useState<string | null>(null);

    const { register, handleSubmit, control } = useForm<PromoteEnrolmentRequest>({
        defaultValues: {
            class_id: '',
            academic_session_id: '',
            residence_type: currentEnrolment.residence_type,
            enrolled_at: '',
        },
    });

    const onSubmit = async (values: PromoteEnrolmentRequest) => {
        setServerError(null);
        try {
            await promote.mutateAsync({
                enrolmentId: currentEnrolment.id,
                payload: {
                    ...values,
                    enrolled_at: values.enrolled_at || null,
                },
            });
            onPromoted();
        } catch (error: any) {
            setServerError(error?.response?.data?.message ?? 'Unable to promote student.');
        }
    };

    return (
        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
            <Typography variant="subtitle1" gutterBottom>
                Promote / Transfer
            </Typography>

            {serverError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {serverError}
                </Alert>
            )}

            <Stack spacing={2}>
                <TextField
                    size="small"
                    fullWidth
                    label="New Class ID (UUID)"
                    {...register('class_id', { required: true })}
                />
                <TextField
                    size="small"
                    fullWidth
                    label="New Academic Session ID (UUID)"
                    {...register('academic_session_id', { required: true })}
                />
                <Controller
                    name="residence_type"
                    control={control}
                    render={({ field }) => (
                        <TextField select size="small" fullWidth label="Residence Type" {...field}>
                            <MenuItem value="day">Day</MenuItem>
                            <MenuItem value="boarding">Boarding</MenuItem>
                        </TextField>
                    )}
                />
                <TextField
                    size="small"
                    fullWidth
                    label="Enrolled At"
                    type="date"
                    InputLabelProps={{ shrink: true }}
                    {...register('enrolled_at')}
                />
                <Box>
                    <Button type="submit" variant="contained" disabled={promote.isPending}>
                        {promote.isPending ? 'Promoting…' : 'Promote'}
                    </Button>
                </Box>
            </Stack>
        </Box>
    );
}
