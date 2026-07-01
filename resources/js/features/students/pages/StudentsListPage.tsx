import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { Plus, Search, X } from 'lucide-react';
import { useStudents, STUDENTS_QUERY_KEY, type StudentFilters } from '../api/useStudents';
import { useSchools } from '../../academics/api/useSchools';
import { useClasses } from '../../academics/api/useClasses';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useClassStreams } from '../../academics/api/useStreams';
import { usePermissions } from '@/lib/usePermissions';
import { useDebouncedValue } from '@/lib/useDebouncedValue';
import { ExportButtons } from '@/components/ExportButtons';
import { ImportDialog } from '@/components/ImportDialog';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

const EMPTY_FILTERS: StudentFilters = {
    search: '',
    class_id: '',
    stream_id: '',
    gender: '',
    residence_type: '',
    academic_session_id: '',
    status: '',
};

/** Students list with server-side search and combined filters. */
export function StudentsListPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { user, canAction } = usePermissions();
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<StudentFilters>(EMPTY_FILTERS);
    const debouncedSearch = useDebouncedValue(filters.search ?? '', 300);

    const activeFilters: StudentFilters = { ...filters, search: debouncedSearch };
    const { data, isLoading, isError } = useStudents(page, activeFilters);
    const { data: schools } = useSchools();
    const { data: classes } = useClasses();
    const { data: sessions } = useAcademicSessions();
    const { data: streams } = useClassStreams(filters.class_id ?? '');
    const canAdmit = canAction('admitStudents');
    const needsSchoolPicker = user?.school_id === null;

    const [importOpen, setImportOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const hasActiveFilters = Object.entries(activeFilters).some(
        ([, value]) => value !== '' && value != null,
    );

    useEffect(() => {
        setPage(1);
    }, [debouncedSearch, filters.class_id, filters.stream_id, filters.gender, filters.residence_type, filters.academic_session_id, filters.status]);

    const updateFilter = (key: keyof StudentFilters, value: string) => {
        setFilters((prev) => {
            const next = { ...prev, [key]: value };
            if (key === 'class_id') {
                next.stream_id = '';
            }
            return next;
        });
    };

    const clearFilters = () => setFilters(EMPTY_FILTERS);

    const exportParams = Object.fromEntries(
        Object.entries(activeFilters).filter(([, value]) => value !== '' && value != null),
    );

    return (
        <div className="space-y-6">
            <PageHeader
                title="Students"
                actions={
                    <>
                        <ExportButtons
                            endpoint="/students/export"
                            filenamePrefix="students"
                            params={exportParams}
                            onError={(message) => setExportError(message)}
                        />
                        {canAdmit && (
                            <>
                                <Button variant="outline" onClick={() => setImportOpen(true)}>
                                    Import
                                </Button>
                                <Button onClick={() => navigate('/students/new')}>
                                    <Plus className="h-4 w-4" />
                                    Add Student
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            {exportError && (
                <Alert variant="destructive">
                    <AlertDescription>{exportError}</AlertDescription>
                </Alert>
            )}

            <Card className="p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="relative sm:col-span-2 lg:col-span-4">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by name or admission number"
                            value={filters.search ?? ''}
                            onChange={(e) => updateFilter('search', e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="filter-class">Class</Label>
                        <select
                            id="filter-class"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            value={filters.class_id ?? ''}
                            onChange={(e) => updateFilter('class_id', e.target.value)}
                        >
                            <option value="">All classes</option>
                            {(classes ?? []).map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="filter-stream">Stream</Label>
                        <select
                            id="filter-stream"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm disabled:opacity-50"
                            value={filters.stream_id ?? ''}
                            onChange={(e) => updateFilter('stream_id', e.target.value)}
                            disabled={!filters.class_id}
                        >
                            <option value="">All streams</option>
                            {(streams ?? [])
                                .filter((s) => s.is_active)
                                .map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="filter-session">Academic Year</Label>
                        <select
                            id="filter-session"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            value={filters.academic_session_id ?? ''}
                            onChange={(e) => updateFilter('academic_session_id', e.target.value)}
                        >
                            <option value="">All sessions</option>
                            {(sessions ?? []).map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="filter-gender">Gender</Label>
                        <select
                            id="filter-gender"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            value={filters.gender ?? ''}
                            onChange={(e) => updateFilter('gender', e.target.value)}
                        >
                            <option value="">All</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="filter-residence">Boarding/Day</Label>
                        <select
                            id="filter-residence"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            value={filters.residence_type ?? ''}
                            onChange={(e) => updateFilter('residence_type', e.target.value)}
                        >
                            <option value="">All</option>
                            <option value="day">Day</option>
                            <option value="boarding">Boarding</option>
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="filter-status">Status</Label>
                        <select
                            id="filter-status"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            value={filters.status ?? ''}
                            onChange={(e) => updateFilter('status', e.target.value)}
                        >
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="transferred">Transferred</option>
                            <option value="graduated">Graduated</option>
                            <option value="withdrawn">Withdrawn</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    {hasActiveFilters && (
                        <div className="flex items-end sm:col-span-2 lg:col-span-4">
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="h-4 w-4" />
                                Clear filters
                            </Button>
                        </div>
                    )}
                </div>
            </Card>

            {isLoading && (
                <Card className="p-6">
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-10 w-full" />
                        ))}
                    </div>
                </Card>
            )}

            {isError && (
                <Alert variant="destructive">
                    <AlertDescription>Unable to load students. Please try again.</AlertDescription>
                </Alert>
            )}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Card>
                    <EmptyState
                        title={hasActiveFilters ? 'No students match your filters' : 'No students at this time'}
                        description={
                            hasActiveFilters
                                ? 'Try adjusting your search or filters.'
                                : 'Students will appear here after they enroll in your school.'
                        }
                        action={
                            canAdmit && !hasActiveFilters ? (
                                <Button onClick={() => navigate('/students/new')}>
                                    <Plus className="h-4 w-4" />
                                    Add Student
                                </Button>
                            ) : undefined
                        }
                    />
                </Card>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Card className="overflow-hidden p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Admission No.</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Class</TableHead>
                                <TableHead>Stream</TableHead>
                                <TableHead>Residence</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.data.map((student) => (
                                <TableRow
                                    key={student.id}
                                    className="cursor-pointer"
                                    onClick={() => navigate(`/students/${student.id}`)}
                                >
                                    <TableCell className="font-medium">{student.admission_number}</TableCell>
                                    <TableCell>{student.full_name}</TableCell>
                                    <TableCell>{student.current_enrolment?.class_name ?? '—'}</TableCell>
                                    <TableCell>{student.current_enrolment?.stream_name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={student.residence_type === 'boarding' ? 'secondary' : 'outline'}>
                                            {student.residence_type}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="capitalize text-muted-foreground">{student.status}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {data.meta.last_page > 1 && (
                        <div className="flex items-center justify-center gap-2 border-t p-4">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={page <= 1}
                                onClick={() => setPage((p) => p - 1)}
                            >
                                Previous
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                Page {data.meta.current_page} of {data.meta.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={page >= data.meta.last_page}
                                onClick={() => setPage((p) => p + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    )}
                </Card>
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
        </div>
    );
}
