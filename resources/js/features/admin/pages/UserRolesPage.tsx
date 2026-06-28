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
import { Pencil } from 'lucide-react';
import { useAdminUsers, useAssignableRoles, useUpdateUserRoles } from '../api/useAdmin';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { AdminUser } from '../types/admin';

function formatRole(name: string): string {
    return name.replace(/_/g, ' ');
}

function RolesDialog({ user, open, onClose }: { user: AdminUser | null; open: boolean; onClose: () => void }) {
    const { data: roles = [] } = useAssignableRoles();
    const updateRoles = useUpdateUserRoles();
    const [selected, setSelected] = useState<string[]>(user?.roles ?? []);
    const [error, setError] = useState<string | null>(null);

    const handleSave = async () => {
        if (!user) return;
        setError(null);
        try {
            await updateRoles.mutateAsync({ userId: user.id, roles: selected });
            onClose();
        } catch (e) {
            setError(getErrorMessage(e, 'Unable to update roles.'));
        }
    };

    return (
        <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm" TransitionProps={{ onEnter: () => setSelected(user?.roles ?? []) }}>
            <DialogTitle>Roles — {user?.name}</DialogTitle>
            <DialogContent>
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                )}
                <TextField
                    select
                    label="Roles"
                    value={selected}
                    onChange={(e) => setSelected(typeof e.target.value === 'string' ? [e.target.value] : e.target.value)}
                    fullWidth
                    SelectProps={{ multiple: true, renderValue: (value) => (value as string[]).map(formatRole).join(', ') }}
                    sx={{ mt: 1 }}
                >
                    {roles.map((role) => (
                        <MenuItem key={role} value={role}>
                            {formatRole(role)}
                        </MenuItem>
                    ))}
                </TextField>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="contained" onClick={handleSave} disabled={updateRoles.isPending || selected.length === 0}>
                    Save
                </Button>
            </DialogActions>
        </Dialog>
    );
}

export function UserRolesPage() {
    const { data: users, isLoading, error } = useAdminUsers();
    const [editing, setEditing] = useState<AdminUser | null>(null);

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load users.')}</Alert>;
    }

    return (
        <Box>
            <Typography variant="h5" mb={3}>
                Users &amp; Roles
            </Typography>

            <TableContainer component={Paper}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Name</TableCell>
                            <TableCell>Email</TableCell>
                            <TableCell>Roles</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {(users ?? []).map((user) => (
                            <TableRow key={user.id}>
                                <TableCell>{user.name}</TableCell>
                                <TableCell>{user.email}</TableCell>
                                <TableCell>
                                    <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                                        {user.roles.map((role) => (
                                            <Chip key={role} size="small" label={formatRole(role)} />
                                        ))}
                                    </Stack>
                                </TableCell>
                                <TableCell align="right">
                                    <IconButton aria-label="Edit roles" onClick={() => setEditing(user)}>
                                        <Pencil size={18} />
                                    </IconButton>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

            <RolesDialog user={editing} open={editing !== null} onClose={() => setEditing(null)} />
        </Box>
    );
}
