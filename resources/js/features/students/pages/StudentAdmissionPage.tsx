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
import { SearchableSelect } from '../../../components/SearchableSelect';
import { useClasses } from '../../academics/api/useClasses';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useClassStreams } from '../../academics/api/useStreams';
import { toNameOptions } from '../../../lib/selectOptions';
import { useAdmitStudent } from '../api/useStudents';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { AdmitStudentRequest } from '../types/student';

export function StudentAdmissionPage() {
    const navigate = useNavigate();
    const admitStudent = useAdmitStudent();
    const { data: classes, isLoading: classesLoading } = useClasses();
    const { data: sessions, isLoading: sessionsLoading } = useAcademicSessions();
    const [serverError, setServerError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        control,
        watch,
        setValue,
        formState: { errors },
    } = useForm<AdmitStudentRequest>({
        defaultValues: {
            admission_number: '',
            first_name: '',
            last_name: '',
            date_of_birth: '',
            gender: '',
            class_id: '',
            stream_id: '',
            academic_session_id: '',
            residence_type: 'day',
            enrolled_at: '',
        },
    });

    const selectedClassId = watch('class_id');
    const { data: streams, isLoading: streamsLoading } = useClassStreams(selectedClassId);

    const activeStreams = (streams ?? []).filter((s) => s.is_active);

    const onSubmit = async (values: AdmitStudentRequest) => {
        setServerError(null);
        try {
            const student = await admitStudent.mutateAsync({
                ...values,
                date_of_birth: values.date_of_birth || null,
                gender: values.gender || null,
                stream_id: values.stream_id || null,
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

                        <Controller
                            name="class_id"
                            control={control}
                            rules={{ required: 'Class is required' }}
                            render={({ field }) => (
                                <SearchableSelect
                                    label="Class"
                                    options={toNameOptions(classes, (item) =>
                                        item.level ? `Level ${item.level}` : null,
                                    )}
                                    value={field.value}
                                    onChange={(value) => {
                                        field.onChange(value);
                                        setValue('stream_id', '');
                                    }}
                                    loading={classesLoading}
                                    required
                                    error={Boolean(errors.class_id)}
                                    helperText={errors.class_id?.message}
                                />
                            )}
                        />
                        {selectedClassId && activeStreams.length > 0 && (
                            <Controller
                                name="stream_id"
                                control={control}
                                render={({ field }) => (
                                    <SearchableSelect
                                        label="Stream (optional)"
                                        options={activeStreams.map((s) => ({
                                            id: s.id,
                                            label: s.name,
                                            secondary: s.capacity ? `Capacity ${s.capacity}` : null,
                                        }))}
                                        value={field.value ?? ''}
                                        onChange={field.onChange}
                                        loading={streamsLoading}
                                    />
                                )}
                            />
                        )}
                        <Controller
                            name="academic_session_id"
                            control={control}
                            rules={{ required: 'Academic session is required' }}
                            render={({ field }) => (
                                <SearchableSelect
                                    label="Academic Session"
                                    options={toNameOptions(sessions)}
                                    value={field.value}
                                    onChange={field.onChange}
                                    loading={sessionsLoading}
                                    required
                                    error={Boolean(errors.academic_session_id)}
                                    helperText={errors.academic_session_id?.message}
                                />
                            )}
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
