import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    CircularProgress,
    Grid,
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
import { useClasses } from '../../academics/api/useClasses';
import { useDebouncedValue } from '../../../lib/useDebouncedValue';
import { useAttendanceReport } from '../api/useAttendanceReport';
import { ExportButtons } from '../../../components/ExportButtons';

function SummaryCard({ label, value }: { label: string; value: string | number }) {
    return (
        <Card variant="outlined">
            <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
                <Typography variant="caption" color="text.secondary">
                    {label}
                </Typography>
                <Typography variant="h6">{value}</Typography>
            </CardContent>
        </Card>
    );
}

/** Attendance report with summary statistics and searchable records. */
export function AttendanceReportsPage() {
    const { data: classes } = useClasses();
    const [classId, setClassId] = useState('');
    const [attendanceDate, setAttendanceDate] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const debouncedSearch = useDebouncedValue(search, 300);

    const { data, isLoading, isError } = useAttendanceReport({
        class_id: classId || undefined,
        attendance_date: attendanceDate || undefined,
        date_from: !attendanceDate && dateFrom ? dateFrom : undefined,
        date_to: !attendanceDate && dateTo ? dateTo : undefined,
        search: debouncedSearch || undefined,
        page,
    });

    const summary = data?.summary;
    const exportParams = {
        class_id: classId || undefined,
        attendance_date: attendanceDate || undefined,
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Attendance Reports</Typography>
                <ExportButtons endpoint="/attendance/export" filenamePrefix="attendance" params={exportParams} />
            </Stack>

            <Paper sx={{ p: 2, mb: 3 }}>
                <Grid container spacing={2}>
                    <Grid item xs={12} sm={6} md={3}>
                        <TextField
                            select
                            fullWidth
                            size="small"
                            label="Class"
                            value={classId}
                            onChange={(e) => {
                                setClassId(e.target.value);
                                setPage(1);
                            }}
                        >
                            <MenuItem value="">All classes</MenuItem>
                            {(classes ?? []).map((c) => (
                                <MenuItem key={c.id} value={c.id}>
                                    {c.name}
                                </MenuItem>
                            ))}
                        </TextField>
                    </Grid>
                    <Grid item xs={12} sm={6} md={3}>
                        <TextField
                            fullWidth
                            size="small"
                            label="Date"
                            type="date"
                            InputLabelProps={{ shrink: true }}
                            value={attendanceDate}
                            onChange={(e) => {
                                setAttendanceDate(e.target.value);
                                setPage(1);
                            }}
                        />
                    </Grid>
                    <Grid item xs={12} sm={6} md={3}>
                        <TextField
                            fullWidth
                            size="small"
                            label="From"
                            type="date"
                            InputLabelProps={{ shrink: true }}
                            value={dateFrom}
                            disabled={Boolean(attendanceDate)}
                            onChange={(e) => {
                                setDateFrom(e.target.value);
                                setPage(1);
                            }}
                        />
                    </Grid>
                    <Grid item xs={12} sm={6} md={3}>
                        <TextField
                            fullWidth
                            size="small"
                            label="To"
                            type="date"
                            InputLabelProps={{ shrink: true }}
                            value={dateTo}
                            disabled={Boolean(attendanceDate)}
                            onChange={(e) => {
                                setDateTo(e.target.value);
                                setPage(1);
                            }}
                        />
                    </Grid>
                    <Grid item xs={12}>
                        <TextField
                            fullWidth
                            size="small"
                            label="Search student (name or admission number)"
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                setPage(1);
                            }}
                        />
                    </Grid>
                </Grid>
            </Paper>

            {summary && (
                <Grid container spacing={2} mb={3}>
                    <Grid item xs={6} sm={4} md={2}>
                        <SummaryCard label="Students" value={summary.total_students} />
                    </Grid>
                    <Grid item xs={6} sm={4} md={2}>
                        <SummaryCard label="Present" value={summary.present} />
                    </Grid>
                    <Grid item xs={6} sm={4} md={2}>
                        <SummaryCard label="Absent" value={summary.absent} />
                    </Grid>
                    <Grid item xs={6} sm={4} md={2}>
                        <SummaryCard label="Late" value={summary.late} />
                    </Grid>
                    <Grid item xs={6} sm={4} md={2}>
                        <SummaryCard label="Excused" value={summary.excused} />
                    </Grid>
                    <Grid item xs={6} sm={4} md={2}>
                        <SummaryCard label="Attendance %" value={`${summary.attendance_percentage}%`} />
                    </Grid>
                </Grid>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load attendance report.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No attendance records match your filters.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Student</TableCell>
                                    <TableCell>Date</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell>Note</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell>{record.student_name ?? record.student_id}</TableCell>
                                        <TableCell>{record.attendance_date}</TableCell>
                                        <TableCell>
                                            <Chip label={record.status} size="small" />
                                        </TableCell>
                                        <TableCell>{record.note ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                    {data.meta.last_page > 1 && (
                        <Stack direction="row" justifyContent="center" spacing={2} p={2}>
                            <Button size="small" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                Previous
                            </Button>
                            <Typography variant="body2" alignSelf="center">
                                Page {data.meta.current_page} of {data.meta.last_page}
                            </Typography>
                            <Button
                                size="small"
                                disabled={page >= data.meta.last_page}
                                onClick={() => setPage((p) => p + 1)}
                            >
                                Next
                            </Button>
                        </Stack>
                    )}
                </Paper>
            )}
        </Box>
    );
}
