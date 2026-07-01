import {
    Alert,
    Box,
    CircularProgress,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Typography,
} from '@mui/material';
import { useFeeStatement } from '../api/useFeeStatement';
import { formatMoney } from '../../../lib/formatMoney';

/**
 * Read-only per-item fee breakdown for parents — used on the ward profile
 * and above the slip submission allocation section.
 */
export function FeeStatementPanel({
    studentId,
    academicSessionId,
    title = 'Fee Statement',
    compact = false,
}: {
    studentId: string;
    academicSessionId: string;
    title?: string;
    compact?: boolean;
}) {
    const { data, isLoading, isError } = useFeeStatement(studentId, academicSessionId);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={compact ? 2 : 3}>
                <CircularProgress size={24} />
            </Box>
        );
    }

    if (isError) {
        return <Alert severity="error">Unable to load the fee statement.</Alert>;
    }

    if (!data || data.lines.length === 0) {
        return (
            <Alert severity="info">
                No fee items found for this student and session — please contact the school admin.
            </Alert>
        );
    }

    return (
        <Box>
            <Typography variant={compact ? 'subtitle2' : 'subtitle1'} gutterBottom>
                {title}
                {data.academic_session_name ? ` — ${data.academic_session_name}` : ''}
            </Typography>

            <TableContainer component={compact ? Box : Paper} variant={compact ? undefined : 'outlined'}>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Fee Item</TableCell>
                            <TableCell align="right">Total Charged</TableCell>
                            <TableCell align="right">Paid</TableCell>
                            <TableCell align="right">Balance</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {data.lines.map((line) => (
                            <TableRow key={line.fee_type}>
                                <TableCell>{line.fee_type}</TableCell>
                                <TableCell align="right">{formatMoney(line.total_charged)}</TableCell>
                                <TableCell align="right">{formatMoney(line.total_paid)}</TableCell>
                                <TableCell align="right">
                                    {Number(line.balance) <= 0 ? (
                                        <Typography component="span" color="success.main" variant="body2">
                                            Paid
                                        </Typography>
                                    ) : (
                                        formatMoney(line.balance)
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        <TableRow>
                            <TableCell>
                                <strong>Total</strong>
                            </TableCell>
                            <TableCell align="right">
                                <strong>{formatMoney(data.totals.total_charged)}</strong>
                            </TableCell>
                            <TableCell align="right">
                                <strong>{formatMoney(data.totals.total_paid)}</strong>
                            </TableCell>
                            <TableCell align="right">
                                <strong>{formatMoney(data.totals.balance)}</strong>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </TableContainer>

            {data.pending_slips.length > 0 && (
                <Alert severity="info" sx={{ mt: 1.5 }}>
                    {data.pending_slips.length} payment slip(s) pending verification — balances above
                    exclude these until finance approves them.
                </Alert>
            )}
        </Box>
    );
}
