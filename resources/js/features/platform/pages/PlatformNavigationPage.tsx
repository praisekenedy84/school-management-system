import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
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
import { usePlatformNavigationManage, useUpdatePlatformNavigationItem } from '../../admin/api/useAdmin';
import { navIcon } from '../../../config/navIcons';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { NavigationItemAdmin } from '../../admin/types/admin';

export function PlatformNavigationPage() {
    const { data: sections, isLoading, error } = usePlatformNavigationManage();
    const updateItem = useUpdatePlatformNavigationItem();
    const [message, setMessage] = useState<string | null>(null);

    const saveItem = async (item: NavigationItemAdmin, patch: Partial<NavigationItemAdmin>) => {
        setMessage(null);
        try {
            await updateItem.mutateAsync({ id: item.id, ...patch });
            setMessage('Platform menu updated.');
        } catch (e) {
            setMessage(getErrorMessage(e, 'Unable to save.'));
        }
    };

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load platform navigation.')}</Alert>;
    }

    return (
        <Box>
            <Typography variant="h5" mb={3}>
                Platform Menu Editor
            </Typography>
            {message && (
                <Alert severity="info" sx={{ mb: 2 }}>
                    {message}
                </Alert>
            )}

            {(sections ?? []).map((section) => (
                <Paper key={section.id} sx={{ p: 2, mb: 3 }}>
                    <Typography variant="subtitle1" mb={2}>
                        {section.label}
                    </Typography>
                    <TableContainer>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Label</TableCell>
                                    <TableCell>Path</TableCell>
                                    <TableCell>Visible</TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {section.items.map((item) => (
                                    <PlatformItemRow key={item.id} item={item} onSave={(patch) => saveItem(item, patch)} />
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            ))}
        </Box>
    );
}

function PlatformItemRow({
    item,
    onSave,
}: {
    item: NavigationItemAdmin;
    onSave: (patch: Partial<NavigationItemAdmin>) => void;
}) {
    const [label, setLabel] = useState(item.label);
    const [isActive, setIsActive] = useState(item.is_active);

    return (
        <TableRow>
            <TableCell>
                <Stack direction="row" spacing={1} alignItems="center">
                    {navIcon(item.icon, 18)}
                    <TextField size="small" value={label} onChange={(e) => setLabel(e.target.value)} />
                </Stack>
            </TableCell>
            <TableCell>{item.path}</TableCell>
            <TableCell>
                <Checkbox checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
            </TableCell>
            <TableCell>
                <Button size="small" variant="outlined" onClick={() => onSave({ label, is_active: isActive })}>
                    Save
                </Button>
            </TableCell>
        </TableRow>
    );
}
