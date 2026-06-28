import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Drawer,
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
import { usePurchaseRequests } from '../api/usePurchaseRequests';
import {
    useAmendPurchaseRequest,
    useApprovePurchaseRequest,
    useRejectPurchaseRequest,
} from '../api/usePurchaseRequestMutations';
import { FulfillmentDialog } from '../components/FulfillmentDialog';
import { PurchaseRequestStatusBadge } from '../components/PurchaseRequestStatusBadge';
import { ExportButtons } from '../../../components/ExportButtons';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { PurchaseRequest, PurchaseRequestStatus } from '../types/stores';

const QUEUE_STATUSES: PurchaseRequestStatus[] = ['submitted', 'under_review', 'approved', 'amended'];

const STATUS_FILTER_OPTIONS: { value: PurchaseRequestStatus | ''; label: string }[] = [
    { value: '', label: 'All Queue' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'under_review', label: 'Under Review' },
    { value: 'approved', label: 'Approved' },
    { value: 'amended', label: 'Amended' },
];

function ProcurementReviewDrawer({
    purchaseRequest,
    open,
    onClose,
    onFulfill,
}: {
    purchaseRequest: PurchaseRequest | null;
    open: boolean;
    onClose: () => void;
    onFulfill: () => void;
}) {
    const approve = useApprovePurchaseRequest();
    const amend = useAmendPurchaseRequest();
    const reject = useRejectPurchaseRequest();

    const [reviewNotes, setReviewNotes] = useState('');
    const [amendmentNotes, setAmendmentNotes] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [amendedQuantities, setAmendedQuantities] = useState<Record<string, string>>({});
    const [amendedCosts, setAmendedCosts] = useState<Record<string, string>>({});
    const [serverError, setServerError] = useState<string | null>(null);

    const resetForm = () => {
        setReviewNotes('');
        setAmendmentNotes('');
        setRejectionReason('');
        setAmendedQuantities({});
        setAmendedCosts({});
        setServerError(null);

        if (purchaseRequest?.lines) {
            const qty: Record<string, string> = {};
            const cost: Record<string, string> = {};
            purchaseRequest.lines.forEach((line) => {
                qty[line.id] = line.amended_quantity ?? line.requested_quantity;
                cost[line.id] = line.amended_unit_cost ?? line.estimated_unit_cost;
            });
            setAmendedQuantities(qty);
            setAmendedCosts(cost);
        }
    };

    const canDecide = purchaseRequest?.status === 'submitted' || purchaseRequest?.status === 'under_review';
    const canFulfill = purchaseRequest?.status === 'approved' || purchaseRequest?.status === 'amended';
    const isBusy = approve.isPending || amend.isPending || reject.isPending;

    const amendedEffectiveTotal = purchaseRequest
        ? (purchaseRequest.lines ?? [])
              .reduce((sum, line) => {
                  const qty = parseFloat(amendedQuantities[line.id] ?? line.effective_quantity);
                  const cost = parseFloat(amendedCosts[line.id] ?? line.effective_unit_cost);
                  return sum + qty * cost;
              }, 0)
              .toFixed(2)
        : '0.00';

    const handleApprove = async () => {
        if (!purchaseRequest) return;
        setServerError(null);
        try {
            await approve.mutateAsync({ id: purchaseRequest.id, payload: { review_notes: reviewNotes || null } });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to approve purchase request.'));
        }
    };

    const handleAmend = async () => {
        if (!purchaseRequest) return;
        setServerError(null);
        try {
            await amend.mutateAsync({
                id: purchaseRequest.id,
                payload: {
                    amendment_notes: amendmentNotes || null,
                    lines: (purchaseRequest.lines ?? []).map((line) => ({
                        line_id: line.id,
                        amended_quantity: amendedQuantities[line.id] ?? null,
                        amended_unit_cost: amendedCosts[line.id] ?? null,
                    })),
                },
            });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to amend purchase request.'));
        }
    };

    const handleReject = async () => {
        if (!purchaseRequest) return;
        setServerError(null);
        try {
            await reject.mutateAsync({
                id: purchaseRequest.id,
                payload: { rejection_reason: rejectionReason },
            });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to reject purchase request.'));
        }
    };

    return (
        <Drawer
            anchor="right"
            open={open}
            onClose={onClose}
            PaperProps={{ sx: { width: { xs: '100%', sm: 520 } } }}
            TransitionProps={{ onEnter: resetForm }}
        >
            <Box p={3}>
                <Typography variant="h6" gutterBottom>
                    {purchaseRequest?.request_number ?? 'Purchase Request'}
                </Typography>

                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                {purchaseRequest && (
                    <Stack spacing={2}>
                        <Typography variant="body2">
                            <strong>Title:</strong> {purchaseRequest.title ?? '—'}
                        </Typography>
                        <PurchaseRequestStatusBadge status={purchaseRequest.status} />

                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Item</TableCell>
                                    <TableCell align="right">Qty</TableCell>
                                    <TableCell align="right">Unit Cost</TableCell>
                                    <TableCell align="right">Line Total</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {(purchaseRequest.lines ?? []).map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>{line.item_name}</TableCell>
                                        <TableCell align="right">
                                            {line.effective_quantity} {line.unit}
                                        </TableCell>
                                        <TableCell align="right">
                                            {formatMoney(line.effective_unit_cost)}
                                        </TableCell>
                                        <TableCell align="right">
                                            <EmphasizedMoney amount={line.effective_line_total} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <AccountingListTotal
                            label="Effective Total"
                            amount={purchaseRequest.effective_total ?? purchaseRequest.estimated_total}
                        />

                        {canDecide && (
                            <Stack spacing={1}>
                                <TextField
                                    fullWidth
                                    multiline
                                    minRows={2}
                                    label="Review Notes"
                                    value={reviewNotes}
                                    onChange={(e) => setReviewNotes(e.target.value)}
                                />
                                <Button variant="contained" color="success" disabled={isBusy} onClick={handleApprove}>
                                    Approve As-Is
                                </Button>

                                <Typography variant="subtitle2">Amend Lines</Typography>
                                {(purchaseRequest.lines ?? []).map((line) => (
                                    <Stack key={line.id} direction="row" spacing={1}>
                                        <Typography variant="body2" flex={1}>
                                            {line.item_name}
                                        </Typography>
                                        <TextField
                                            size="small"
                                            label="Qty"
                                            type="number"
                                            value={amendedQuantities[line.id] ?? ''}
                                            onChange={(e) =>
                                                setAmendedQuantities((prev) => ({
                                                    ...prev,
                                                    [line.id]: e.target.value,
                                                }))
                                            }
                                            sx={{ width: 90 }}
                                        />
                                        <TextField
                                            size="small"
                                            label="Cost"
                                            type="number"
                                            value={amendedCosts[line.id] ?? ''}
                                            onChange={(e) =>
                                                setAmendedCosts((prev) => ({
                                                    ...prev,
                                                    [line.id]: e.target.value,
                                                }))
                                            }
                                            sx={{ width: 110 }}
                                        />
                                    </Stack>
                                ))}
                                <TextField
                                    fullWidth
                                    label="Amendment Notes"
                                    value={amendmentNotes}
                                    onChange={(e) => setAmendmentNotes(e.target.value)}
                                />
                                <Button variant="outlined" color="warning" disabled={isBusy} onClick={handleAmend}>
                                    Amend & Send Back
                                </Button>
                                <AccountingListTotal label="Amended Total (preview)" amount={amendedEffectiveTotal} />

                                <TextField
                                    fullWidth
                                    multiline
                                    minRows={2}
                                    label="Rejection Reason (min 20 chars)"
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                />
                                <Button
                                    variant="outlined"
                                    color="error"
                                    disabled={isBusy || rejectionReason.length < 20}
                                    onClick={handleReject}
                                >
                                    Reject
                                </Button>
                            </Stack>
                        )}

                        {canFulfill && (
                            <Button variant="contained" onClick={onFulfill}>
                                Record Fulfillment
                            </Button>
                        )}
                    </Stack>
                )}
            </Box>
        </Drawer>
    );
}

/** Finance procurement queue — approve, amend, reject purchase requests. */
export function ProcurementQueuePage() {
    const { canAction } = usePermissions();
    const canApprovePurchases = canAction('approvePurchases');

    const [statusFilter, setStatusFilter] = useState<PurchaseRequestStatus | ''>('submitted');
    const [selected, setSelected] = useState<PurchaseRequest | null>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [fulfillmentOpen, setFulfillmentOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const { data, isLoading, isError } = usePurchaseRequests(statusFilter ? { status: statusFilter } : {});
    const queueItems = (data ?? []).filter((item) => QUEUE_STATUSES.includes(item.status));

    if (!canApprovePurchases) {
        return (
            <Box p={3}>
                <Alert severity="warning">You do not have permission to view the procurement queue.</Alert>
            </Box>
        );
    }

    const openReview = (request: PurchaseRequest) => {
        setSelected(request);
        setDrawerOpen(true);
    };

    const openFulfillment = () => {
        setDrawerOpen(false);
        setFulfillmentOpen(true);
    };

    const requestTotal = (request: PurchaseRequest) =>
        request.effective_total ?? request.estimated_total ?? '0';

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Procurement Queue</Typography>
                <Stack direction="row" spacing={2} alignItems="center">
                    <TextField
                        select
                        size="small"
                        label="Status"
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value as PurchaseRequestStatus | '')}
                        sx={{ minWidth: 200 }}
                    >
                        {STATUS_FILTER_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <ExportButtons
                        endpoint="/purchase-requests/export"
                        filenamePrefix="purchase-requests"
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

            {isError && <Alert severity="error">Unable to load procurement queue. Please try again.</Alert>}

            {!isLoading && !isError && queueItems.length === 0 && (
                <Alert severity="info">No purchase requests in the queue for this filter.</Alert>
            )}

            {!isLoading && !isError && queueItems.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Number</TableCell>
                                    <TableCell>Title</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell align="right">Total Amount</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {queueItems.map((request) => (
                                    <TableRow key={request.id} hover>
                                        <TableCell>{request.request_number}</TableCell>
                                        <TableCell>{request.title ?? '—'}</TableCell>
                                        <TableCell>
                                            <PurchaseRequestStatusBadge status={request.status} />
                                        </TableCell>
                                        <TableCell align="right">
                                            <EmphasizedMoney amount={requestTotal(request)} />
                                        </TableCell>
                                        <TableCell align="right">
                                            <Button size="small" onClick={() => openReview(request)}>
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

            <ProcurementReviewDrawer
                purchaseRequest={selected}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                onFulfill={openFulfillment}
            />

            <FulfillmentDialog
                purchaseRequest={selected}
                open={fulfillmentOpen}
                onClose={() => setFulfillmentOpen(false)}
            />
        </Box>
    );
}
