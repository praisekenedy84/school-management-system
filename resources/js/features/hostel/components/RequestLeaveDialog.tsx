import { useState } from 'react';
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, MenuItem, Stack, TextField } from '@mui/material';
import { useRequestHostelLeave } from '../api/useHostelLeaveRequests';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { HostelAllocation, RequestLeaveRequest } from '../types/hostel';

/**
 * Leave-request form for a parent: picks one of their ward's allocations
 * (the list passed in is already server-scoped to the parent's own wards —
 * see useHostelAllocations' doc comment) and submits reason/depart/return.
 */
export function RequestLeaveDialog({
    open,
    allocations,
    studentName,
    onClose,
}: {
    open: boolean;
    allocations: HostelAllocation[];
    studentName: (studentId: string) => string;
    onClose: () => void;
}) {
    const [allocationId, setAllocationId] = useState('');
    const [reason, setReason] = useState('');
    const [departAt, setDepartAt] = useState('');
    const [returnAt, setReturnAt] = useState('');
    const [serverError, setServerError] = useState<string | null>(null);

    const requestLeave = useRequestHostelLeave();

    const handleClose = () => {
        setAllocationId('');
        setReason('');
        setDepartAt('');
        setReturnAt('');
        setServerError(null);
        onClose();
    };

    const handleSubmit = async () => {
        setServerError(null);
        const payload: RequestLeaveRequest = {
            hostel_allocation_id: allocationId,
            reason,
            depart_at: departAt,
            return_at: returnAt,
        };
        try {
            await requestLeave.mutateAsync(payload);
            handleClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to submit this leave request.'));
        }
    };

    const canSubmit =
        Boolean(allocationId) && reason.trim().length >= 5 && Boolean(departAt) && Boolean(returnAt) && !requestLeave.isPending;

    return (
        <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
            <DialogTitle>Request Leave</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        select
                        fullWidth
                        label="Child"
                        value={allocationId}
                        onChange={(e) => setAllocationId(e.target.value)}
                    >
                        {allocations.map((allocation) => (
                            <MenuItem key={allocation.id} value={allocation.id}>
                                {studentName(allocation.student_id)}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Reason"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        helperText="At least 5 characters."
                    />
                    <TextField
                        fullWidth
                        type="date"
                        label="Depart"
                        value={departAt}
                        onChange={(e) => setDepartAt(e.target.value)}
                        InputLabelProps={{ shrink: true }}
                    />
                    <TextField
                        fullWidth
                        type="date"
                        label="Return"
                        value={returnAt}
                        onChange={(e) => setReturnAt(e.target.value)}
                        InputLabelProps={{ shrink: true }}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={handleClose}>Cancel</Button>
                <Button variant="contained" disabled={!canSubmit} onClick={handleSubmit}>
                    {requestLeave.isPending ? 'Submitting…' : 'Submit Request'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
