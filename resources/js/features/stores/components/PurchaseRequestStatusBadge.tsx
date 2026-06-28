import { Chip } from '@mui/material';
import type { PurchaseRequestStatus } from '../types/stores';

const STATUS_CONFIG: Record<
    PurchaseRequestStatus,
    { label: string; color: 'default' | 'info' | 'success' | 'warning' | 'error' }
> = {
    draft: { label: 'Draft', color: 'default' },
    submitted: { label: 'Submitted', color: 'info' },
    under_review: { label: 'Under Review', color: 'info' },
    approved: { label: 'Approved', color: 'success' },
    amended: { label: 'Amended', color: 'warning' },
    rejected: { label: 'Rejected', color: 'error' },
    fulfilled: { label: 'Fulfilled', color: 'success' },
    cancelled: { label: 'Cancelled', color: 'default' },
};

export function PurchaseRequestStatusBadge({ status }: { status: PurchaseRequestStatus }) {
    const config = STATUS_CONFIG[status];
    return <Chip size="small" color={config.color} label={config.label} />;
}
