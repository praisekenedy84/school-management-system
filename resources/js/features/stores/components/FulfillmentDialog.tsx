import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { useFulfillPurchaseRequest } from '../api/usePurchaseRequestMutations';
import { AccountingListTotal } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { PurchaseRequest } from '../types/stores';

/**
 * Finance fulfillment dialog — side-by-side requested vs received quantities
 * and costs per purchase request line.
 */
export function FulfillmentDialog({
    purchaseRequest,
    open,
    onClose,
}: {
    purchaseRequest: PurchaseRequest | null;
    open: boolean;
    onClose: () => void;
}) {
    const fulfill = useFulfillPurchaseRequest();

    const [supplierName, setSupplierName] = useState('');
    const [supplierReference, setSupplierReference] = useState('');
    const [fulfillmentDate, setFulfillmentDate] = useState('');
    const [notes, setNotes] = useState('');
    const [receivedQuantities, setReceivedQuantities] = useState<Record<string, string>>({});
    const [actualCosts, setActualCosts] = useState<Record<string, string>>({});
    const [attachments, setAttachments] = useState<File[]>([]);
    const [serverError, setServerError] = useState<string | null>(null);

    const resetForm = () => {
        setSupplierName('');
        setSupplierReference('');
        setFulfillmentDate(new Date().toISOString().slice(0, 10));
        setNotes('');
        setReceivedQuantities({});
        setActualCosts({});
        setAttachments([]);
        setServerError(null);

        if (purchaseRequest?.lines) {
            const qtyDefaults: Record<string, string> = {};
            const costDefaults: Record<string, string> = {};
            purchaseRequest.lines.forEach((line) => {
                qtyDefaults[line.id] = line.effective_quantity;
                costDefaults[line.id] = line.effective_unit_cost;
            });
            setReceivedQuantities(qtyDefaults);
            setActualCosts(costDefaults);
        }
    };

    const requestedTotal = purchaseRequest?.effective_total ?? purchaseRequest?.estimated_total ?? '0';

    const actualTotal = (purchaseRequest?.lines ?? [])
        .reduce((sum, line) => {
            const qty = parseFloat(receivedQuantities[line.id] ?? '0');
            const cost = parseFloat(actualCosts[line.id] ?? line.effective_unit_cost);
            return sum + qty * cost;
        }, 0)
        .toFixed(2);

    const handleFulfill = async () => {
        if (!purchaseRequest) return;
        setServerError(null);

        if (!fulfillmentDate) {
            setServerError('Fulfillment date is required.');
            return;
        }

        const lines = (purchaseRequest.lines ?? []).map((line) => ({
            purchase_request_line_id: line.id,
            received_quantity: receivedQuantities[line.id] ?? '0',
            actual_unit_cost: actualCosts[line.id] ?? line.effective_unit_cost,
        }));

        try {
            await fulfill.mutateAsync({
                id: purchaseRequest.id,
                payload: {
                    supplier_name: supplierName || null,
                    supplier_reference: supplierReference || null,
                    fulfillment_date: fulfillmentDate,
                    notes: notes || null,
                    lines,
                },
                files: attachments,
            });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to record fulfillment.'));
        }
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullWidth
            maxWidth="lg"
            TransitionProps={{ onEnter: resetForm }}
        >
            <DialogTitle>Fulfill {purchaseRequest?.request_number ?? 'Purchase Request'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                <Stack spacing={2} mt={1}>
                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                        <TextField
                            fullWidth
                            label="Supplier Name"
                            value={supplierName}
                            onChange={(e) => setSupplierName(e.target.value)}
                        />
                        <TextField
                            fullWidth
                            label="Invoice / Receipt Reference"
                            value={supplierReference}
                            onChange={(e) => setSupplierReference(e.target.value)}
                        />
                        <TextField
                            fullWidth
                            type="date"
                            label="Fulfillment Date"
                            InputLabelProps={{ shrink: true }}
                            value={fulfillmentDate}
                            onChange={(e) => setFulfillmentDate(e.target.value)}
                        />
                    </Stack>
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Notes"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                    />
                    <Button variant="outlined" component="label">
                        Attach Receipts
                        <input
                            hidden
                            multiple
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            onChange={(e) => setAttachments(Array.from(e.target.files ?? []))}
                        />
                    </Button>
                    {attachments.length > 0 && (
                        <Typography variant="caption">{attachments.length} file(s) selected</Typography>
                    )}

                    <Typography variant="subtitle2">Requested vs Received</Typography>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Item</TableCell>
                                <TableCell align="right">Requested Qty</TableCell>
                                <TableCell align="right">Requested Cost</TableCell>
                                <TableCell align="right">Requested Total</TableCell>
                                <TableCell align="right">Received Qty</TableCell>
                                <TableCell align="right">Actual Cost</TableCell>
                                <TableCell align="right">Actual Total</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {(purchaseRequest?.lines ?? []).map((line) => {
                                const receivedQty = receivedQuantities[line.id] ?? '';
                                const actualCost = actualCosts[line.id] ?? line.effective_unit_cost;
                                const actualLineTotal = (
                                    parseFloat(receivedQty || '0') * parseFloat(actualCost || '0')
                                ).toFixed(2);

                                return (
                                <TableRow key={line.id}>
                                    <TableCell>{line.item_name}</TableCell>
                                    <TableCell align="right">
                                        {line.effective_quantity} {line.unit}
                                    </TableCell>
                                    <TableCell align="right">
                                        {formatMoney(line.effective_unit_cost)}
                                    </TableCell>
                                    <TableCell align="right">{formatMoney(line.effective_line_total)}</TableCell>
                                    <TableCell align="right">
                                        <TextField
                                            size="small"
                                            type="number"
                                            inputProps={{ min: 0, step: '0.001' }}
                                            value={receivedQty}
                                            onChange={(e) =>
                                                setReceivedQuantities((prev) => ({
                                                    ...prev,
                                                    [line.id]: e.target.value,
                                                }))
                                            }
                                            sx={{ width: 100 }}
                                        />
                                    </TableCell>
                                    <TableCell align="right">
                                        <TextField
                                            size="small"
                                            type="number"
                                            inputProps={{ min: 0, step: '0.01' }}
                                            value={actualCosts[line.id] ?? ''}
                                            onChange={(e) =>
                                                setActualCosts((prev) => ({
                                                    ...prev,
                                                    [line.id]: e.target.value,
                                                }))
                                            }
                                            sx={{ width: 120 }}
                                        />
                                    </TableCell>
                                    <TableCell align="right">{formatMoney(actualLineTotal)}</TableCell>
                                </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    <AccountingListTotal
                        label="Requested Total"
                        amount={requestedTotal}
                        secondary={{ label: 'Actual Total', amount: actualTotal }}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="contained" disabled={fulfill.isPending} onClick={handleFulfill}>
                    Record Fulfillment
                </Button>
            </DialogActions>
        </Dialog>
    );
}
