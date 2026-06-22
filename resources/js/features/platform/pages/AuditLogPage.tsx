import { useState } from 'react';
import {
    Alert,
    Box,
    Chip,
    CircularProgress,
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
import { useAuditLogs } from '../api/usePlatform';
import { ExportButtons } from '../../../components/ExportButtons';
import type { AuditLogFilters } from '../types/platform';

const EMPTY_FILTERS: AuditLogFilters = {};

/** Platform Admin: a single cross-tenant feed of everything (PRD-adjacent — "view all activities done by anyone"). */
export function AuditLogPage() {
    const [filters, setFilters] = useState<AuditLogFilters>(EMPTY_FILTERS);
    const { data, isLoading, isError } = useAuditLogs(filters);
    const [exportError, setExportError] = useState<string | null>(null);

    const setFilter = (key: keyof AuditLogFilters) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setFilters((prev) => ({ ...prev, [key]: e.target.value || undefined }));

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Audit Log</Typography>
                <ExportButtons
                    endpoint="/platform/audit-logs/export"
                    filenamePrefix="audit-logs"
                    params={filters as Record<string, string | number | undefined>}
                    onError={(message) => setExportError(message)}
                />
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            <Stack direction="row" spacing={2} mb={2} flexWrap="wrap">
                <TextField label="Tenant ID" size="small" value={filters.tenant_id ?? ''} onChange={setFilter('tenant_id')} />
                <TextField label="Action" size="small" value={filters.action ?? ''} onChange={setFilter('action')} />
                <TextField
                    label="From"
                    type="date"
                    size="small"
                    InputLabelProps={{ shrink: true }}
                    value={filters.from ?? ''}
                    onChange={setFilter('from')}
                />
                <TextField
                    label="To"
                    type="date"
                    size="small"
                    InputLabelProps={{ shrink: true }}
                    value={filters.to ?? ''}
                    onChange={setFilter('to')}
                />
            </Stack>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load the audit log. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No activity matches these filters.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>When</TableCell>
                                    <TableCell>Tenant</TableCell>
                                    <TableCell>Actor</TableCell>
                                    <TableCell>Action</TableCell>
                                    <TableCell>Subject</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((entry) => (
                                    <TableRow key={entry.id} hover>
                                        <TableCell>{new Date(entry.created_at).toLocaleString()}</TableCell>
                                        <TableCell>{entry.tenant_id ?? <Chip label="platform" size="small" />}</TableCell>
                                        <TableCell>
                                            {entry.actor_name ?? '—'}
                                            {entry.actor_type === 'platform_admin' && (
                                                <Chip label="platform admin" size="small" sx={{ ml: 1 }} />
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <code>{entry.action}</code>
                                        </TableCell>
                                        <TableCell>
                                            {entry.subject_type ? `${entry.subject_type} #${entry.subject_id?.slice(0, 8)}` : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}
        </Box>
    );
}
