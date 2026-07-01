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
import { useFeeStatement } from '../api/useFeeStatement';
import { formatMoney } from '../../../lib/formatMoney';
import type { AllocationLine } from '../types/finance';

function AllocationLineRow({
    line,
    index,
    studentId,
    selectedFeeTypes,
    onUpdate,
    onRemove,
}: {
    line: AllocationLine;
    index: number;
    studentId: string;
    selectedFeeTypes: string[];
    onUpdate: (index: number, patch: Partial<AllocationLine>) => void;
    onRemove: (index: number) => void;
}) {
    const { data: sessions } = useAcademicSessions();
    const { data: feeStatement } = useFeeStatement(
        studentId,
        line.academic_session_id || undefined,
    );

    const sessionSelected = Boolean(line.academic_session_id);
    const options = feeStatement?.lines ?? [];

    return (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems="flex-start">
            <TextField
                select
                label="Academic Session"
                value={line.academic_session_id}
                onChange={(e) => onUpdate(index, { academic_session_id: e.target.value, fee_type: '' })}
                sx={{ flex: 2 }}
            >
                {(sessions ?? []).map((session) => (
                    <MenuItem key={session.id} value={session.id}>
                        {session.name}
                    </MenuItem>
                ))}
            </TextField>
            <TextField
                select
                label="Fee Type"
                value={line.fee_type}
                onChange={(e) => onUpdate(index, { fee_type: e.target.value })}
                disabled={!sessionSelected}
                helperText={!sessionSelected ? 'Select an academic session first' : undefined}
                sx={{ flex: 2 }}
            >
                {options
                    .filter(
                        (option) =>
                            option.fee_type === line.fee_type || !selectedFeeTypes.includes(option.fee_type),
                    )
                    .map((option) => (
                        <MenuItem key={option.fee_type} value={option.fee_type}>
                            {option.fee_type} — Balance: {formatMoney(option.balance)}
                        </MenuItem>
                    ))}
            </TextField>
            <TextField
                label="Amount"
                type="number"
                value={line.amount || ''}
                onChange={(e) => onUpdate(index, { amount: Number(e.target.value) || 0 })}
                sx={{ flex: 1 }}
                inputProps={{ min: 0, step: '0.01' }}
            />
            <IconButton onClick={() => onRemove(index)} aria-label="Remove allocation line">
                <Trash2 size={16} />
            </IconButton>
        </Stack>
    );
}

/**
 * Dynamic add/remove rows editor for a payment slip's `allocation[]`. Fee
 * types are chosen from the student's assigned fee structure (not free text).
 * Ensures allocations sum to the total as a CLIENT HINT ONLY — the API
 * re-validates with AllocationSumMatchesTotal (RULES §8: UX only).
 */
export function AllocationEditor({
    studentId,
    lines,
    totalAmount,
    totalTouched,
    onChange,
}: {
    studentId: string;
    lines: AllocationLine[];
    totalAmount: number;
    totalTouched: boolean;
    onChange: (lines: AllocationLine[]) => void;
}) {
    const { data: sessions } = useAcademicSessions();
    const defaultSessionId = sessions?.find((s) => s.is_current)?.id ?? sessions?.[0]?.id ?? '';

    const sum = lines.reduce((acc, line) => acc + (Number(line.amount) || 0), 0);
    const total = Number(totalAmount) || 0;
    const matches = Math.abs(sum - total) < 0.01;
    const hasZeroLines = lines.some((line) => Number(line.amount) === 0);
    const hasAllocationAmount = lines.some((line) => Number(line.amount) > 0);
    const showValidation = totalTouched && total > 0 && hasAllocationAmount;

    const selectedFeeTypes = lines.map((line) => line.fee_type).filter(Boolean);

    const updateLine = (index: number, patch: Partial<AllocationLine>) => {
        const next = lines.map((line, i) => (i === index ? { ...line, ...patch } : line));
        onChange(next);
    };

    const addLine = () => {
        onChange([
            ...lines,
            { fee_type: '', amount: 0, academic_session_id: defaultSessionId },
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
                    <AllocationLineRow
                        key={index}
                        line={line}
                        index={index}
                        studentId={studentId}
                        selectedFeeTypes={selectedFeeTypes}
                        onUpdate={updateLine}
                        onRemove={removeLine}
                    />
                ))}
            </Stack>

            <Button size="small" startIcon={<Plus size={18} />} onClick={addLine} sx={{ mt: 1 }}>
                Add Allocation Line
            </Button>

            {showValidation && (
                <Alert severity={matches && !hasZeroLines ? 'success' : 'warning'} sx={{ mt: 2 }}>
                    Allocated {formatMoney(sum)} of {formatMoney(totalAmount)}
                    {!matches &&
                        ` — the amounts allocated across fee types (${formatMoney(sum)}) do not match the total you entered (${formatMoney(totalAmount)}). Please adjust the split before submitting.`}
                    {hasZeroLines &&
                        ' — each allocation line must have a positive amount; remove or fill zero-value lines.'}
                </Alert>
            )}
        </Box>
    );
}
