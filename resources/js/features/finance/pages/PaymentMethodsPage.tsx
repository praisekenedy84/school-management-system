import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    MenuItem,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { Plus, Pencil, Trash2 } from 'lucide-react';
import {
    useCreatePaymentMethod,
    useDeletePaymentMethod,
    usePaymentMethods,
    useUpdatePaymentMethod,
} from '../api/usePaymentMethods';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { ExportButtons } from '../../../components/ExportButtons';
import type { PaymentMethod, PaymentMethodRequest, PaymentMethodType } from '../types/finance';

const TYPE_OPTIONS: { value: PaymentMethodType; label: string }[] = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cash_deposit', label: 'Cash Deposit' },
    { value: 'mobile_money', label: 'Mobile Money' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'direct_cash', label: 'Direct Cash' },
];

function PaymentMethodDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: PaymentMethodRequest;
    onClose: () => void;
    onSubmit: (values: PaymentMethodRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [name, setName] = useState(initialValue.name);
    const [type, setType] = useState(initialValue.type);
    const [bankName, setBankName] = useState(initialValue.bank_name ?? '');
    const [accountNumber, setAccountNumber] = useState(initialValue.account_number ?? '');
    const [accountName, setAccountName] = useState(initialValue.account_name ?? '');
    const [branchCode, setBranchCode] = useState(initialValue.branch_code ?? '');
    const [swiftCode, setSwiftCode] = useState(initialValue.swift_code ?? '');
    const [paymentInstructions, setPaymentInstructions] = useState(initialValue.payment_instructions ?? '');
    const [isActive, setIsActive] = useState(initialValue.is_active);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setType(initialValue.type);
                    setBankName(initialValue.bank_name ?? '');
                    setAccountNumber(initialValue.account_number ?? '');
                    setAccountName(initialValue.account_name ?? '');
                    setBranchCode(initialValue.branch_code ?? '');
                    setSwiftCode(initialValue.swift_code ?? '');
                    setPaymentInstructions(initialValue.payment_instructions ?? '');
                    setIsActive(initialValue.is_active);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Payment Method' : 'New Payment Method'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField fullWidth label="Name" value={name} onChange={(e) => setName(e.target.value)} autoFocus />
                    <TextField
                        select
                        fullWidth
                        label="Type"
                        value={type}
                        onChange={(e) => setType(e.target.value as PaymentMethodType)}
                    >
                        {TYPE_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField fullWidth label="Bank Name" value={bankName} onChange={(e) => setBankName(e.target.value)} />
                    <TextField
                        fullWidth
                        label="Account Number"
                        value={accountNumber}
                        onChange={(e) => setAccountNumber(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        label="Account Name"
                        value={accountName}
                        onChange={(e) => setAccountName(e.target.value)}
                    />
                    <Stack direction="row" spacing={2}>
                        <TextField
                            fullWidth
                            label="Branch Code"
                            value={branchCode}
                            onChange={(e) => setBranchCode(e.target.value)}
                        />
                        <TextField
                            fullWidth
                            label="SWIFT Code"
                            value={swiftCode}
                            onChange={(e) => setSwiftCode(e.target.value)}
                        />
                    </Stack>
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Payment Instructions"
                        value={paymentInstructions}
                        onChange={(e) => setPaymentInstructions(e.target.value)}
                    />
                    <FormControlLabel
                        control={<Checkbox checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />}
                        label="Active"
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!name || !type || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            name,
                            type,
                            bank_name: bankName || null,
                            account_number: accountNumber || null,
                            account_name: accountName || null,
                            branch_code: branchCode || null,
                            swift_code: swiftCode || null,
                            payment_instructions: paymentInstructions || null,
                            is_active: isActive,
                        })
                    }
                >
                    {isSubmitting ? 'Saving…' : 'Save'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

/** Simple CRUD list for payment methods: table + a Dialog for create/edit, mirrors SubjectsPage. */
export function PaymentMethodsPage() {
    const { data, isLoading, isError } = usePaymentMethods();
    const createPaymentMethod = useCreatePaymentMethod();
    const updatePaymentMethod = useUpdatePaymentMethod();
    const deletePaymentMethod = useDeletePaymentMethod();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingPaymentMethod, setEditingPaymentMethod] = useState<PaymentMethod | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingPaymentMethod(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (paymentMethod: PaymentMethod) => {
        setEditingPaymentMethod(paymentMethod);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: PaymentMethodRequest) => {
        setServerError(null);
        try {
            if (editingPaymentMethod) {
                await updatePaymentMethod.mutateAsync({ id: editingPaymentMethod.id, payload: values });
            } else {
                await createPaymentMethod.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save payment method.'));
        }
    };

    const handleDelete = (id: string) => {
        deletePaymentMethod.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Payment Methods</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/payment-methods/export"
                        filenamePrefix="payment-methods"
                        onError={(message) => setExportError(message)}
                    />
                    <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                        New Payment Method
                    </Button>
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load payment methods. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No payment methods have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Type</TableCell>
                                    <TableCell>Bank</TableCell>
                                    <TableCell>Account Number</TableCell>
                                    <TableCell>Active</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((paymentMethod) => (
                                    <TableRow key={paymentMethod.id} hover>
                                        <TableCell>{paymentMethod.name}</TableCell>
                                        <TableCell>{paymentMethod.type}</TableCell>
                                        <TableCell>{paymentMethod.bank_name ?? '—'}</TableCell>
                                        <TableCell>{paymentMethod.account_number ?? '—'}</TableCell>
                                        <TableCell>{paymentMethod.is_active ? 'Yes' : 'No'}</TableCell>
                                        <TableCell align="right">
                                            <IconButton size="small" onClick={() => openEdit(paymentMethod)}>
                                                <Pencil size={16} />
                                            </IconButton>
                                            <IconButton size="small" onClick={() => handleDelete(paymentMethod.id)}>
                                                <Trash2 size={16} />
                                            </IconButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <PaymentMethodDialog
                open={dialogOpen}
                initialValue={{
                    name: editingPaymentMethod?.name ?? '',
                    type: editingPaymentMethod?.type ?? 'bank_transfer',
                    bank_name: editingPaymentMethod?.bank_name ?? '',
                    account_number: editingPaymentMethod?.account_number ?? '',
                    account_name: editingPaymentMethod?.account_name ?? '',
                    branch_code: editingPaymentMethod?.branch_code ?? '',
                    swift_code: editingPaymentMethod?.swift_code ?? '',
                    payment_instructions: editingPaymentMethod?.payment_instructions ?? '',
                    is_active: editingPaymentMethod?.is_active ?? true,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createPaymentMethod.isPending || updatePaymentMethod.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
