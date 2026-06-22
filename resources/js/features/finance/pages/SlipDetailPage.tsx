import { useParams, Link as RouterLink } from 'react-router-dom';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Divider,
    Grid,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import { usePaymentSlip } from '../api/usePaymentSlips';
import { SlipStatusBadge } from '../components/SlipStatusBadge';
import { formatMoney } from '../../../lib/formatMoney';

/**
 * Read-only detail view for a single payment slip — used from MySlipsPage
 * (parent) and could be linked to from the verification queue as well. Shows
 * the receipt link once verified; attachments are linked (not previewed
 * inline, per the task spec).
 */
export function SlipDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { data: slip, isLoading, isError } = usePaymentSlip(id);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (isError || !slip) {
        return <Alert severity="error">Unable to load this payment slip.</Alert>;
    }

    return (
        <Box maxWidth={900}>
            <Stack direction="row" justifyContent="space-between" alignItems="flex-start" mb={2}>
                <Box>
                    <Typography variant="h5">{slip.slip_number}</Typography>
                    <Typography variant="body2" color="text.secondary">
                        {slip.student_name ?? 'Unknown student'}
                    </Typography>
                </Box>
                <SlipStatusBadge status={slip.status} />
            </Stack>

            <Grid container spacing={2}>
                <Grid item xs={12} md={6}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="subtitle1" gutterBottom>
                            Payment Details
                        </Typography>
                        <Divider sx={{ mb: 2 }} />
                        <Stack spacing={1}>
                            <Typography variant="body2">
                                <strong>Depositor:</strong> {slip.depositor_name}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Deposit Date:</strong> {slip.deposit_date ?? '—'}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Total Amount:</strong> {formatMoney(slip.total_amount, slip.currency)}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Bank:</strong> {slip.bank_name ?? '—'}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Branch:</strong> {slip.branch_name ?? '—'}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Teller Number:</strong> {slip.teller_number ?? '—'}
                            </Typography>
                            <Typography variant="body2">
                                <strong>Transaction Reference:</strong> {slip.transaction_reference ?? '—'}
                            </Typography>
                            {slip.notes && (
                                <Typography variant="body2">
                                    <strong>Notes:</strong> {slip.notes}
                                </Typography>
                            )}
                        </Stack>
                    </Paper>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="subtitle1" gutterBottom>
                            Allocation
                        </Typography>
                        <Divider sx={{ mb: 2 }} />
                        <Stack spacing={1}>
                            {slip.allocation.map((line, index) => (
                                <Stack key={index} direction="row" justifyContent="space-between">
                                    <Typography variant="body2">{line.fee_type}</Typography>
                                    <Typography variant="body2">{formatMoney(line.amount, slip.currency)}</Typography>
                                </Stack>
                            ))}
                        </Stack>
                    </Paper>

                    <Paper sx={{ p: 3, mt: 2 }}>
                        <Typography variant="subtitle1" gutterBottom>
                            Attachments
                        </Typography>
                        <Divider sx={{ mb: 2 }} />
                        <Stack spacing={1}>
                            {slip.slip_attachments.length === 0 && (
                                <Typography variant="body2" color="text.secondary">
                                    No attachments.
                                </Typography>
                            )}
                            {slip.slip_attachments.map((attachment, index) => (
                                <Button
                                    key={index}
                                    size="small"
                                    href={attachment.file_path}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    sx={{ justifyContent: 'flex-start' }}
                                >
                                    {attachment.file_name}
                                </Button>
                            ))}
                        </Stack>
                    </Paper>

                    {slip.status === 'verified' && slip.receipt && (
                        <Paper sx={{ p: 3, mt: 2 }}>
                            <Typography variant="subtitle1" gutterBottom>
                                Receipt
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Typography variant="body2" gutterBottom>
                                {slip.receipt.receipt_number}
                            </Typography>
                            {slip.receipt.file_path && (
                                <Button
                                    size="small"
                                    variant="outlined"
                                    href={slip.receipt.file_path}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Download Receipt
                                </Button>
                            )}
                        </Paper>
                    )}

                    {slip.status === 'rejected' && (
                        <Paper sx={{ p: 3, mt: 2 }}>
                            <Typography variant="subtitle1" gutterBottom color="error">
                                Rejection Reason
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Typography variant="body2">{slip.rejection_reason}</Typography>
                        </Paper>
                    )}

                    {slip.verification_notes && (
                        <Paper sx={{ p: 3, mt: 2 }}>
                            <Typography variant="subtitle1" gutterBottom>
                                Verification Notes
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Typography variant="body2">{slip.verification_notes}</Typography>
                        </Paper>
                    )}
                </Grid>
            </Grid>

            <Box mt={2}>
                <Button component={RouterLink} to="/finance/my-slips">
                    Back to My Slips
                </Button>
            </Box>
        </Box>
    );
}
