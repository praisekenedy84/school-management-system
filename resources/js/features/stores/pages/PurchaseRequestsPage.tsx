import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
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
import { Plus, Trash2 } from 'lucide-react';
import { SearchableSelect } from '../../../components/SearchableSelect';
import { ExportButtons } from '../../../components/ExportButtons';
import { useInventoryItems } from '../api/useInventoryItems';
import { usePurchaseRequests } from '../api/usePurchaseRequests';
import {
    useCreatePurchaseRequest,
    useSubmitPurchaseRequest,
    useUpdatePurchaseRequest,
} from '../api/usePurchaseRequestMutations';
import { PurchaseRequestStatusBadge } from '../components/PurchaseRequestStatusBadge';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { usePermissions } from '../../../lib/usePermissions';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { toNameOptions } from '../../../lib/selectOptions';
import type { PurchaseRequest, PurchaseRequestForm, PurchaseRequestLineInput } from '../types/stores';

interface LineDraft {
    inventory_item_id: string;
    item_name: string;
    unit: string;
    requested_quantity: string;
    estimated_unit_cost: string;
    line_notes: string;
    useCatalog: boolean;
}

function emptyLine(): LineDraft {
    return {
        inventory_item_id: '',
        item_name: '',
        unit: '',
        requested_quantity: '',
        estimated_unit_cost: '0',
        line_notes: '',
        useCatalog: true,
    };
}

function PurchaseRequestFormDialog({
    open,
    purchaseRequest,
    onClose,
}: {
    open: boolean;
    purchaseRequest: PurchaseRequest | null;
    onClose: () => void;
}) {
    const { data: items } = useInventoryItems();
    const createRequest = useCreatePurchaseRequest();
    const updateRequest = useUpdatePurchaseRequest();
    const submitRequest = useSubmitPurchaseRequest();

    const [title, setTitle] = useState('');
    const [notes, setNotes] = useState('');
    const [lines, setLines] = useState<LineDraft[]>([emptyLine()]);
    const [serverError, setServerError] = useState<string | null>(null);

    const itemOptions = toNameOptions(items, (item) => item.unit);

    const buildPayload = (): PurchaseRequestForm => ({
        title: title || null,
        notes: notes || null,
        lines: lines
            .filter((line) => line.requested_quantity && (line.inventory_item_id || line.item_name))
            .map(
                (line): PurchaseRequestLineInput => ({
                    inventory_item_id: line.useCatalog && line.inventory_item_id ? line.inventory_item_id : null,
                    item_name:
                        line.useCatalog && line.inventory_item_id
                            ? (items?.find((item) => item.id === line.inventory_item_id)?.name ?? line.item_name)
                            : line.item_name,
                    unit: line.unit,
                    requested_quantity: line.requested_quantity,
                    estimated_unit_cost: line.estimated_unit_cost || '0',
                    line_notes: line.line_notes || null,
                }),
            ),
    });

    const handleSave = async (andSubmit: boolean) => {
        setServerError(null);
        const payload = buildPayload();
        if (payload.lines.length === 0) {
            setServerError('Add at least one line with item and quantity.');
            return;
        }

        try {
            let saved: PurchaseRequest;
            if (purchaseRequest) {
                saved = await updateRequest.mutateAsync({ id: purchaseRequest.id, payload });
            } else {
                saved = await createRequest.mutateAsync(payload);
            }
            if (andSubmit) {
                await submitRequest.mutateAsync(saved.id);
            }
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save purchase request.'));
        }
    };

    const isBusy = createRequest.isPending || updateRequest.isPending || submitRequest.isPending;

    const draftTotal = lines
        .filter((line) => line.requested_quantity && (line.inventory_item_id || line.item_name))
        .reduce(
            (sum, line) =>
                sum + parseFloat(line.requested_quantity || '0') * parseFloat(line.estimated_unit_cost || '0'),
            0,
        )
        .toFixed(2);

    const updateLine = (index: number, patch: Partial<LineDraft>) => {
        setLines((prev) =>
            prev.map((row, i) => {
                if (i !== index) return row;
                const next = { ...row, ...patch };
                if (patch.inventory_item_id) {
                    const item = items?.find((entry) => entry.id === patch.inventory_item_id);
                    if (item) {
                        next.item_name = item.name;
                        next.unit = item.unit;
                        next.estimated_unit_cost = item.unit_cost;
                    }
                }
                return next;
            }),
        );
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullWidth
            maxWidth="lg"
            TransitionProps={{
                onEnter: () => {
                    setTitle(purchaseRequest?.title ?? '');
                    setNotes(purchaseRequest?.notes ?? '');
                    setLines(
                        purchaseRequest?.lines?.length
                            ? purchaseRequest.lines.map((line) => ({
                                  inventory_item_id: line.inventory_item_id ?? '',
                                  item_name: line.item_name,
                                  unit: line.unit,
                                  requested_quantity: line.requested_quantity,
                                  estimated_unit_cost: line.estimated_unit_cost,
                                  line_notes: line.line_notes ?? '',
                                  useCatalog: Boolean(line.inventory_item_id),
                              }))
                            : [emptyLine()],
                    );
                    setServerError(null);
                },
            }}
        >
            <DialogTitle>{purchaseRequest ? 'Edit Purchase Request' : 'New Purchase Request'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField fullWidth label="Title" value={title} onChange={(e) => setTitle(e.target.value)} />
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Notes"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                    />

                    <Typography variant="subtitle2">Lines</Typography>
                    {lines.map((line, index) => (
                        <Stack key={index} spacing={1}>
                            {line.useCatalog ? (
                                <SearchableSelect
                                    label="Catalog Item"
                                    options={itemOptions}
                                    value={line.inventory_item_id}
                                    onChange={(value) => updateLine(index, { inventory_item_id: value })}
                                />
                            ) : (
                                <TextField
                                    fullWidth
                                    label="New Item Name"
                                    value={line.item_name}
                                    onChange={(e) => updateLine(index, { item_name: e.target.value })}
                                />
                            )}
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                                <TextField
                                    label="Unit"
                                    value={line.unit}
                                    onChange={(e) => updateLine(index, { unit: e.target.value })}
                                    sx={{ width: { xs: '100%', sm: 100 } }}
                                />
                                <TextField
                                    label="Qty"
                                    type="number"
                                    inputProps={{ min: 0, step: '0.001' }}
                                    value={line.requested_quantity}
                                    onChange={(e) => updateLine(index, { requested_quantity: e.target.value })}
                                    sx={{ width: { xs: '100%', sm: 120 } }}
                                />
                                <TextField
                                    label="Est. Unit Cost"
                                    type="number"
                                    inputProps={{ min: 0, step: '0.01' }}
                                    value={line.estimated_unit_cost}
                                    onChange={(e) => updateLine(index, { estimated_unit_cost: e.target.value })}
                                    sx={{ width: { xs: '100%', sm: 140 } }}
                                />
                                <TextField
                                    label="Notes"
                                    value={line.line_notes}
                                    onChange={(e) => updateLine(index, { line_notes: e.target.value })}
                                    sx={{ flex: 1 }}
                                />
                                <IconButton
                                    disabled={lines.length === 1}
                                    onClick={() => setLines((prev) => prev.filter((_, i) => i !== index))}
                                >
                                    <Trash2 size={16} />
                                </IconButton>
                            </Stack>
                            <Button size="small" onClick={() => updateLine(index, { useCatalog: !line.useCatalog })}>
                                {line.useCatalog ? 'Use free-text item instead' : 'Pick from catalog'}
                            </Button>
                        </Stack>
                    ))}
                    <Button startIcon={<Plus size={16} />} onClick={() => setLines((prev) => [...prev, emptyLine()])}>
                        Add Line
                    </Button>
                    <AccountingListTotal label="Estimated Total" amount={draftTotal} />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="outlined" disabled={isBusy} onClick={() => handleSave(false)}>
                    Save Draft
                </Button>
                <Button variant="contained" disabled={isBusy} onClick={() => handleSave(true)}>
                    Save & Submit
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** Storekeeper create and submit purchase requests to Finance. */
export function PurchaseRequestsPage() {
    const { canAction } = usePermissions();
    const canCreate = canAction('createPurchaseRequests');
    const { data, isLoading, isError } = usePurchaseRequests();
    const submitRequest = useSubmitPurchaseRequest();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRequest, setEditingRequest] = useState<PurchaseRequest | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingRequest(null);
        setDialogOpen(true);
    };

    const openEdit = (request: PurchaseRequest) => {
        setEditingRequest(request);
        setDialogOpen(true);
    };

    const lineCount = (request: PurchaseRequest) => request.lines?.length ?? 0;

    const requestTotal = (request: PurchaseRequest) =>
        request.effective_total ?? request.estimated_total ?? '0';

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Purchase Requests</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/purchase-requests/export"
                        filenamePrefix="purchase-requests"
                        onError={(message) => setExportError(message)}
                    />
                    {canCreate && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Request
                        </Button>
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

            {isError && <Alert severity="error">Unable to load purchase requests. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No purchase requests have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Number</TableCell>
                                    <TableCell>Title</TableCell>
                                    <TableCell>Lines</TableCell>
                                    <TableCell align="right">Est. Total</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((request) => (
                                    <TableRow key={request.id} hover>
                                        <TableCell>{request.request_number}</TableCell>
                                        <TableCell>{request.title ?? '—'}</TableCell>
                                        <TableCell>{lineCount(request)}</TableCell>
                                        <TableCell align="right">
                                            <EmphasizedMoney amount={requestTotal(request)} />
                                        </TableCell>
                                        <TableCell>
                                            <PurchaseRequestStatusBadge status={request.status} />
                                        </TableCell>
                                        <TableCell align="right">
                                            {request.status === 'draft' && (
                                                <>
                                                    <Button size="small" onClick={() => openEdit(request)}>
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="small"
                                                        variant="contained"
                                                        onClick={() => submitRequest.mutate(request.id)}
                                                    >
                                                        Submit
                                                    </Button>
                                                </>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <PurchaseRequestFormDialog
                open={dialogOpen}
                purchaseRequest={editingRequest}
                onClose={() => setDialogOpen(false)}
            />
        </Box>
    );
}
