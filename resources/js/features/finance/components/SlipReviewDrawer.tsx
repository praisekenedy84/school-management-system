import { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Divider,
    Drawer,
    MenuItem,
    Stack,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { useVerifyPaymentSlip } from '../api/useVerifyPaymentSlip';
import { useRejectPaymentSlip } from '../api/useRejectPaymentSlip';
import { AccountingListTotal } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { PaymentSlip, RejectionCategory } from '../types/finance';

const REJECTION_CATEGORIES: { value: RejectionCategory; label: string }[] = [
    { value: 'incorrect_amount', label: 'Incorrect Amount' },
    { value: 'unclear_image', label: 'Unclear Image' },
    { value: 'wrong_details', label: 'Wrong Details' },
    { value: 'duplicate', label: 'Duplicate' },
    { value: 'other', label: 'Other' },
];

/**
 * Review drawer for the finance verification queue: full slip detail +
 * attachment links + a tabbed verify/reject form. Both actions share the
 * `verify` ability server-side (PaymentSlipPolicy::verify).
 */
export function SlipReviewDrawer({
    slip,
    open,
    onClose,
}: {
    slip: PaymentSlip | null;
    open: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<'verify' | 'reject'>('verify');
    const [verificationNotes, setVerificationNotes] = useState('');
    const [rejectionCategory, setRejectionCategory] = useState<RejectionCategory>('incorrect_amount');
    const [rejectionReason, setRejectionReason] = useState('');
    const [rejectionReasonError, setRejectionReasonError] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const verifySlip = useVerifyPaymentSlip();
    const rejectSlip = useRejectPaymentSlip();

    useEffect(() => {
        setTab('verify');
        setVerificationNotes('');
        setRejectionCategory('incorrect_amount');
        setRejectionReason('');
        setRejectionReasonError(null);
        setServerError(null);
    }, [slip?.id]);

    if (!slip) {
        return null;
    }

    const handleVerify = async () => {
        setServerError(null);
        try {
            await verifySlip.mutateAsync({ id: slip.id, payload: { verification_notes: verificationNotes } });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to verify this slip.'));
        }
    };

    const handleReject = async () => {
        setServerError(null);
        setRejectionReasonError(null);

        if (rejectionReason.trim().length < 20) {
            setRejectionReasonError(
                'A rejection reason is required. Please explain why this slip was rejected so the parent can resubmit correctly.',
            );
            return;
        }

        try {
            await rejectSlip.mutateAsync({
                id: slip.id,
                payload: { rejection_category: rejectionCategory, rejection_reason: rejectionReason },
            });
            onClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to reject this slip.'));
        }
    };

    const canVerify = verificationNotes.trim().length >= 10 && !verifySlip.isPending;
    const canReject = rejectionReason.trim().length >= 20 && !rejectSlip.isPending;

    return (
        <Drawer anchor="right" open={open} onClose={onClose}>
            <Box sx={{ width: 480, p: 3 }}>
                <Typography variant="h6" gutterBottom>
                    {slip.slip_number}
                </Typography>
                <Typography variant="body2" color="text.secondary" gutterBottom>
                    {slip.student_name ?? 'Unknown student'}
                </Typography>

                <Divider sx={{ my: 2 }} />

                <Stack spacing={1} mb={2}>
                    <Typography variant="body2">
                        <strong>Depositor:</strong> {slip.depositor_name}
                    </Typography>
                    <Typography variant="body2">
                        <strong>Deposit Date:</strong> {slip.deposit_date ?? '—'}
                    </Typography>
                </Stack>

                <AccountingListTotal label="Total Amount" amount={slip.total_amount} currency={slip.currency} />

                <Stack spacing={1} mb={2}>
                    <Typography variant="body2">
                        <strong>Bank:</strong> {slip.bank_name ?? '—'} ({slip.branch_name ?? '—'})
                    </Typography>
                    <Typography variant="body2">
                        <strong>Teller Number:</strong> {slip.teller_number ?? '—'}
                    </Typography>
                </Stack>

                <Typography variant="subtitle2" gutterBottom>
                    Allocation
                </Typography>
                <Stack spacing={0.5} mb={2}>
                    {slip.allocation.map((line, index) => (
                        <Stack key={index} direction="row" justifyContent="space-between">
                            <Typography variant="body2">{line.fee_type}</Typography>
                            <Typography variant="body2">{formatMoney(line.amount, slip.currency)}</Typography>
                        </Stack>
                    ))}
                </Stack>

                <Typography variant="subtitle2" gutterBottom>
                    Attachments
                </Typography>
                <Stack spacing={0.5} mb={2}>
                    {slip.slip_attachments.length === 0 && (
                        <Typography variant="body2" color="text.secondary">
                            No attachments.
                        </Typography>
                    )}
                    {slip.slip_attachments.map((attachment, index) => (
                        <Button
                            key={index}
                            size="small"
                            href={attachment.file_path}
                            target="_blank"
                            rel="noopener noreferrer"
                            sx={{ justifyContent: 'flex-start' }}
                        >
                            {attachment.file_name}
                        </Button>
                    ))}
                </Stack>

                <Divider sx={{ my: 2 }} />

                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                <Tabs value={tab} onChange={(_, value) => setTab(value)} sx={{ mb: 2 }}>
                    <Tab value="verify" label="Verify" />
                    <Tab value="reject" label="Reject" />
                </Tabs>

                {tab === 'verify' && (
                    <Stack spacing={2}>
                        <TextField
                            fullWidth
                            multiline
                            minRows={3}
                            label="Verification Notes"
                            value={verificationNotes}
                            onChange={(e) => setVerificationNotes(e.target.value)}
                            helperText="At least 10 characters."
                        />
                        <Button variant="contained" color="success" disabled={!canVerify} onClick={handleVerify}>
                            {verifySlip.isPending ? 'Verifying…' : 'Verify & Issue Receipt'}
                        </Button>
                    </Stack>
                )}

                {tab === 'reject' && (
                    <Stack spacing={2}>
                        <TextField
                            select
                            fullWidth
                            label="Rejection Category"
                            value={rejectionCategory}
                            onChange={(e) => setRejectionCategory(e.target.value as RejectionCategory)}
                        >
                            {REJECTION_CATEGORIES.map((category) => (
                                <MenuItem key={category.value} value={category.value}>
                                    {category.label}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            fullWidth
                            multiline
                            minRows={3}
                            label="Rejection Reason"
                            value={rejectionReason}
                            onChange={(e) => {
                                setRejectionReason(e.target.value);
                                if (rejectionReasonError) {
                                    setRejectionReasonError(null);
                                }
                            }}
                            error={Boolean(rejectionReasonError)}
                            helperText={rejectionReasonError ?? 'At least 20 characters.'}
                        />
                        <Button variant="contained" color="error" disabled={!canReject} onClick={handleReject}>
                            {rejectSlip.isPending ? 'Rejecting…' : 'Reject Slip'}
                        </Button>
                    </Stack>
                )}
            </Box>
        </Drawer>
    );
}
