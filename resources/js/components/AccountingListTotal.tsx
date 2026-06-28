import { Box, Stack, TableCell, TableRow, Typography, useTheme } from '@mui/material';
import { alpha } from '@mui/material/styles';
import { formatMoney } from '../lib/formatMoney';

export interface AccountingTotalItem {
    label: string;
    amount: number | string | null | undefined;
    currency?: string;
}

interface AccountingListTotalProps {
    label?: string;
    amount: number | string | null | undefined;
    currency?: string;
    /** Optional second total (e.g. requested vs actual). */
    secondary?: AccountingTotalItem;
}

/** Emphasized total banner for accounting line-item lists. */
export function AccountingListTotal({
    label = 'Total Amount',
    amount,
    currency = 'TZS',
    secondary,
}: AccountingListTotalProps) {
    const theme = useTheme();

    return (
        <Box
            sx={{
                mt: 2,
                px: 2,
                py: 1.5,
                borderRadius: 1,
                border: 1,
                borderColor: 'primary.main',
                bgcolor: alpha(theme.palette.primary.main, 0.08),
            }}
        >
            <Stack
                direction={{ xs: 'column', sm: 'row' }}
                spacing={2}
                justifyContent={secondary ? 'space-between' : 'flex-end'}
                alignItems={{ xs: 'stretch', sm: 'center' }}
            >
                <TotalBlock label={label} amount={amount} currency={currency} />
                {secondary && (
                    <TotalBlock label={secondary.label} amount={secondary.amount} currency={secondary.currency} />
                )}
            </Stack>
        </Box>
    );
}

function TotalBlock({
    label,
    amount,
    currency = 'TZS',
}: {
    label: string;
    amount: number | string | null | undefined;
    currency?: string;
}) {
    return (
        <Stack direction="row" spacing={1.5} alignItems="baseline" justifyContent="flex-end">
            <Typography variant="subtitle2" color="text.secondary">
                {label}
            </Typography>
            <Typography variant="h6" fontWeight={700} color="primary.main">
                {formatMoney(amount, currency)}
            </Typography>
        </Stack>
    );
}

/** Table footer row with emphasized total — use inside TableBody. */
export function AccountingTableTotalRow({
    label = 'Total Amount',
    amount,
    currency = 'TZS',
    colSpan,
}: {
    label?: string;
    amount: number | string | null | undefined;
    currency?: string;
    colSpan: number;
}) {
    const theme = useTheme();

    return (
        <TableRow
            sx={{
                bgcolor: alpha(theme.palette.primary.main, 0.08),
                '& td': { borderTop: 2, borderColor: 'primary.main' },
            }}
        >
            <TableCell colSpan={colSpan} align="right">
                <Typography variant="subtitle2" fontWeight={600}>
                    {label}
                </Typography>
            </TableCell>
            <TableCell align="right">
                <Typography variant="subtitle1" fontWeight={700} color="primary.main">
                    {formatMoney(amount, currency)}
                </Typography>
            </TableCell>
        </TableRow>
    );
}

/** Inline emphasized money for list/table cells. */
export function EmphasizedMoney({
    amount,
    currency = 'TZS',
}: {
    amount: number | string | null | undefined;
    currency?: string;
}) {
    return (
        <Typography component="span" variant="body2" fontWeight={700} color="primary.main">
            {formatMoney(amount, currency)}
        </Typography>
    );
}
