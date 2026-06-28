import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { Alert, Box, Button, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { SearchableSelect } from '../../../components/SearchableSelect';
import { useClasses } from '../../academics/api/useClasses';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { toNameOptions } from '../../../lib/selectOptions';
import { usePromoteEnrolment } from '../api/useEnrolments';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { Enrolment, PromoteEnrolmentRequest } from '../types/student';

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
    const { data: classes, isLoading: classesLoading } = useClasses();
    const { data: sessions, isLoading: sessionsLoading } = useAcademicSessions();
    const [serverError, setServerError] = useState<string | null>(null);

    const { control, handleSubmit, register } = useForm<PromoteEnrolmentRequest>({
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
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to promote student.'));
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
                <Controller
                    name="class_id"
                    control={control}
                    rules={{ required: 'Class is required' }}
                    render={({ field, fieldState }) => (
                        <SearchableSelect
                            label="New Class"
                            size="small"
                            options={toNameOptions(classes, (item) =>
                                item.level ? `Level ${item.level}` : null,
                            )}
                            value={field.value}
                            onChange={field.onChange}
                            loading={classesLoading}
                            required
                            error={Boolean(fieldState.error)}
                            helperText={fieldState.error?.message}
                        />
                    )}
                />
                <Controller
                    name="academic_session_id"
                    control={control}
                    rules={{ required: 'Academic session is required' }}
                    render={({ field, fieldState }) => (
                        <SearchableSelect
                            label="New Academic Session"
                            size="small"
                            options={toNameOptions(sessions)}
                            value={field.value}
                            onChange={field.onChange}
                            loading={sessionsLoading}
                            required
                            error={Boolean(fieldState.error)}
                            helperText={fieldState.error?.message}
                        />
                    )}
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
