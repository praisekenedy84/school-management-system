import { Chip } from '@mui/material';
import type { StoreRequisitionStatus } from '../types/stores';

const STATUS_CONFIG: Record<
    StoreRequisitionStatus,
    { label: string; color: 'default' | 'info' | 'success' | 'warning' | 'error' }
> = {
    draft: { label: 'Draft', color: 'default' },
    submitted: { label: 'Submitted', color: 'info' },
    approved: { label: 'Approved', color: 'success' },
    partially_issued: { label: 'Partially Issued', color: 'warning' },
    issued: { label: 'Issued', color: 'success' },
    rejected: { label: 'Rejected', color: 'error' },
    cancelled: { label: 'Cancelled', color: 'default' },
};

export function RequisitionStatusBadge({ status }: { status: StoreRequisitionStatus }) {
    const config = STATUS_CONFIG[status];
    return <Chip size="small" color={config.color} label={config.label} />;
}
