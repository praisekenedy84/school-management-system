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
import { useStoreRequisitions } from '../api/useStoreRequisitions';
import {
    useApproveStoreRequisition,
    useAddRequisitionToPurchase,
    useCloseRequisitionLine,
    useIssueStoreRequisition,
    useRejectStoreRequisition,
} from '../api/useStoreRequisitionMutations';
import { RequisitionStatusBadge } from '../components/RequisitionStatusBadge';
import { ExportButtons } from '../../../components/ExportButtons';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { usePermissions } from '../../../lib/usePermissions';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { StoreRequisition, StoreRequisitionStatus } from '../types/stores';

const QUEUE_STATUSES: StoreRequisitionStatus[] = ['submitted', 'approved', 'partially_issued'];

const STATUS_FILTER_OPTIONS: { value: StoreRequisitionStatus | ''; label: string }[] = [
    { value: '', label: 'All Queue' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'approved', label: 'Approved' },
    { value: 'partially_issued', label: 'Partially Issued' },
];

function RequisitionReviewDrawer({
    requisition,
    open,
    onClose,
}: {
    requisition: StoreRequisition | null;
    open: boolean;
    onClose: () => void;
}) {
    const approve = useApproveStoreRequisition();
    const reject = useRejectStoreRequisition();
    const issue = useIssueStoreRequisition();
    const closeLine = useCloseRequisitionLine();
    const addToPurchase = useAddRequisitionToPurchase();
    const { canAction } = usePermissions();
    const canApproveRequisitions = canAction('approveRequisitions');
    const canIssueRequisitions = canAction('issueRequisitions');
    const canCreatePurchaseRequests = canAction('createPurchaseRequests');

    const [reviewNotes, setReviewNotes] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [issueQuantities, setIssueQuantities] = useState<Record<string, string>>({});
    const [serverError, setServerError] = useState<string | null>(null);

    const resetForm = () => {
        setReviewNotes('');
        setRejectionReason('');
        setIssueQuantities({});
        setServerError(null);
    };

    const handleApprove = async () => {
        if (!requisition) return;
        setServerError(null);
        try {
            await approve.mutateAsync({ id: requisition.id, payload: { review_notes: reviewNotes || null } });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to approve requisition.'));
        }
    };

    const handleReject = async () => {
        if (!requisition) return;
        setServerError(null);
        try {
            await reject.mutateAsync({
                id: requisition.id,
                payload: { rejection_reason: rejectionReason },
            });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to reject requisition.'));
        }
    };

    const handleIssue = async () => {
        if (!requisition) return;
        setServerError(null);
        const lines = Object.entries(issueQuantities)
            .filter(([, qty]) => qty && parseFloat(qty) > 0)
            .map(([line_id, quantity]) => ({ line_id, quantity }));

        if (lines.length === 0) {
            setServerError('Enter a quantity to issue for at least one line.');
            return;
        }

        for (const line of requisition.lines ?? []) {
            const entered = issueQuantities[line.id];
            if (!entered || parseFloat(entered) <= 0) {
                continue;
            }
            if (parseFloat(entered) > parseFloat(line.remaining_quantity)) {
                setServerError(
                    `Cannot issue ${entered} ${line.unit} for ${line.inventory_item?.name ?? 'item'} — only ${line.remaining_quantity} ${line.unit} remaining.`,
                );
                return;
            }
        }

        try {
            await issue.mutateAsync({ id: requisition.id, payload: { lines } });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to issue items.'));
        }
    };

    const handleCloseLine = async (lineId: string) => {
        if (!requisition) return;
        setServerError(null);
        try {
            await closeLine.mutateAsync({ id: requisition.id, payload: { line_id: lineId } });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to close line.'));
        }
    };

    const handleAddToPurchase = async (mode: 'shortfall' | 'all') => {
        if (!requisition) return;
        setServerError(null);
        try {
            await addToPurchase.mutateAsync({ id: requisition.id, payload: { mode } });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to add to purchase list.'));
        }
    };

    const showApproveActions = canApproveRequisitions && requisition?.status === 'submitted';
    const showIssueActions =
        canIssueRequisitions &&
        (requisition?.status === 'approved' || requisition?.status === 'partially_issued');
    const showAddToPurchase =
        canCreatePurchaseRequests &&
        requisition !== null &&
        ['submitted', 'approved', 'partially_issued'].includes(requisition.status);
    const isBusy =
        approve.isPending ||
        reject.isPending ||
        issue.isPending ||
        closeLine.isPending ||
        addToPurchase.isPending;

    return (
        <Drawer
            anchor="right"
            open={open}
            onClose={onClose}
            PaperProps={{ sx: { width: { xs: '100%', sm: 480 } } }}
            TransitionProps={{
                onEnter: resetForm,
            }}
        >
            <Box p={3}>
                <Typography variant="h6" gutterBottom>
                    {requisition?.requisition_number ?? 'Requisition'}
                </Typography>

                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                {requisition && (
                    <Stack spacing={2}>
                        <Typography variant="body2">
                            <strong>Purpose:</strong> {requisition.purpose ?? '—'}
                        </Typography>
                        <Typography variant="body2">
                            <strong>Needed by:</strong> {requisition.needed_by ?? '—'}
                        </Typography>
                        <RequisitionStatusBadge status={requisition.status} />

                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Item</TableCell>
                                    <TableCell align="right">Requested</TableCell>
                                    <TableCell align="right">Issued</TableCell>
                                    <TableCell align="right">Remaining</TableCell>
                                    <TableCell align="right">Est. Value</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {(requisition.lines ?? []).map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            {line.inventory_item?.name ?? line.inventory_item_id}
                                            {line.is_closed && ' (closed)'}
                                        </TableCell>
                                        <TableCell align="right">
                                            {line.requested_quantity} {line.unit}
                                        </TableCell>
                                        <TableCell align="right">
                                            {line.issued_quantity} {line.unit}
                                        </TableCell>
                                        <TableCell align="right">
                                            {line.remaining_quantity} {line.unit}
                                        </TableCell>
                                        <TableCell align="right">
                                            {line.estimated_line_value
                                                ? formatMoney(line.estimated_line_value)
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {requisition.estimated_total && (
                            <AccountingListTotal
                                label="Estimated Total Value"
                                amount={requisition.estimated_total}
                            />
                        )}

                        {(requisition.issue_history ?? []).length > 0 && (
                            <Box>
                                <Typography variant="subtitle2" gutterBottom>
                                    Issue History
                                </Typography>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Item</TableCell>
                                            <TableCell align="right">Quantity</TableCell>
                                            <TableCell>Issued At</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {(requisition.issue_history ?? []).map((event) => (
                                            <TableRow key={event.id}>
                                                <TableCell>
                                                    {event.inventory_item?.name ?? event.inventory_item_id}
                                                </TableCell>
                                                <TableCell align="right">
                                                    {event.quantity} {event.inventory_item?.unit ?? ''}
                                                </TableCell>
                                                <TableCell>{event.performed_at ?? '—'}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </Box>
                        )}

                        {showApproveActions && (
                            <Stack spacing={1}>
                                <TextField
                                    fullWidth
                                    multiline
                                    minRows={2}
                                    label="Review Notes"
                                    value={reviewNotes}
                                    onChange={(e) => setReviewNotes(e.target.value)}
                                />
                                <Stack direction="row" spacing={1}>
                                    <Button variant="contained" color="success" disabled={isBusy} onClick={handleApprove}>
                                        Approve
                                    </Button>
                                    <TextField
                                        fullWidth
                                        multiline
                                        minRows={2}
                                        label="Rejection Reason (min 20 chars)"
                                        value={rejectionReason}
                                        onChange={(e) => setRejectionReason(e.target.value)}
                                    />
                                </Stack>
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

                        {showIssueActions && (
                            <Stack spacing={1}>
                                <Typography variant="subtitle2">Issue Quantities</Typography>
                                {(requisition.lines ?? [])
                                    .filter((line) => !line.is_closed && parseFloat(line.remaining_quantity) > 0)
                                    .map((line) => (
                                        <Stack key={line.id} direction="row" spacing={1} alignItems="center">
                                            <Typography variant="body2" flex={1}>
                                                {line.inventory_item?.name ?? line.inventory_item_id}
                                                <br />
                                                <Typography component="span" variant="caption" color="text.secondary">
                                                    Stock: {line.inventory_item?.current_quantity ?? '—'} · Remaining:{' '}
                                                    {line.remaining_quantity} {line.unit}
                                                </Typography>
                                            </Typography>
                                            <TextField
                                                size="small"
                                                label="Issue"
                                                type="number"
                                                inputProps={{
                                                    min: 0.001,
                                                    max: parseFloat(line.remaining_quantity),
                                                    step: '0.001',
                                                }}
                                                value={issueQuantities[line.id] ?? ''}
                                                onChange={(e) =>
                                                    setIssueQuantities((prev) => ({
                                                        ...prev,
                                                        [line.id]: e.target.value,
                                                    }))
                                                }
                                                sx={{ width: 100 }}
                                            />
                                            <Button size="small" onClick={() => handleCloseLine(line.id)}>
                                                Close
                                            </Button>
                                        </Stack>
                                    ))}
                                <Button variant="contained" disabled={isBusy} onClick={handleIssue}>
                                    Issue Selected
                                </Button>
                            </Stack>
                        )}

                        {showAddToPurchase && (
                            <Stack spacing={1}>
                                <Typography variant="subtitle2">Procurement</Typography>
                                <Typography variant="body2" color="text.secondary">
                                    Add items from this requisition to a draft purchase list for Finance.
                                </Typography>
                                <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                                    <Button
                                        variant="outlined"
                                        disabled={isBusy}
                                        onClick={() => handleAddToPurchase('shortfall')}
                                    >
                                        Add stock shortfall
                                    </Button>
                                    <Button
                                        variant="outlined"
                                        disabled={isBusy}
                                        onClick={() => handleAddToPurchase('all')}
                                    >
                                        Add all remaining
                                    </Button>
                                </Stack>
                            </Stack>
                        )}
                    </Stack>
                )}
            </Box>
        </Drawer>
    );
}

/** Storekeeper queue — approve, reject, partial issue, and close requisition lines. */
export function RequisitionQueuePage() {
    const [statusFilter, setStatusFilter] = useState<StoreRequisitionStatus | ''>('submitted');
    const [selected, setSelected] = useState<StoreRequisition | null>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const { data, isLoading, isError } = useStoreRequisitions(
        statusFilter ? { status: statusFilter } : {},
    );

    const queueItems = (data ?? []).filter((item) => QUEUE_STATUSES.includes(item.status));

    const openReview = (requisition: StoreRequisition) => {
        setSelected(requisition);
        setDrawerOpen(true);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Requisition Queue</Typography>
                <Stack direction="row" spacing={2} alignItems="center">
                    <TextField
                        select
                        size="small"
                        label="Status"
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value as StoreRequisitionStatus | '')}
                        sx={{ minWidth: 200 }}
                    >
                        {STATUS_FILTER_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <ExportButtons
                        endpoint="/store-requisitions/export"
                        filenamePrefix="store-requisitions"
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

            {isError && <Alert severity="error">Unable to load requisition queue. Please try again.</Alert>}

            {!isLoading && !isError && queueItems.length === 0 && (
                <Alert severity="info">No requisitions in the queue for this filter.</Alert>
            )}

            {!isLoading && !isError && queueItems.length > 0 && (
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
                                {queueItems.map((requisition) => (
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
                                            <Button size="small" onClick={() => openReview(requisition)}>
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

            <RequisitionReviewDrawer
                requisition={selected}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
            />
        </Box>
    );
}
