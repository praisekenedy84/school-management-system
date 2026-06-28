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
import { SearchableSelect } from '../../../components/SearchableSelect';
import { useClasses } from '../../academics/api/useClasses';
import { useAcademicSessions } from '../../academics/api/useAcademicSessions';
import { toNameOptions } from '../../../lib/selectOptions';
import {
    useCreateFeeStructure,
    useDeleteFeeStructure,
    useFeeStructures,
    useUpdateFeeStructure,
} from '../api/useFeeStructures';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { ExportButtons } from '../../../components/ExportButtons';
import type { FeeStructure, FeeStructureRequest } from '../types/finance';

const APPLICABLE_TO_OPTIONS: { value: FeeStructureRequest['applicable_to']; label: string }[] = [
    { value: 'all', label: 'All Students' },
    { value: 'day_only', label: 'Day Students Only' },
    { value: 'boarding_only', label: 'Boarding Students Only' },
];

function FeeStructureDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: FeeStructureRequest;
    onClose: () => void;
    onSubmit: (values: FeeStructureRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const { data: classes, isLoading: classesLoading } = useClasses();
    const { data: sessions, isLoading: sessionsLoading } = useAcademicSessions();

    const [academicSessionId, setAcademicSessionId] = useState(initialValue.academic_session_id);
    const [classId, setClassId] = useState(initialValue.class_id);
    const [feeType, setFeeType] = useState(initialValue.fee_type);
    const [amount, setAmount] = useState(initialValue.amount);
    const [isMandatory, setIsMandatory] = useState(initialValue.is_mandatory);
    const [applicableTo, setApplicableTo] = useState(initialValue.applicable_to);
    const [installmentAllowed, setInstallmentAllowed] = useState(initialValue.installment_allowed);
    const [installmentCount, setInstallmentCount] = useState(initialValue.installment_count ?? 1);
    const [dueDate, setDueDate] = useState(initialValue.due_date ?? '');
    const [isActive, setIsActive] = useState(initialValue.is_active);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setAcademicSessionId(initialValue.academic_session_id);
                    setClassId(initialValue.class_id);
                    setFeeType(initialValue.fee_type);
                    setAmount(initialValue.amount);
                    setIsMandatory(initialValue.is_mandatory);
                    setApplicableTo(initialValue.applicable_to);
                    setInstallmentAllowed(initialValue.installment_allowed);
                    setInstallmentCount(initialValue.installment_count ?? 1);
                    setDueDate(initialValue.due_date ?? '');
                    setIsActive(initialValue.is_active);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.fee_type ? 'Edit Fee Structure' : 'New Fee Structure'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <SearchableSelect
                        label="Academic Session"
                        options={toNameOptions(sessions)}
                        value={academicSessionId}
                        onChange={setAcademicSessionId}
                        loading={sessionsLoading}
                    />
                    <SearchableSelect
                        label="Class"
                        options={toNameOptions(classes, (item) =>
                            item.level ? `Level ${item.level}` : null,
                        )}
                        value={classId}
                        onChange={setClassId}
                        loading={classesLoading}
                    />
                    <TextField
                        fullWidth
                        label="Fee Type"
                        value={feeType}
                        onChange={(e) => setFeeType(e.target.value)}
                        autoFocus
                    />
                    <TextField
                        fullWidth
                        label="Amount (TZS)"
                        type="number"
                        value={amount}
                        onChange={(e) => setAmount(Number(e.target.value))}
                    />
                    <TextField
                        select
                        fullWidth
                        label="Applicable To"
                        value={applicableTo}
                        onChange={(e) => setApplicableTo(e.target.value as FeeStructureRequest['applicable_to'])}
                    >
                        {APPLICABLE_TO_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <FormControlLabel
                        control={
                            <Checkbox checked={isMandatory} onChange={(e) => setIsMandatory(e.target.checked)} />
                        }
                        label="Mandatory"
                    />
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={installmentAllowed}
                                onChange={(e) => setInstallmentAllowed(e.target.checked)}
                            />
                        }
                        label="Installments Allowed"
                    />
                    {installmentAllowed && (
                        <TextField
                            fullWidth
                            label="Installment Count"
                            type="number"
                            value={installmentCount}
                            onChange={(e) => setInstallmentCount(Number(e.target.value))}
                        />
                    )}
                    <TextField
                        fullWidth
                        label="Due Date"
                        type="date"
                        InputLabelProps={{ shrink: true }}
                        value={dueDate}
                        onChange={(e) => setDueDate(e.target.value)}
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
                    disabled={!academicSessionId || !classId || !feeType || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            academic_session_id: academicSessionId,
                            class_id: classId,
                            fee_type: feeType,
                            amount,
                            is_mandatory: isMandatory,
                            applicable_to: applicableTo,
                            installment_allowed: installmentAllowed,
                            installment_count: installmentAllowed ? installmentCount : null,
                            due_date: dueDate || null,
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

/** Simple CRUD list for fee structures: table + a Dialog for create/edit, mirrors SubjectsPage. */
export function FeeStructuresPage() {
    const { canAction } = usePermissions();
    const canManage = canAction('manageFeeConfig');
    const { data, isLoading, isError } = useFeeStructures();
    const createFeeStructure = useCreateFeeStructure();
    const updateFeeStructure = useUpdateFeeStructure();
    const deleteFeeStructure = useDeleteFeeStructure();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingFeeStructure, setEditingFeeStructure] = useState<FeeStructure | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingFeeStructure(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (feeStructure: FeeStructure) => {
        setEditingFeeStructure(feeStructure);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: FeeStructureRequest) => {
        setServerError(null);
        try {
            if (editingFeeStructure) {
                await updateFeeStructure.mutateAsync({ id: editingFeeStructure.id, payload: values });
            } else {
                await createFeeStructure.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save fee structure.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteFeeStructure.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Fee Structures</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/fee-structures/export"
                        filenamePrefix="fee-structures"
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Fee Structure
                        </Button>
                    )}
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

            {isError && <Alert severity="error">Unable to load fee structures. Please try again.</Alert>}

            {!isLoading && !isError && data && data.data.length === 0 && (
                <Alert severity="info">No fee structures have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Fee Type</TableCell>
                                    <TableCell>Class</TableCell>
                                    <TableCell>Academic Session</TableCell>
                                    <TableCell>Amount</TableCell>
                                    <TableCell>Applicable To</TableCell>
                                    <TableCell>Active</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.data.map((feeStructure) => (
                                    <TableRow key={feeStructure.id} hover>
                                        <TableCell>{feeStructure.fee_type}</TableCell>
                                        <TableCell>{feeStructure.class_name ?? '—'}</TableCell>
                                        <TableCell>{feeStructure.academic_session_name ?? '—'}</TableCell>
                                        <TableCell>{formatMoney(feeStructure.amount)}</TableCell>
                                        <TableCell>{feeStructure.applicable_to}</TableCell>
                                        <TableCell>{feeStructure.is_active ? 'Yes' : 'No'}</TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(feeStructure)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => handleDelete(feeStructure.id)}>
                                                    <Trash2 size={16} />
                                                </IconButton>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <FeeStructureDialog
                open={dialogOpen}
                initialValue={{
                    academic_session_id: editingFeeStructure?.academic_session_id ?? '',
                    class_id: editingFeeStructure?.class_id ?? '',
                    fee_type: editingFeeStructure?.fee_type ?? '',
                    amount: editingFeeStructure?.amount ?? 0,
                    is_mandatory: editingFeeStructure?.is_mandatory ?? true,
                    applicable_to: editingFeeStructure?.applicable_to ?? 'all',
                    installment_allowed: editingFeeStructure?.installment_allowed ?? false,
                    installment_count: editingFeeStructure?.installment_count ?? null,
                    due_date: editingFeeStructure?.due_date ?? null,
                    is_active: editingFeeStructure?.is_active ?? true,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createFeeStructure.isPending || updateFeeStructure.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
