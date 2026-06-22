import { Chip } from '@mui/material';
import type { PaymentSlipStatus } from '../types/finance';

/**
 * Color-coded status chip for a payment slip's lifecycle status. Colors are
 * MUI theme palette keys, not ad-hoc hex values (RULES §8: theme tokens only).
 */
const STATUS_COLOR: Record<PaymentSlipStatus, 'default' | 'success' | 'error' | 'warning' | 'info'> = {
    pending: 'default',
    under_review: 'info',
    verified: 'success',
    approved: 'success',
    rejected: 'error',
    disputed: 'error',
    clarification_needed: 'warning',
};

const STATUS_LABEL: Record<PaymentSlipStatus, string> = {
    pending: 'Pending',
    under_review: 'Under Review',
    verified: 'Verified',
    approved: 'Approved',
    rejected: 'Rejected',
    disputed: 'Disputed',
    clarification_needed: 'Clarification Needed',
};

export function SlipStatusBadge({ status }: { status: PaymentSlipStatus }) {
    return <Chip size="small" color={STATUS_COLOR[status]} label={STATUS_LABEL[status]} />;
}
