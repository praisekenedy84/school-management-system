import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import {
    Alert,
    Box,
    Button,
    MenuItem,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useAdmitStudent } from '../api/useStudents';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { AdmitStudentRequest } from '../types/student';

/**
 * TODO: class_id and academic_session_id are free-text UUID inputs because
 * there is no GET /api/v1/classes or GET /api/v1/academic-sessions endpoint
 * yet — building those list endpoints was out of scope for this backend
 * pass. Swap these for <Select> dropdowns once those endpoints exist
 * (api-builder, Recipe B).
 */
export function StudentAdmissionPage() {
    const navigate = useNavigate();
    const admitStudent = useAdmitStudent();
    const [serverError, setServerError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        control,
        formState: { errors },
    } = useForm<AdmitStudentRequest>({
        defaultValues: {
            admission_number: '',
            first_name: '',
            last_name: '',
            date_of_birth: '',
            gender: '',
            class_id: '',
            academic_session_id: '',
            residence_type: 'day',
            enrolled_at: '',
        },
    });

    const onSubmit = async (values: AdmitStudentRequest) => {
        setServerError(null);
        try {
            const student = await admitStudent.mutateAsync({
                ...values,
                date_of_birth: values.date_of_birth || null,
                gender: values.gender || null,
                enrolled_at: values.enrolled_at || null,
            });
            navigate(`/students/${student.id}`, { replace: true });
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to admit student. Check the form and try again.'));
        }
    };

    return (
        <Box maxWidth={640}>
            <Typography variant="h5" gutterBottom>
                Admit Student
            </Typography>

            <Paper sx={{ p: 3 }}>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
                    <Stack spacing={2}>
                        <TextField
                            fullWidth
                            label="Admission Number"
                            error={Boolean(errors.admission_number)}
                            helperText={errors.admission_number?.message}
                            {...register('admission_number', { required: 'Admission number is required' })}
                        />
                        <Stack direction="row" spacing={2}>
                            <TextField
                                fullWidth
                                label="First Name"
                                error={Boolean(errors.first_name)}
                                helperText={errors.first_name?.message}
                                {...register('first_name', { required: 'First name is required' })}
                            />
                            <TextField
                                fullWidth
                                label="Last Name"
                                error={Boolean(errors.last_name)}
                                helperText={errors.last_name?.message}
                                {...register('last_name', { required: 'Last name is required' })}
                            />
                        </Stack>
                        <Stack direction="row" spacing={2}>
                            <TextField
                                fullWidth
                                label="Date of Birth"
                                type="date"
                                InputLabelProps={{ shrink: true }}
                                {...register('date_of_birth')}
                            />
                            <TextField fullWidth label="Gender" {...register('gender')} />
                        </Stack>

                        {/* TODO: replace with a <Select> once GET /api/v1/classes exists. */}
                        <TextField
                            fullWidth
                            label="Class ID (UUID)"
                            helperText={errors.class_id?.message ?? 'TODO: replace with a class picker once the endpoint exists'}
                            error={Boolean(errors.class_id)}
                            {...register('class_id', { required: 'Class is required' })}
                        />
                        {/* TODO: replace with a <Select> once GET /api/v1/academic-sessions exists. */}
                        <TextField
                            fullWidth
                            label="Academic Session ID (UUID)"
                            helperText={
                                errors.academic_session_id?.message ??
                                'TODO: replace with a session picker once the endpoint exists'
                            }
                            error={Boolean(errors.academic_session_id)}
                            {...register('academic_session_id', { required: 'Academic session is required' })}
                        />

                        <Controller
                            name="residence_type"
                            control={control}
                            rules={{ required: true }}
                            render={({ field }) => (
                                <TextField select fullWidth label="Residence Type" {...field}>
                                    <MenuItem value="day">Day</MenuItem>
                                    <MenuItem value="boarding">Boarding</MenuItem>
                                </TextField>
                            )}
                        />

                        <TextField
                            fullWidth
                            label="Enrolled At"
                            type="date"
                            InputLabelProps={{ shrink: true }}
                            {...register('enrolled_at')}
                        />

                        <Stack direction="row" spacing={2} justifyContent="flex-end">
                            <Button onClick={() => navigate('/students')}>Cancel</Button>
                            <Button type="submit" variant="contained" disabled={admitStudent.isPending}>
                                {admitStudent.isPending ? 'Admitting…' : 'Admit Student'}
                            </Button>
                        </Stack>
                    </Stack>
                </Box>
            </Paper>
        </Box>
    );
}
