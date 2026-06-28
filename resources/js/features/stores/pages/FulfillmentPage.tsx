import { useState } from 'react';
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
import { usePermissions } from '../../../lib/usePermissions';
import { usePurchaseRequests } from '../api/usePurchaseRequests';
import { FulfillmentDialog } from '../components/FulfillmentDialog';
import { PurchaseRequestStatusBadge } from '../components/PurchaseRequestStatusBadge';
import { EmphasizedMoney } from '../../../components/AccountingListTotal';
import type { PurchaseRequest } from '../types/stores';

const FULFILLABLE_STATUSES = ['approved', 'amended'] as const;

/** Finance page listing approved/amended purchase requests ready for fulfillment. */
export function FulfillmentPage() {
    const { canAction } = usePermissions();
    const canFulfill = canAction('fulfillPurchases');

    const { data, isLoading, isError } = usePurchaseRequests();
    const [selected, setSelected] = useState<PurchaseRequest | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    const fulfillable = (data ?? []).filter((request) =>
        FULFILLABLE_STATUSES.includes(request.status as (typeof FULFILLABLE_STATUSES)[number]),
    );

    if (!canFulfill) {
        return (
            <Box p={3}>
                <Alert severity="warning">You do not have permission to record fulfillments.</Alert>
            </Box>
        );
    }

    const openFulfillment = (request: PurchaseRequest) => {
        setSelected(request);
        setDialogOpen(true);
    };

    const requestTotal = (request: PurchaseRequest) =>
        request.effective_total ?? request.estimated_total ?? '0';

    return (
        <Box>
            <Typography variant="h5" mb={2}>
                Purchase Fulfillment
            </Typography>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load purchase requests. Please try again.</Alert>}

            {!isLoading && !isError && fulfillable.length === 0 && (
                <Alert severity="info">No approved purchase requests awaiting fulfillment.</Alert>
            )}

            {!isLoading && !isError && fulfillable.length > 0 && (
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
                                {fulfillable.map((request) => (
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
                                            <Button size="small" variant="contained" onClick={() => openFulfillment(request)}>
                                                Fulfill
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <FulfillmentDialog
                purchaseRequest={selected}
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
            />
        </Box>
    );
}
