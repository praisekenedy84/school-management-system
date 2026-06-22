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
    MenuItem,
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
import { useHostels } from '../api/useHostels';
import { useCreateMealPlan, useDeleteMealPlan, useMealPlans, useUpdateMealPlan } from '../api/useMealPlans';
import { formatMoney } from '../../../lib/formatMoney';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { useAuth } from '../../../app/AuthProvider';
import { HOSTEL_STAFF_ROLES } from '../../../routes/RequireHostelStaff';
import { ExportButtons } from '../../../components/ExportButtons';
import type { MealPlan, MealPlanRequest } from '../types/hostel';

function MealPlanDialog({
    open,
    initialValue,
    hostelOptions,
    onClose,
    onSubmit,
    isSubmitting,
    serverError,
}: {
    open: boolean;
    initialValue: MealPlanRequest;
    hostelOptions: { id: string; name: string }[];
    onClose: () => void;
    onSubmit: (values: MealPlanRequest) => void;
    isSubmitting: boolean;
    serverError: string | null;
}) {
    const [hostelId, setHostelId] = useState(initialValue.hostel_id);
    const [name, setName] = useState(initialValue.name);
    const [description, setDescription] = useState(initialValue.description ?? '');
    const [price, setPrice] = useState(initialValue.price != null ? String(initialValue.price) : '');
    const [isActive, setIsActive] = useState(initialValue.is_active ?? true);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            TransitionProps={{
                onEnter: () => {
                    setHostelId(initialValue.hostel_id);
                    setName(initialValue.name);
                    setDescription(initialValue.description ?? '');
                    setPrice(initialValue.price != null ? String(initialValue.price) : '');
                    setIsActive(initialValue.is_active ?? true);
                },
            }}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>{initialValue.name ? 'Edit Meal Plan' : 'New Meal Plan'}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        select
                        fullWidth
                        label="Hostel"
                        value={hostelId}
                        onChange={(e) => setHostelId(e.target.value)}
                    >
                        {hostelOptions.map((hostel) => (
                            <MenuItem key={hostel.id} value={hostel.id}>
                                {hostel.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        fullWidth
                        label="Name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        autoFocus
                    />
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        label="Description"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                    />
                    <TextField
                        fullWidth
                        type="number"
                        label="Price"
                        value={price}
                        onChange={(e) => setPrice(e.target.value)}
                        inputProps={{ min: 0, step: '0.01' }}
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
                    disabled={!hostelId || !name || isSubmitting}
                    onClick={() =>
                        onSubmit({
                            hostel_id: hostelId,
                            name,
                            description: description || null,
                            price: price ? Number(price) : null,
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

/** CRUD list for hostel meal plans, filterable by hostel, gated to hostel staff (RULES.md §5). */
export function MealPlansPage() {
    const { user } = useAuth();
    const canManage = Boolean(user?.roles.some((role) => HOSTEL_STAFF_ROLES.includes(role)));

    const [hostelFilter, setHostelFilter] = useState<string>('');
    const { data: hostels } = useHostels();
    const { data, isLoading, isError } = useMealPlans({ hostel_id: hostelFilter || undefined });
    const createMealPlan = useCreateMealPlan();
    const updateMealPlan = useUpdateMealPlan();
    const deleteMealPlan = useDeleteMealPlan();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingPlan, setEditingPlan] = useState<MealPlan | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    const hostelOptions = hostels ?? [];
    const hostelName = (id: string) => hostelOptions.find((h) => h.id === id)?.name ?? '—';

    const openCreate = () => {
        setEditingPlan(null);
        setServerError(null);
        setDialogOpen(true);
    };

    const openEdit = (plan: MealPlan) => {
        setEditingPlan(plan);
        setServerError(null);
        setDialogOpen(true);
    };

    const handleSubmit = async (values: MealPlanRequest) => {
        setServerError(null);
        try {
            if (editingPlan) {
                await updateMealPlan.mutateAsync({ id: editingPlan.id, payload: values });
            } else {
                await createMealPlan.mutateAsync(values);
            }
            setDialogOpen(false);
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save meal plan.'));
        }
    };

    const handleDelete = (id: string) => {
        deleteMealPlan.mutate(id);
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Meal Plans</Typography>
                <Stack direction="row" spacing={2}>
                    <ExportButtons
                        endpoint="/meal-plans/export"
                        filenamePrefix="meal-plans"
                        params={hostelFilter ? { hostel_id: hostelFilter } : undefined}
                        onError={(message) => setExportError(message)}
                    />
                    {canManage && (
                        <Button variant="contained" startIcon={<Plus size={18} />} onClick={openCreate}>
                            New Meal Plan
                        </Button>
                    )}
                </Stack>
            </Stack>

            {exportError && (
                <Alert severity="error" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
                    {exportError}
                </Alert>
            )}

            <TextField
                select
                size="small"
                label="Filter by Hostel"
                value={hostelFilter}
                onChange={(e) => setHostelFilter(e.target.value)}
                sx={{ minWidth: 240, mb: 2 }}
            >
                <MenuItem value="">All Hostels</MenuItem>
                {hostelOptions.map((hostel) => (
                    <MenuItem key={hostel.id} value={hostel.id}>
                        {hostel.name}
                    </MenuItem>
                ))}
            </TextField>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load meal plans. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No meal plans have been created yet.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Hostel</TableCell>
                                    <TableCell>Price</TableCell>
                                    <TableCell>Status</TableCell>
                                    {canManage && <TableCell align="right">Actions</TableCell>}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((plan) => (
                                    <TableRow key={plan.id} hover>
                                        <TableCell>{plan.name}</TableCell>
                                        <TableCell>{hostelName(plan.hostel_id)}</TableCell>
                                        <TableCell>{formatMoney(plan.price)}</TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                color={plan.is_active ? 'success' : 'default'}
                                                label={plan.is_active ? 'Active' : 'Inactive'}
                                            />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell align="right">
                                                <IconButton size="small" onClick={() => openEdit(plan)}>
                                                    <Pencil size={16} />
                                                </IconButton>
                                                <IconButton size="small" onClick={() => handleDelete(plan.id)}>
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

            <MealPlanDialog
                open={dialogOpen}
                initialValue={{
                    hostel_id: editingPlan?.hostel_id ?? hostelFilter ?? '',
                    name: editingPlan?.name ?? '',
                    description: editingPlan?.description ?? '',
                    price: editingPlan?.price ?? null,
                    is_active: editingPlan?.is_active ?? true,
                }}
                hostelOptions={hostelOptions}
                onClose={() => setDialogOpen(false)}
                onSubmit={handleSubmit}
                isSubmitting={createMealPlan.isPending || updateMealPlan.isPending}
                serverError={serverError}
            />
        </Box>
    );
}
