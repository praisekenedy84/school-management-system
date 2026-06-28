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
import { useStoreRequisitions } from '../api/useStoreRequisitions';
import {
    useCreateStoreRequisition,
    useCancelStoreRequisition,
    useSubmitStoreRequisition,
    useUpdateStoreRequisition,
} from '../api/useStoreRequisitionMutations';
import { RequisitionStatusBadge } from '../components/RequisitionStatusBadge';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { usePermissions } from '../../../lib/usePermissions';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { toNameOptions } from '../../../lib/selectOptions';
import type { StoreRequisition, StoreRequisitionLineInput, StoreRequisitionRequest } from '../types/stores';

interface LineDraft {
    inventory_item_id: string;
    requested_quantity: string;
    line_notes: string;
}

function emptyLine(): LineDraft {
    return { inventory_item_id: '', requested_quantity: '', line_notes: '' };
}

function RequisitionFormDialog({
    open,
    requisition,
    onClose,
}: {
    open: boolean;
    requisition: StoreRequisition | null;
    onClose: () => void;
}) {
    const { data: items } = useInventoryItems();
    const createRequisition = useCreateStoreRequisition();
    const updateRequisition = useUpdateStoreRequisition();
    const submitRequisition = useSubmitStoreRequisition();

    const [purpose, setPurpose] = useState('');
    const [neededBy, setNeededBy] = useState('');
    const [lines, setLines] = useState<LineDraft[]>([emptyLine()]);
    const [serverError, setServerError] = useState<string | null>(null);

    const itemOptions = toNameOptions(items, (item) => `${item.current_quantity} ${item.unit}`);

    const buildPayload = (): StoreRequisitionRequest => ({
        purpose: purpose || null,
        needed_by: neededBy || null,
        lines: lines
            .filter((line) => line.inventory_item_id && line.requested_quantity)
            .map(
                (line): StoreRequisitionLineInput => ({
                    inventory_item_id: line.inventory_item_id,
                    requested_quantity: line.requested_quantity,
                    line_notes: line.line_notes || null,
                }),
            ),
    });

    const handleSave = async (andSubmit: boolean) => {
        setServerError(null);
        const payload = buildPayload();
        if (payload.lines.length === 0) {
            setServerError('Add at least one line with an item and quantity.');
            return;
        }

        try {
            let saved: StoreRequisition;
            if (requisition) {
                saved = await updateRequisition.mutateAsync({ id: requisition.id, payload });
            } else {
                saved = await createRequisition.mutateAsync(payload);
            }
            if (andSubmit) {
                await submitRequisition.mutateAsync(saved.id);
            }
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save requisition.'));
        }
    };

    const isBusy = createRequisition.isPending || updateRequisition.isPending || submitRequisition.isPending;

    const draftEstimatedTotal = lines
        .filter((line) => line.inventory_item_id && line.requested_quantity)
        .reduce((sum, line) => {
            const item = items?.find((entry) => entry.id === line.inventory_item_id);
            if (!item) return sum;
            return sum + parseFloat(line.requested_quantity) * parseFloat(item.unit_cost);
        }, 0)
        .toFixed(2);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullWidth
            maxWidth="md"
            TransitionProps={{
                onEnter: () => {
                    setPurpose(requisition?.purpose ?? '');
                    setNeededBy(requisition?.needed_by ?? '');
                    setLines(
                        requisition?.lines?.length
                            ? requisition.lines.map((line) => ({
                                  inventory_item_id: line.inventory_item_id,
                                  requested_quantity: line.requested_quantity,
                                  line_notes: line.line_notes ?? '',
                              }))
                            : [emptyLine()],
                    );
                    setServerError(null);
                },
            }}
        >
            <DialogTitle>{requisition ? 'Edit Requisition' : 'New Requisition'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Purpose"
                        value={purpose}
                        onChange={(e) => setPurpose(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        type="date"
                        label="Needed By"
                        InputLabelProps={{ shrink: true }}
                        value={neededBy}
                        onChange={(e) => setNeededBy(e.target.value)}
                    />

                    <Typography variant="subtitle2">Lines</Typography>
                    {lines.map((line, index) => (
                        <Stack key={index} direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems="flex-start">
                            <Box flex={2}>
                                <SearchableSelect
                                    label="Item"
                                    options={itemOptions}
                                    value={line.inventory_item_id}
                                    onChange={(value) =>
                                        setLines((prev) =>
                                            prev.map((row, i) =>
                                                i === index ? { ...row, inventory_item_id: value } : row,
                                            ),
                                        )
                                    }
                                />
                            </Box>
                            <TextField
                                label="Qty"
                                type="number"
                                inputProps={{ min: 0, step: '0.001' }}
                                value={line.requested_quantity}
                                onChange={(e) =>
                                    setLines((prev) =>
                                        prev.map((row, i) =>
                                            i === index ? { ...row, requested_quantity: e.target.value } : row,
                                        ),
                                    )
                                }
                                sx={{ width: { xs: '100%', sm: 120 } }}
                            />
                            <TextField
                                label="Notes"
                                value={line.line_notes}
                                onChange={(e) =>
                                    setLines((prev) =>
                                        prev.map((row, i) =>
                                            i === index ? { ...row, line_notes: e.target.value } : row,
                                        ),
                                    )
                                }
                                sx={{ flex: 1, width: '100%' }}
                            />
                            <IconButton
                                disabled={lines.length === 1}
                                onClick={() => setLines((prev) => prev.filter((_, i) => i !== index))}
                            >
                                <Trash2 size={16} />
                            </IconButton>
                        </Stack>
                    ))}
                    <Button startIcon={<Plus size={16} />} onClick={() => setLines((prev) => [...prev, emptyLine()])}>
                        Add Line
                    </Button>
                    <AccountingListTotal label="Estimated Total Value" amount={draftEstimatedTotal} />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="outlined" disabled={isBusy} onClick={() => handleSave(false)}>
                    Save Draft
                </Button>
                <Button variant="contained" disabled={isBusy} onClick={() => handleSave(true)}>
                    {isBusy ? 'Submitting…' : 'Save & Submit'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** kitchen_staff create and track their own store requisitions. */
export function MyRequisitionsPage() {
    const { canAction } = usePermissions();
    const canCreate = canAction('createRequisitions');
    const { data, isLoading, isError } = useStoreRequisitions();
    const submitRequisition = useSubmitStoreRequisition();
    const cancelRequisition = useCancelStoreRequisition();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRequisition, setEditingRequisition] = useState<StoreRequisition | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingRequisition(null);
        setDialogOpen(true);
    };

    const openEdit = (requisition: StoreRequisition) => {
        setEditingRequisition(requisition);
        setDialogOpen(true);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">My Requisitions</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/store-requisitions/export"
                        filenamePrefix="store-requisitions"
                        onError={(message) => setExportError(message)}
                    />
                    {canCreate && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Requisition
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

            {isError && <Alert severity="error">Unable to load requisitions. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">You have not created any requisitions yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Number</TableCell>
                                    <TableCell>Purpose</TableCell>
                                    <TableCell>Needed By</TableCell>
                                    <TableCell align="right">Est. Value</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((requisition) => (
                                    <TableRow key={requisition.id} hover>
                                        <TableCell>{requisition.requisition_number}</TableCell>
                                        <TableCell>{requisition.purpose ?? '—'}</TableCell>
                                        <TableCell>{requisition.needed_by ?? '—'}</TableCell>
                                        <TableCell align="right">
                                            {requisition.estimated_total ? (
                                                <EmphasizedMoney amount={requisition.estimated_total} />
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <RequisitionStatusBadge status={requisition.status} />
                                        </TableCell>
                                        <TableCell align="right">
                                            {requisition.status === 'draft' && (
                                                <>
                                                    <Button size="small" onClick={() => openEdit(requisition)}>
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="small"
                                                        variant="contained"
                                                        onClick={() => submitRequisition.mutate(requisition.id)}
                                                    >
                                                        Submit
                                                    </Button>
                                                    <Button
                                                        size="small"
                                                        color="error"
                                                        onClick={() => cancelRequisition.mutate(requisition.id)}
                                                    >
                                                        Cancel
                                                    </Button>
                                                </>
                                            )}
                                            {requisition.status === 'submitted' && (
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() => cancelRequisition.mutate(requisition.id)}
                                                >
                                                    Cancel
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <RequisitionFormDialog
                open={dialogOpen}
                requisition={editingRequisition}
                onClose={() => setDialogOpen(false)}
            />
        </Box>
    );
}
