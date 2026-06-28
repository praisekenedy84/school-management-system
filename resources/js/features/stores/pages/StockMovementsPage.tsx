import { useState } from 'react';
import {
    Alert,
    Box,
    Chip,
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
import { SearchableSelect } from '../../../components/SearchableSelect';
import { ExportButtons } from '../../../components/ExportButtons';
import { useInventoryItems } from '../api/useInventoryItems';
import { useStockMovements } from '../api/useStockMovements';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { sumAmountStrings } from '../../../lib/sumAmounts';
import { toNameOptions } from '../../../lib/selectOptions';
import type { StockMovementDirection } from '../types/stores';

const DIRECTION_OPTIONS: { value: StockMovementDirection | ''; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'in', label: 'Stock In' },
    { value: 'out', label: 'Stock Out' },
];

/** Append-only stock movement ledger with filters and export. */
export function StockMovementsPage() {
    const { data: items } = useInventoryItems();
    const [itemFilter, setItemFilter] = useState('');
    const [directionFilter, setDirectionFilter] = useState<StockMovementDirection | ''>('');
    const [fromDate, setFromDate] = useState('');
    const [toDate, setToDate] = useState('');
    const [exportError, setExportError] = useState<string | null>(null);

    const { data, isLoading, isError } = useStockMovements({
        inventory_item_id: itemFilter || undefined,
        direction: directionFilter || undefined,
        from_date: fromDate || undefined,
        to_date: toDate || undefined,
    });

    const itemOptions = toNameOptions(items);
    const totalMovementValue = sumAmountStrings((data ?? []).map((movement) => movement.line_value));

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Stock Movements</Typography>
                <ExportButtons
                    endpoint="/stock-movements/export"
                    filenamePrefix="stock-movements"
                    params={{
                        inventory_item_id: itemFilter || undefined,
                        direction: directionFilter || undefined,
                        from_date: fromDate || undefined,
                        to_date: toDate || undefined,
                    }}
                    onError={(message) => setExportError(message)}
                />
            </Stack>

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} mb={2}>
                <Box flex={1}>
                    <SearchableSelect
                        label="Item"
                        options={[{ id: '', label: 'All items' }, ...itemOptions]}
                        value={itemFilter}
                        onChange={setItemFilter}
                    />
                </Box>
                <TextField
                    select
                    size="small"
                    label="Direction"
                    value={directionFilter}
                    onChange={(e) => setDirectionFilter(e.target.value as StockMovementDirection | '')}
                    sx={{ minWidth: 140 }}
                >
                    {DIRECTION_OPTIONS.map((option) => (
                        <MenuItem key={option.value} value={option.value}>
                            {option.label}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    size="small"
                    type="date"
                    label="From"
                    InputLabelProps={{ shrink: true }}
                    value={fromDate}
                    onChange={(e) => setFromDate(e.target.value)}
                />
                <TextField
                    size="small"
                    type="date"
                    label="To"
                    InputLabelProps={{ shrink: true }}
                    value={toDate}
                    onChange={(e) => setToDate(e.target.value)}
                />
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

            {isError && <Alert severity="error">Unable to load stock movements. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No stock movements match these filters.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <>
                    <AccountingListTotal label="Total Movement Value" amount={totalMovementValue} />
                    <Paper sx={{ mt: 2 }}>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Date</TableCell>
                                    <TableCell>Item</TableCell>
                                    <TableCell>Direction</TableCell>
                                    <TableCell align="right">Quantity</TableCell>
                                    <TableCell align="right">Unit Cost</TableCell>
                                    <TableCell align="right">Line Value</TableCell>
                                    <TableCell align="right">Balance After</TableCell>
                                    <TableCell>Reason</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((movement) => (
                                    <TableRow key={movement.id} hover>
                                        <TableCell>
                                            {movement.performed_at
                                                ? new Date(movement.performed_at).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>{movement.inventory_item?.name ?? movement.inventory_item_id}</TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={movement.direction === 'in' ? 'success' : 'warning'}
                                                label={movement.direction.toUpperCase()}
                                            />
                                        </TableCell>
                                        <TableCell align="right">{movement.quantity}</TableCell>
                                        <TableCell align="right">{formatMoney(movement.unit_cost)}</TableCell>
                                        <TableCell align="right">
                                            <EmphasizedMoney amount={movement.line_value} />
                                        </TableCell>
                                        <TableCell align="right">{movement.balance_after}</TableCell>
                                        <TableCell>{movement.reason.replace(/_/g, ' ')}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
                </>
            )}
        </Box>
    );
}
