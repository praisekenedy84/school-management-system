import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Pagination,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Typography,
} from '@mui/material';
import { Plus } from 'lucide-react';
import { useStudents, STUDENTS_QUERY_KEY } from '../api/useStudents';
import { useSchools } from '../../academics/api/useSchools';
import { usePermissions } from '../../../lib/usePermissions';
import { ExportButtons } from '../../../components/ExportButtons';
import { ImportDialog } from '../../../components/ImportDialog';

/**
 * Students list with a simple MUI Table (no DataGrid dependency) and
 * server-driven pagination via the `page` query param.
 */
export function StudentsListPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { user, canAction } = usePermissions();
    const [page, setPage] = useState(1);
    const { data, isLoading, isError } = useStudents(page);
    const { data: schools } = useSchools();
    const canAdmit = canAction('admitStudents');
    const needsSchoolPicker = user?.school_id === null;

    const [importOpen, setImportOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Students</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/students/export"
                        filenamePrefix="students"
                        onError={(message) => setExportError(message)}
                    />
                    {canAdmit && (
                        <>
                            <Button variant="outlined" onClick={() => setImportOpen(true)}>
                                Import
                            </Button>
                            <Button
                                variant="contained"
                                startIcon={<Plus size={18} />}
                                onClick={() => navigate('/students/new')}
                            >
                                New Student
                            </Button>
                        </>
                    )}
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load students. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No students have been admitted yet.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Admission No.</TableCell>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Class</TableCell>
                                    <TableCell>Residence</TableCell>
                                    <TableCell>Status</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((student) => (
                                    <TableRow
                                        key={student.id}
                                        hover
                                        sx={{ cursor: 'pointer' }}
                                        onClick={() => navigate(`/students/${student.id}`)}
                                    >
                                        <TableCell>{student.admission_number}</TableCell>
                                        <TableCell>{student.full_name}</TableCell>
                                        <TableCell>
                                            {student.current_enrolment?.class_name ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                label={student.residence_type}
                                                size="small"
                                                color={student.residence_type === 'boarding' ? 'secondary' : 'default'}
                                            />
                                        </TableCell>
                                        <TableCell>{student.status}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                    {data.meta.last_page > 1 && (
                        <Box display="flex" justifyContent="center" p={2}>
                            <Pagination
                                count={data.meta.last_page}
                                page={data.meta.current_page}
                                onChange={(_, value) => setPage(value)}
                            />
                        </Box>
                    )}
                </Paper>
            )}

            <ImportDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
                templateEndpoint="/students/import-template"
                importEndpoint="/students/import"
                resourceLabel="Students"
                showSchoolPicker={needsSchoolPicker}
                schools={schools ?? []}
                onImported={() => queryClient.invalidateQueries({ queryKey: STUDENTS_QUERY_KEY })}
            />
        </Box>
    );
}
