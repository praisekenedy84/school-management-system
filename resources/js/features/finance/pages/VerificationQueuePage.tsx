import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
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
import { usePermissions } from '../../../lib/usePermissions';
import { usePaymentSlips } from '../api/usePaymentSlips';
import { SlipStatusBadge } from '../components/SlipStatusBadge';
import { SlipReviewDrawer } from '../components/SlipReviewDrawer';
import { EmphasizedMoney } from '../../../components/AccountingListTotal';
import { ExportButtons } from '../../../components/ExportButtons';
import type { PaymentSlip, PaymentSlipStatus } from '../types/finance';

const STATUS_FILTER_OPTIONS: { value: PaymentSlipStatus | ''; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'under_review', label: 'Under Review' },
    { value: 'clarification_needed', label: 'Clarification Needed' },
    { value: 'verified', label: 'Verified' },
    { value: 'rejected', label: 'Rejected' },
];

/**
 * Finance-staff verification queue, gated to finance_manager / accountant /
 * school_admin / tenant_admin (mirrors Phase 2's publish-gating pattern in
 * AssessmentsPage). The route itself is wrapped in <RequireFinanceStaff>
 * (redirects non-staff to "/"); this in-page check is defence-in-depth in
 * case the page is ever rendered outside that route, consistent with
 * RULES §8 (UI hiding is UX only — the API still authorizes every
 * verify/reject call server-side).
 */
export function VerificationQueuePage() {
    const { canAction } = usePermissions();
    const canVerify = canAction('verifySlips');
    const [statusFilter, setStatusFilter] = useState<PaymentSlipStatus | ''>('pending');
    const [selectedSlip, setSelectedSlip] = useState<PaymentSlip | null>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const { data, isLoading, isError } = usePaymentSlips({
        status: statusFilter || undefined,
    });

    if (!canVerify) {
        return (
            <Box p={3}>
                <Alert severity="warning">You do not have permission to view the verification queue.</Alert>
            </Box>
        );
    }

    const openReview = (slip: PaymentSlip) => {
        setSelectedSlip(slip);
        setDrawerOpen(true);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Verification Queue</Typography>
                <Stack direction="row" spacing={2} alignItems="center">
                    <TextField
                        select
                        size="small"
                        label="Status"
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value as PaymentSlipStatus | '')}
                        sx={{ minWidth: 220 }}
                    >
                        {STATUS_FILTER_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <ExportButtons
                        endpoint="/payment-slips/export"
                        filenamePrefix="payment-slips"
                        params={statusFilter ? { status: statusFilter } : undefined}
                        onError={(message) => setExportError(message)}
                    />
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

            {isError && <Alert severity="error">Unable to load the verification queue. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No payment slips match this filter.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Slip Number</TableCell>
                                    <TableCell>Student</TableCell>
                                    <TableCell>Deposit Date</TableCell>
                                    <TableCell>Amount</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((slip) => (
                                    <TableRow key={slip.id} hover>
                                        <TableCell>{slip.slip_number}</TableCell>
                                        <TableCell>{slip.student_name ?? '—'}</TableCell>
                                        <TableCell>{slip.deposit_date ?? '—'}</TableCell>
                                        <TableCell>
                                            <EmphasizedMoney amount={slip.total_amount} currency={slip.currency} />
                                        </TableCell>
                                        <TableCell>
                                            <SlipStatusBadge status={slip.status} />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Button size="small" onClick={() => openReview(slip)}>
                                                Review
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <SlipReviewDrawer
                slip={selectedSlip}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
            />
        </Box>
    );
}
