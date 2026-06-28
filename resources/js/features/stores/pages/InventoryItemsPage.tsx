import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    Paper,
    Stack,
    Switch,
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
import { useInventoryItems, useInventoryValuation } from '../api/useInventoryItems';
import {
    useCreateInventoryItem,
    useDeleteInventoryItem,
    useUpdateInventoryItem,
} from '../api/useInventoryItemMutations';
import { ExportButtons } from '../../../components/ExportButtons';
import { AccountingListTotal, EmphasizedMoney } from '../../../components/AccountingListTotal';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import type { InventoryItem, InventoryItemRequest } from '../types/stores';

function InventoryItemDialog({
    open,
    initialValue,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: InventoryItemRequest;
    onClose: () => void;
    onSubmit: (values: InventoryItemRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [name, setName] = useState(initialValue.name);
    const [sku, setSku] = useState(initialValue.sku ?? '');
    const [category, setCategory] = useState(initialValue.category ?? '');
    const [unit, setUnit] = useState(initialValue.unit);
    const [reorderLevel, setReorderLevel] = useState(String(initialValue.reorder_level ?? '0'));
    const [unitCost, setUnitCost] = useState(String(initialValue.unit_cost ?? '0'));
    const [notes, setNotes] = useState(initialValue.notes ?? '');
    const [isActive, setIsActive] = useState(initialValue.is_active ?? true);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setName(initialValue.name);
                    setSku(initialValue.sku ?? '');
                    setCategory(initialValue.category ?? '');
                    setUnit(initialValue.unit);
                    setReorderLevel(String(initialValue.reorder_level ?? '0'));
                    setUnitCost(String(initialValue.unit_cost ?? '0'));
                    setNotes(initialValue.notes ?? '');
                    setIsActive(initialValue.is_active ?? true);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Item' : 'New Inventory Item'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField fullWidth label="Name" value={name} onChange={(e) => setName(e.target.value)} autoFocus />
                    <TextField
                        fullWidth
                        label="SKU"
                        value={sku}
                        onChange={(e) => setSku(e.target.value)}
                        helperText={initialValue.name ? undefined : 'Leave blank to auto-generate (SKU-YYYYMMDD-NNNN)'}
                    />
                    <TextField
                        fullWidth
                        label="Category"
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                    />
                    <TextField fullWidth label="Unit" value={unit} onChange={(e) => setUnit(e.target.value)} required />
                    <TextField
                        fullWidth
                        label="Reorder Level"
                        type="number"
                        inputProps={{ min: 0, step: '0.001' }}
                        value={reorderLevel}
                        onChange={(e) => setReorderLevel(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        label="Unit Cost (TZS)"
                        type="number"
                        inputProps={{ min: 0, step: '0.01' }}
                        value={unitCost}
                        onChange={(e) => setUnitCost(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Notes"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                    />
                    <FormControlLabel
                        control={<Switch checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />}
                        label="Active"
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    disabled={!name || !unit || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            name,
                            sku: sku || null,
                            category: category || null,
                            unit,
                            reorder_level: reorderLevel,
                            unit_cost: unitCost,
                            notes: notes || null,
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

/** Catalog CRUD for storekeepers — inventory items with reorder levels and unit costs. */
export function InventoryItemsPage() {
    const { user, canAction } = usePermissions();
    const canManage = canAction('manageInventoryCatalog');
    const { data, isLoading, isError } = useInventoryItems({ active_only: false });
    const { data: valuation } = useInventoryValuation();
    const createItem = useCreateInventoryItem();
    const updateItem = useUpdateInventoryItem();
    const deleteItem = useDeleteInventoryItem();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<InventoryItem | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const openCreate = () => {
        setEditingItem(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (item: InventoryItem) => {
        setEditingItem(item);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: InventoryItemRequest) => {
        setServerError(null);
        try {
            if (editingItem) {
                await updateItem.mutateAsync({ id: editingItem.id, payload: values });
            } else {
                await createItem.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save inventory item.'));
        }
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Inventory Catalog</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/inventory-items/export"
                        filenamePrefix="inventory-items"
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Item
                        </Button>
                    )}
                </Stack>
            </Stack>

            {valuation && (
                <AccountingListTotal
                    label={`Total Stock Value (${valuation.item_count} items)`}
                    amount={valuation.total_valuation}
                    currency={valuation.currency}
                />
            )}

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

            {isError && <Alert severity="error">Unable to load inventory items. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No inventory items have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>SKU</TableCell>
                                    <TableCell>Category</TableCell>
                                    <TableCell>Unit</TableCell>
                                    <TableCell align="right">Quantity</TableCell>
                                    <TableCell align="right">Reorder Level</TableCell>
                                    <TableCell align="right">Unit Cost</TableCell>
                                    <TableCell align="right">Line Value</TableCell>
                                    <TableCell>Status</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((item) => (
                                    <TableRow key={item.id} hover>
                                        <TableCell>
                                            {item.name}
                                            {item.is_low_stock && (
                                                <Chip size="small" color="warning" label="Low" sx={{ ml: 1 }} />
                                            )}
                                        </TableCell>
                                        <TableCell>{item.sku ?? '—'}</TableCell>
                                        <TableCell>{item.category ?? '—'}</TableCell>
                                        <TableCell>{item.unit}</TableCell>
                                        <TableCell align="right">{item.current_quantity}</TableCell>
                                        <TableCell align="right">{item.reorder_level}</TableCell>
                                        <TableCell align="right">
                                            {formatMoney(item.unit_cost, item.currency)}
                                        </TableCell>
                                        <TableCell align="right">
                                            <EmphasizedMoney amount={item.line_value} currency={item.currency} />
                                        </TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={item.is_active ? 'success' : 'default'}
                                                label={item.is_active ? 'Active' : 'Inactive'}
                                            />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(item)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => deleteItem.mutate(item.id)}>
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

            <InventoryItemDialog
                open={dialogOpen}
                initialValue={{
                    name: editingItem?.name ?? '',
                    sku: editingItem?.sku,
                    category: editingItem?.category,
                    unit: editingItem?.unit ?? '',
                    reorder_level: editingItem?.reorder_level ?? '0',
                    unit_cost: editingItem?.unit_cost ?? '0',
                    notes: editingItem?.notes,
                    is_active: editingItem?.is_active ?? true,
                }}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createItem.isPending || updateItem.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
