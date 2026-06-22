import { Chip } from '@mui/material';
import type { HostelLeaveStatus } from '../types/hostel';

/**
 * Color-coded status chip for a hostel leave request, mirroring
 * finance/components/SlipStatusBadge's MUI-theme-token convention:
 * pending = warning (orange), approved = success (green), rejected = error (red).
 */
const STATUS_COLOR: Record<HostelLeaveStatus, 'warning' | 'success' | 'error'> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
};

const STATUS_LABEL: Record<HostelLeaveStatus, string> = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
};

export function LeaveStatusBadge({ status }: { status: HostelLeaveStatus }) {
    return <Chip size="small" color={STATUS_COLOR[status]} label={STATUS_LABEL[status]} />;
}
