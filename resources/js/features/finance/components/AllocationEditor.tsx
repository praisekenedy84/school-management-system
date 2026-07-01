import {
    Alert,
    Box,
    Button,
    IconButton,
    MenuItem,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { Plus, Trash2 } from 'lucide-react';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { formatMoney } from '../../../lib/formatMoney';
import type { AllocationLine } from '../types/finance';

/**
 * Dynamic add/remove rows editor for a payment slip's `allocation[]`. Ensures
 * allocations sum to the total as a CLIENT HINT ONLY — the API re-validates
 * with AllocationSumMatchesTotal and is the source of truth (FRONTEND.md
 * example structure / RULES §8: hiding/validating in UI is UX only).
 */
export function AllocationEditor({
    lines,
    totalAmount,
    onChange,
}: {
    lines: AllocationLine[];
    totalAmount: number;
    onChange: (lines: AllocationLine[]) => void;
}) {
    const { data: sessions } = useAcademicSessions();

    const sum = lines.reduce((acc, line) => acc + (Number(line.amount) || 0), 0);
    const total = Number(totalAmount) || 0;
    const matches = Math.abs(sum - total) < 0.01;
    const hasZeroLines = lines.some((line) => Number(line.amount) === 0);

    const updateLine = (index: number, patch: Partial<AllocationLine>) => {
        const next = lines.map((line, i) => (i === index ? { ...line, ...patch } : line));
        onChange(next);
    };

    const addLine = () => {
        onChange([
            ...lines,
            { fee_type: '', amount: 0, academic_session_id: sessions?.[0]?.id ?? '' },
        ]);
    };

    const removeLine = (index: number) => {
        onChange(lines.filter((_, i) => i !== index));
    };

    return (
        <Box>
            <Typography variant="subtitle2" gutterBottom>
                Fee Allocation
            </Typography>

            <Stack spacing={1.5}>
                {lines.map((line, index) => (
                    <Stack key={index} direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems="flex-start">
                        <TextField
                            label="Fee Type"
                            value={line.fee_type}
                            onChange={(e) => updateLine(index, { fee_type: e.target.value })}
                            sx={{ flex: 2 }}
                        />
                        <TextField
                            label="Amount"
                            type="number"
                            value={line.amount}
                            onChange={(e) => updateLine(index, { amount: Number(e.target.value) })}
                            sx={{ flex: 1 }}
                        />
                        <TextField
                            select
                            label="Academic Session"
                            value={line.academic_session_id}
                            onChange={(e) => updateLine(index, { academic_session_id: e.target.value })}
                            sx={{ flex: 2 }}
                        >
                            {(sessions ?? []).map((session) => (
                                <MenuItem key={session.id} value={session.id}>
                                    {session.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <IconButton onClick={() => removeLine(index)} aria-label="Remove allocation line">
                            <Trash2 size={16} />
                        </IconButton>
                    </Stack>
                ))}
            </Stack>

            <Button size="small" startIcon={<Plus size={18} />} onClick={addLine} sx={{ mt: 1 }}>
                Add Allocation Line
            </Button>

            <Alert severity={matches && !hasZeroLines ? 'success' : 'warning'} sx={{ mt: 2 }}>
                Allocated {formatMoney(sum)} of {formatMoney(totalAmount)}
                {!matches &&
                    ` — the amounts allocated across fee types (${formatMoney(sum)}) do not match the total you entered (${formatMoney(totalAmount)}). Please adjust the split before submitting.`}
                {hasZeroLines && ' — each allocation line must have a positive amount; remove or fill zero-value lines.'}
            </Alert>
        </Box>
    );
}
