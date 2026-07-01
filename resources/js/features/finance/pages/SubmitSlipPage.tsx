import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    Alert,
    Box,
    Button,
    Divider,
    MenuItem,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { useStudents } from '../../students/api/useStudents';
import { usePaymentMethods } from '../api/usePaymentMethods';
import { useSubmitPaymentSlip } from '../api/usePaymentSlips';
import { AllocationEditor } from '../components/AllocationEditor';
import { FeeStatementPanel } from '../components/FeeStatementPanel';
import { getErrorMessage, getFieldErrors } from '../../../lib/getErrorMessage';
import type { AllocationLine } from '../types/finance';

/**
 * TODO (contract gap): there is no "my wards" endpoint or `?parent_id=`
 * filter on GET /api/v1/students — StudentPolicy::viewAny is currently `true`
 * for every role (Phase 0 placeholder), so this picker lists the WHOLE
 * school's students rather than just the parent's children. The submission
 * endpoint itself correctly restricts a parent to `wards()` server-side
 * (SubmitPaymentSlipRequest::authorize), so a parent picking a non-ward
 * student here gets a 403 on submit — but the picker should be scoped before
 * this ships to parents. Ask api-builder for a `GET /api/v1/me/wards` or a
 * `?mine=1` filter on `/students`.
 */
export function SubmitSlipPage() {
    const navigate = useNavigate();
    const { data: studentsPage, isLoading: studentsLoading } = useStudents(1, 200);
    const { data: methodsPage, isLoading: methodsLoading } = usePaymentMethods();
    const { data: sessions } = useAcademicSessions();
    const submitSlip = useSubmitPaymentSlip();

    const defaultSessionId = sessions?.find((s) => s.is_current)?.id ?? sessions?.[0]?.id ?? '';

    const [studentId, setStudentId] = useState('');
    const [paymentMethodId, setPaymentMethodId] = useState('');
    const [bankName, setBankName] = useState('');
    const [branchName, setBranchName] = useState('');
    const [tellerNumber, setTellerNumber] = useState('');
    const [transactionReference, setTransactionReference] = useState('');
    const [depositorName, setDepositorName] = useState('');
    const [depositDate, setDepositDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [totalAmount, setTotalAmount] = useState<number>(0);
    const [totalTouched, setTotalTouched] = useState(false);
    const [notes, setNotes] = useState('');
    const [allocation, setAllocation] = useState<AllocationLine[]>([
        { fee_type: '', amount: 0, academic_session_id: '' },
    ]);
    const [files, setFiles] = useState<File[]>([]);

    const [serverError, setServerError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

    const statementSessionId = useMemo(() => {
        const fromAllocation = allocation.find((line) => line.academic_session_id)?.academic_session_id;
        return fromAllocation || defaultSessionId;
    }, [allocation, defaultSessionId]);

    const allocationSum = allocation.reduce((acc, line) => acc + (Number(line.amount) || 0), 0);
    const allocationMatches = Math.abs(allocationSum - (Number(totalAmount) || 0)) < 0.01;
    const hasPositiveLinesOnly = allocation.every((line) => Number(line.amount) > 0);
    const hasValidFeeTypes = allocation.every((line) => line.fee_type !== '');

    const canSubmit = Boolean(
        studentId &&
            depositorName &&
            depositDate &&
            totalAmount > 0 &&
            allocationMatches &&
            hasPositiveLinesOnly &&
            hasValidFeeTypes &&
            allocation.length > 0 &&
            files.length > 0 &&
            !submitSlip.isPending,
    );

    const handleFilesChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFiles(Array.from(e.target.files ?? []));
    };

    const handleSubmit = async () => {
        setServerError(null);
        setFieldErrors({});

        const positiveAllocation = allocation.filter((line) => Number(line.amount) > 0);
        if (positiveAllocation.length === 0) {
            setFieldErrors({ allocation: ['At least one allocation line with a positive amount is required.'] });
            return;
        }

        try {
            const slip = await submitSlip.mutateAsync({
                payload: {
                    student_id: studentId,
                    payment_method_id: paymentMethodId || null,
                    bank_name: bankName || null,
                    branch_name: branchName || null,
                    teller_number: tellerNumber || null,
                    transaction_reference: transactionReference || null,
                    depositor_name: depositorName,
                    deposit_date: depositDate,
                    total_amount: totalAmount,
                    currency: 'TZS',
                    allocation: positiveAllocation,
                    notes: notes || null,
                },
                files,
            });
            navigate(`/finance/my-slips/${slip.id}`, { replace: true });
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to submit payment slip. Check the form and try again.'));
            setFieldErrors(getFieldErrors(error));
        }
    };

    return (
        <Box maxWidth={760}>
            <Typography variant="h5" gutterBottom>
                Submit Payment Slip
            </Typography>
            <Typography variant="body2" color="text.secondary" gutterBottom>
                Record evidence of a payment already made outside this system (bank deposit, mobile money,
                or cash). No money is moved here — finance will verify this slip and issue a receipt.
            </Typography>

            <Paper sx={{ p: 3, mt: 2 }}>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                <Stack spacing={2}>
                    <TextField
                        select
                        fullWidth
                        label="Student"
                        value={studentId}
                        onChange={(e) => setStudentId(e.target.value)}
                        disabled={studentsLoading}
                        error={Boolean(fieldErrors.student_id)}
                        helperText={fieldErrors.student_id?.[0]}
                    >
                        {(studentsPage?.data ?? []).map((student) => (
                            <MenuItem key={student.id} value={student.id}>
                                {student.full_name} ({student.admission_number})
                            </MenuItem>
                        ))}
                    </TextField>

                    <TextField
                        select
                        fullWidth
                        label="Payment Method (optional)"
                        value={paymentMethodId}
                        onChange={(e) => setPaymentMethodId(e.target.value)}
                        disabled={methodsLoading}
                    >
                        <MenuItem value="">None</MenuItem>
                        {(methodsPage?.data ?? []).map((method) => (
                            <MenuItem key={method.id} value={method.id}>
                                {method.name}
                            </MenuItem>
                        ))}
                    </TextField>

                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                        <TextField
                            fullWidth
                            label="Bank Name"
                            value={bankName}
                            onChange={(e) => setBankName(e.target.value)}
                            error={Boolean(fieldErrors.bank_name)}
                            helperText={fieldErrors.bank_name?.[0]}
                        />
                        <TextField
                            fullWidth
                            label="Branch Name"
                            value={branchName}
                            onChange={(e) => setBranchName(e.target.value)}
                            error={Boolean(fieldErrors.branch_name)}
                            helperText={fieldErrors.branch_name?.[0]}
                        />
                    </Stack>

                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                        <TextField
                            fullWidth
                            label="Teller Number (optional)"
                            value={tellerNumber}
                            onChange={(e) => setTellerNumber(e.target.value)}
                            error={Boolean(fieldErrors.teller_number)}
                            helperText={fieldErrors.teller_number?.[0] ?? 'Must be unique per bank, per date'}
                        />
                        <TextField
                            fullWidth
                            label="Transaction Reference (optional)"
                            value={transactionReference}
                            onChange={(e) => setTransactionReference(e.target.value)}
                        />
                    </Stack>

                    <TextField
                        fullWidth
                        label="Depositor Name"
                        value={depositorName}
                        onChange={(e) => setDepositorName(e.target.value)}
                        error={Boolean(fieldErrors.depositor_name)}
                        helperText={fieldErrors.depositor_name?.[0]}
                    />

                    <TextField
                        fullWidth
                        label="Deposit Date"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={depositDate}
                        onChange={(e) => setDepositDate(e.target.value)}
                        error={Boolean(fieldErrors.deposit_date)}
                        helperText={fieldErrors.deposit_date?.[0]}
                    />

                    <Divider />

                    <Box>
                        <Typography variant="subtitle2" gutterBottom>
                            Payment Total
                        </Typography>
                        <TextField
                            fullWidth
                            label="Total Amount (TZS)"
                            type="number"
                            value={totalAmount || ''}
                            onChange={(e) => {
                                setTotalTouched(true);
                                setTotalAmount(Number(e.target.value) || 0);
                            }}
                            error={Boolean(fieldErrors.total_amount)}
                            helperText={
                                fieldErrors.total_amount?.[0] ??
                                'Enter the total amount shown on your deposit slip before splitting it across fee types below.'
                            }
                            inputProps={{ min: 0, step: '0.01' }}
                        />
                    </Box>

                    <Divider />

                    {studentId && statementSessionId && (
                        <FeeStatementPanel
                            studentId={studentId}
                            academicSessionId={statementSessionId}
                            title="Outstanding as of today"
                            compact
                        />
                    )}

                    {studentId ? (
                        <AllocationEditor
                            studentId={studentId}
                            lines={allocation}
                            totalAmount={totalAmount}
                            totalTouched={totalTouched}
                            onChange={setAllocation}
                        />
                    ) : (
                        <Alert severity="info">Select a student to allocate the payment across fee types.</Alert>
                    )}

                    {fieldErrors.allocation && (
                        <Alert severity="error">{fieldErrors.allocation[0]}</Alert>
                    )}

                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Notes (optional)"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                    />

                    <Box>
                        <Button variant="outlined" component="label">
                            Upload Slip Image(s) / PDF
                            <input
                                type="file"
                                hidden
                                multiple
                                accept="image/jpeg,image/png,application/pdf"
                                onChange={handleFilesChange}
                            />
                        </Button>
                        {files.length > 0 && (
                            <Typography variant="body2" sx={{ mt: 1 }}>
                                {files.length} file(s) selected: {files.map((f) => f.name).join(', ')}
                            </Typography>
                        )}
                        {fieldErrors.slip_attachments && (
                            <Alert severity="error" sx={{ mt: 1 }}>
                                {fieldErrors.slip_attachments[0]}
                            </Alert>
                        )}
                    </Box>

                    <Stack direction="row" spacing={2} justifyContent="flex-end">
                        <Button onClick={() => navigate('/finance/my-slips')}>Cancel</Button>
                        <Button variant="contained" disabled={!canSubmit} onClick={handleSubmit}>
                            {submitSlip.isPending ? 'Submitting…' : 'Submit Slip'}
                        </Button>
                    </Stack>
                </Stack>
            </Paper>
        </Box>
    );
}
