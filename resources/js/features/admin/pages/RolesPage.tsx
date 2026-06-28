import { useMemo, useState } from 'react';
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
import { Pencil, Plus, Trash2 } from 'lucide-react';
import {
    useCreateRoleDefinition,
    useDeleteRoleDefinition,
    usePermissionCatalog,
    useRoleDefinitions,
    useSyncRolePermissions,
} from '../api/useAdmin';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { RoleDefinition } from '../types/admin';

function formatRole(name: string): string {
    return name.replace(/_/g, ' ');
}

function RolePermissionsDialog({
    role,
    open,
    onClose,
}: {
    role: RoleDefinition | null;
    open: boolean;
    onClose: () => void;
}) {
    const { data: catalog = [] } = usePermissionCatalog();
    const sync = useSyncRolePermissions();
    const [selected, setSelected] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);

    const toggle = (name: string) => {
        setSelected((prev) => (prev.includes(name) ? prev.filter((p) => p !== name) : [...prev, name]));
    };

    const handleSave = async () => {
        if (!role) return;
        setError(null);
        try {
            await sync.mutateAsync({ role: role.name, permissions: selected });
            onClose();
        } catch (e) {
            setError(getErrorMessage(e, 'Unable to save permissions.'));
        }
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullWidth
            maxWidth="md"
            TransitionProps={{ onEnter: () => setSelected(role?.permissions ?? []) }}
        >
            <DialogTitle>Permissions — {role ? formatRole(role.name) : ''}</DialogTitle>
            <DialogContent>
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                )}
                <Stack spacing={0.5} mt={1}>
                    {catalog.map((entry) => (
                        <FormControlLabel
                            key={entry.name}
                            control={
                                <Checkbox
                                    checked={selected.includes(entry.name)}
                                    onChange={() => toggle(entry.name)}
                                    disabled={role?.is_protected}
                                />
                            }
                            label={
                                <Box>
                                    <Typography variant="body2">{entry.description}</Typography>
                                    <Typography variant="caption" color="text.secondary">
                                        {entry.name}
                                    </Typography>
                                </Box>
                            }
                        />
                    ))}
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="contained" onClick={handleSave} disabled={sync.isPending || selected.length === 0 || role?.is_protected}>
                    Save
                </Button>
            </DialogActions>
        </Dialog>
    );
}

function CreateRoleDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const create = useCreateRoleDefinition();
    const { data: catalog = [] } = usePermissionCatalog();
    const [name, setName] = useState('');
    const [selected, setSelected] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);

    const handleCreate = async () => {
        setError(null);
        try {
            await create.mutateAsync({ name, permissions: selected });
            onClose();
            setName('');
            setSelected([]);
        } catch (e) {
            setError(getErrorMessage(e, 'Unable to create role.'));
        }
    };

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
            <DialogTitle>New Role</DialogTitle>
            <DialogContent>
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                )}
                <Stack spacing={2} mt={1}>
                    <TextField
                        label="Role key"
                        helperText="Lowercase with underscores, e.g. lab_assistant"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        fullWidth
                    />
                    <Typography variant="subtitle2">Initial permissions</Typography>
                    {catalog.slice(0, 8).map((entry) => (
                        <FormControlLabel
                            key={entry.name}
                            control={
                                <Checkbox
                                    checked={selected.includes(entry.name)}
                                    onChange={() =>
                                        setSelected((prev) =>
                                            prev.includes(entry.name)
                                                ? prev.filter((p) => p !== entry.name)
                                                : [...prev, entry.name],
                                        )
                                    }
                                />
                            }
                            label={entry.description}
                        />
                    ))}
                    <Typography variant="caption" color="text.secondary">
                        Fine-tune all permissions after creating the role.
                    </Typography>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="contained" onClick={handleCreate} disabled={!name || selected.length === 0 || create.isPending}>
                    Create
                </Button>
            </DialogActions>
        </Dialog>
    );
}

export function RolesPage() {
    const { data: roles, isLoading, error } = useRoleDefinitions();
    const deleteRole = useDeleteRoleDefinition();
    const [editing, setEditing] = useState<RoleDefinition | null>(null);
    const [creating, setCreating] = useState(false);

    const sorted = useMemo(() => [...(roles ?? [])].sort((a, b) => a.name.localeCompare(b.name)), [roles]);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load roles.')}</Alert>;
    }

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" mb={3}>
                <Box>
                    <Typography variant="h5">Role Permissions</Typography>
                    <Typography variant="body2" color="text.secondary">
                        Customize what each role can do. Users inherit permissions from their assigned roles.
                    </Typography>
                </Box>
                <Button variant="contained" startIcon={<Plus size={18} />} onClick={() => setCreating(true)}>
                    New Role
                </Button>
            </Stack>

            <TableContainer component={Paper}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Role</TableCell>
                            <TableCell>Permissions</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {sorted.map((role) => (
                            <TableRow key={role.name}>
                                <TableCell>
                                    {formatRole(role.name)}
                                    {role.is_protected && (
                                        <Typography variant="caption" display="block" color="text.secondary">
                                            Protected
                                        </Typography>
                                    )}
                                </TableCell>
                                <TableCell>{role.permissions.length}</TableCell>
                                <TableCell align="right">
                                    <IconButton aria-label="Edit permissions" onClick={() => setEditing(role)}>
                                        <Pencil size={18} />
                                    </IconButton>
                                    {!role.is_protected && (
                                        <IconButton
                                            aria-label="Delete role"
                                            onClick={() => deleteRole.mutate(role.name)}
                                            disabled={deleteRole.isPending}
                                        >
                                            <Trash2 size={18} />
                                        </IconButton>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

            <RolePermissionsDialog role={editing} open={editing !== null} onClose={() => setEditing(null)} />
            <CreateRoleDialog open={creating} onClose={() => setCreating(false)} />
        </Box>
    );
}
