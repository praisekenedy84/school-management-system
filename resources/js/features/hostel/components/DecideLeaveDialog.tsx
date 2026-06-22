import { useState } from 'react';
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, Stack, TextField, Typography } from '@mui/material';
import { useApproveHostelLeave, useRejectHostelLeave } from '../api/useHostelLeaveRequests';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { HostelLeaveRequest } from '../types/hostel';

/**
 * Small approve/reject dialog for a hostel leave request, analogous to
 * finance's SlipReviewDrawer verify/reject actions but lighter-weight (a
 * single optional `decision_notes` field, not a tabbed form — the backend
 * validation here is just `nullable|string|max:1000`, no minimum length).
 */
export function DecideLeaveDialog({
    leaveRequest,
    decision,
    onClose,
}: {
    leaveRequest: HostelLeaveRequest | null;
    decision: 'approve' | 'reject' | null;
    onClose: () => void;
}) {
    const [decisionNotes, setDecisionNotes] = useState('');
    const [serverError, setServerError] = useState<string | null>(null);

    const approve = useApproveHostelLeave();
    const reject = useRejectHostelLeave();

    const open = Boolean(leaveRequest) && Boolean(decision);
    const isPending = approve.isPending || reject.isPending;

    const handleClose = () => {
        setDecisionNotes('');
        setServerError(null);
        onClose();
    };

    const handleConfirm = async () => {
        if (!leaveRequest || !decision) {
            return;
        }
        setServerError(null);
        try {
            const payload = { decision_notes: decisionNotes || null };
            if (decision === 'approve') {
                await approve.mutateAsync({ id: leaveRequest.id, payload });
            } else {
                await reject.mutateAsync({ id: leaveRequest.id, payload });
            }
            handleClose();
        } catch (error) {
            setServerError(getErrorMessage(error, `Unable to ${decision} this leave request.`));
        }
    };

    return (
        <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
            <DialogTitle>{decision === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                {leaveRequest && (
                    <Typography variant="body2" color="text.secondary" gutterBottom>
                        {leaveRequest.reason}
                    </Typography>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        fullWidth
                        multiline
                        minRows={3}
                        label="Decision Notes (optional)"
                        value={decisionNotes}
                        onChange={(e) => setDecisionNotes(e.target.value)}
                        helperText="Up to 1000 characters."
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={handleClose}>Cancel</Button>
                <Button
                    variant="contained"
                    color={decision === 'approve' ? 'success' : 'error'}
                    disabled={isPending}
                    onClick={handleConfirm}
                >
                    {isPending ? 'Saving…' : decision === 'approve' ? 'Approve' : 'Reject'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
