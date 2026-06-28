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
    Typography,
} from '@mui/material';
import { useLowStockInventoryItems } from '../api/useInventoryItems';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { sumAmountStrings } from '../../../lib/sumAmounts';

/** Items at or below reorder level — storekeeper low-stock alert view. */
export function LowStockPage() {
    const { data, isLoading, isError } = useLowStockInventoryItems();
    const totalRestockValue = sumAmountStrings((data ?? []).map((item) => item.restock_value));

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Low Stock Alerts</Typography>
                {data && data.length > 0 && (
                    <Chip color="warning" label={`${data.length} item${data.length === 1 ? '' : 's'}`} />
                )}
            </Stack>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load low-stock items. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="success">All items are above their reorder levels.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <>
                    <AccountingListTotal
                        label="Estimated Restock Cost"
                        amount={totalRestockValue}
                    />
                    <Paper sx={{ mt: 2 }}>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Category</TableCell>
                                    <TableCell>Unit</TableCell>
                                    <TableCell align="right">Current Qty</TableCell>
                                    <TableCell align="right">Reorder Level</TableCell>
                                    <TableCell align="right">Shortfall</TableCell>
                                    <TableCell align="right">Unit Cost</TableCell>
                                    <TableCell align="right">Restock Cost</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((item) => {
                                    const shortfall =
                                        parseFloat(item.reorder_level) - parseFloat(item.current_quantity);

                                    return (
                                        <TableRow key={item.id} hover>
                                            <TableCell>{item.name}</TableCell>
                                            <TableCell>{item.category ?? '—'}</TableCell>
                                            <TableCell>{item.unit}</TableCell>
                                            <TableCell align="right">{item.current_quantity}</TableCell>
                                            <TableCell align="right">{item.reorder_level}</TableCell>
                                            <TableCell align="right">
                                                {shortfall > 0 ? shortfall.toFixed(3) : '—'}
                                            </TableCell>
                                            <TableCell align="right">
                                                {formatMoney(item.unit_cost, item.currency)}
                                            </TableCell>
                                            <TableCell align="right">
                                                <EmphasizedMoney
                                                    amount={item.restock_value}
                                                    currency={item.currency}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
                </>
            )}
        </Box>
    );
}
