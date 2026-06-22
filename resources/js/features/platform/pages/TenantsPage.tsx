import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    List,
    ListItemButton,
    ListItemText,
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
import { useNavigate } from 'react-router-dom';
import { Plus, UserCog } from 'lucide-react';
import { useCreateTenant, useStartImpersonation, useTenantUsers, useTenants } from '../api/usePlatform';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { CreateTenantRequest, Tenant } from '../types/platform';

const EMPTY_FORM: CreateTenantRequest = {
    tenant_id: '',
    school_name: '',
    school_code: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
};

function CreateTenantDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [form, setForm] = useState<CreateTenantRequest>(EMPTY_FORM);
    const [serverError, setServerError] = useState<string | null>(null);
    const createTenant = useCreateTenant();

    const set = (field: keyof CreateTenantRequest) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm((prev) => ({ ...prev, [field]: e.target.value }));

    const handleClose = () => {
        setForm(EMPTY_FORM);
        setServerError(null);
        onClose();
    };

    const handleSubmit = async () => {
        setServerError(null);
        try {
            await createTenant.mutateAsync(form);
            handleClose();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to create tenant.'));
        }
    };

    const isValid = Object.values(form).every((value) => value.trim().length > 0);

    return (
        <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
            <DialogTitle>New Tenant</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        fullWidth
                        label="Tenant ID (slug)"
                        helperText="Lowercase letters, numbers, hyphens only — e.g. greenwood-academy"
                        value={form.tenant_id}
                        onChange={set('tenant_id')}
                        autoFocus
                    />
                    <TextField fullWidth label="School Name" value={form.school_name} onChange={set('school_name')} />
                    <TextField fullWidth label="School Code" value={form.school_code} onChange={set('school_code')} />
                    <TextField fullWidth label="Initial Admin Name" value={form.admin_name} onChange={set('admin_name')} />
                    <TextField
                        fullWidth
                        label="Initial Admin Email"
                        type="email"
                        value={form.admin_email}
                        onChange={set('admin_email')}
                    />
                    <TextField
                        fullWidth
                        label="Initial Admin Password"
                        type="password"
                        helperText="Minimum 8 characters — share this with the tenant admin out of band."
                        value={form.admin_password}
                        onChange={set('admin_password')}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={handleClose}>Cancel</Button>
                <Button variant="contained" disabled={!isValid || createTenant.isPending} onClick={handleSubmit}>
                    {createTenant.isPending ? 'Creating…' : 'Create Tenant'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}

function ImpersonatePickerDialog({ tenant, onClose }: { tenant: Tenant | null; onClose: () => void }) {
    const navigate = useNavigate();
    const { data: users, isLoading, isError } = useTenantUsers(tenant?.id ?? null);
    const startImpersonation = useStartImpersonation();
    const [serverError, setServerError] = useState<string | null>(null);

    if (tenant === null) {
        return null;
    }

    const handlePick = async (userId: string) => {
        setServerError(null);
        try {
            await startImpersonation.mutateAsync({ tenantId: tenant.id, userId });
            onClose();
            navigate('/', { replace: true });
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to start impersonation.'));
        }
    };

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="sm">
            <DialogTitle>View as a user in {tenant.id}</DialogTitle>
            <DialogContent>
                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}
                {isLoading && (
                    <Box display="flex" justifyContent="center" py={4}>
                        <CircularProgress />
                    </Box>
                )}
                {isError && <Alert severity="error">Unable to load this tenant's users.</Alert>}
                {!isLoading && !isError && users && users.length === 0 && (
                    <Alert severity="info">This tenant has no users yet.</Alert>
                )}
                {!isLoading && !isError && users && users.length > 0 && (
                    <List>
                        {users.map((tenantUser) => (
                            <ListItemButton
                                key={tenantUser.id}
                                disabled={startImpersonation.isPending}
                                onClick={() => handlePick(tenantUser.id)}
                            >
                                <ListItemText
                                    primary={tenantUser.name}
                                    secondary={`${tenantUser.email} — ${tenantUser.roles.join(', ') || 'no roles'}`}
                                />
                            </ListItemButton>
                        ))}
                    </List>
                )}
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
            </DialogActions>
        </Dialog>
    );
}

/** Platform Admin: list/create tenants, and impersonate any user within one. */
export function TenantsPage() {
    const { data, isLoading, isError } = useTenants();
    const [createOpen, setCreateOpen] = useState(false);
    const [impersonateTarget, setImpersonateTarget] = useState<Tenant | null>(null);

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Tenants</Typography>
                <Button variant="contained" startIcon={<Plus size={18} />} onClick={() => setCreateOpen(true)}>
                    New Tenant
                </Button>
            </Stack>

            {isLoading && (
                <Box display="flex" justifyContent="center" py={6}>
                    <CircularProgress />
                </Box>
            )}

            {isError && <Alert severity="error">Unable to load tenants. Please try again.</Alert>}

            {!isLoading && !isError && data && data.length === 0 && (
                <Alert severity="info">No tenants exist yet — create the first one above.</Alert>
            )}

            {!isLoading && !isError && data && data.length > 0 && (
                <Paper>
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>Tenant ID</TableCell>
                                    <TableCell>Created</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {data.map((tenant) => (
                                    <TableRow key={tenant.id} hover>
                                        <TableCell>{tenant.id}</TableCell>
                                        <TableCell>{new Date(tenant.created_at).toLocaleString()}</TableCell>
                                        <TableCell align="right">
                                            <IconButton
                                                size="small"
                                                title="View as a user in this tenant"
                                                onClick={() => setImpersonateTarget(tenant)}
                                            >
                                                <UserCog size={16} />
                                            </IconButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            )}

            <CreateTenantDialog open={createOpen} onClose={() => setCreateOpen(false)} />
            <ImpersonatePickerDialog tenant={impersonateTarget} onClose={() => setImpersonateTarget(null)} />
        </Box>
    );
}
