import { useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
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
import { usePaymentSlips } from '../api/usePaymentSlips';
import { SlipStatusBadge } from '../components/SlipStatusBadge';
import { formatMoney } from '../../../lib/formatMoney';
import { ExportButtons } from '../../../components/ExportButtons';

/**
 * Lists the current user's own submitted slips (a parent sees only their
 * wards' slips — the API scopes this; see PaymentSlipController::index).
 */
export function MySlipsPage() {
    const { data, isLoading, isError } = usePaymentSlips();
    const [exportError, setExportError] = useState<string | null>(null);

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">My Payment Slips</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/payment-slips/export"
                        filenamePrefix="payment-slips"
                        onError={(message) => setExportError(message)}
                    />
                    <Button variant="contained" component={RouterLink} to="/finance/submit-slip">
                        Submit New Slip
                    </Button>
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

            {isError && <Alert severity="error">Unable to load your payment slips. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">You have not submitted any payment slips yet.</Alert>
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
                                        <TableCell>{formatMoney(slip.total_amount, slip.currency)}</TableCell>
                                        <TableCell>
                                            <SlipStatusBadge status={slip.status} />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Button
                                                size="small"
                                                component={RouterLink}
                                                to={`/finance/my-slips/${slip.id}`}
                                            >
                                                View
                                            </Button>
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
