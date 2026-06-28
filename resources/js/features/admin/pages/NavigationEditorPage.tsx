import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
    FormControlLabel,
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
import { useAdminNavigation, usePermissionCatalog, useUpdateNavigationItem, useUpdateNavigationSection } from '../api/useAdmin';
import { NAV_ICON_OPTIONS, navIcon } from '../../../config/navIcons';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { NavigationItemAdmin } from '../types/admin';

export function NavigationEditorPage() {
    const { data: sections, isLoading, error } = useAdminNavigation();
    const { data: catalog = [] } = usePermissionCatalog();
    const updateItem = useUpdateNavigationItem();
    const updateSection = useUpdateNavigationSection();
    const [message, setMessage] = useState<string | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);

    const handleSaveItem = async (item: NavigationItemAdmin, patch: Partial<NavigationItemAdmin>) => {
        setMessage(null);
        setSaveError(null);
        try {
            await updateItem.mutateAsync({ id: item.id, ...patch });
            setMessage('Menu item saved.');
        } catch (e) {
            setSaveError(getErrorMessage(e, 'Unable to save menu item.'));
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
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load navigation.')}</Alert>;
    }

    return (
        <Box>
            <Typography variant="h5" mb={1}>
                Menu Editor
            </Typography>
            <Typography variant="body2" color="text.secondary" mb={3}>
                Rename items, change icons, toggle visibility, and adjust which permissions unlock each menu entry.
            </Typography>

            {message && (
                <Alert severity="success" sx={{ mb: 2 }}>
                    {message}
                </Alert>
            )}
            {saveError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {saveError}
                </Alert>
            )}

            {(sections ?? []).map((section) => (
                <Paper key={section.id} sx={{ p: 2, mb: 3 }}>
                    <Stack direction="row" spacing={2} alignItems="center" mb={2}>
                        <TextField
                            label="Section label"
                            size="small"
                            defaultValue={section.label}
                            onBlur={(e) => {
                                if (e.target.value !== section.label) {
                                    updateSection.mutate({ id: section.id, label: e.target.value });
                                }
                            }}
                        />
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={section.is_active}
                                    onChange={(e) => updateSection.mutate({ id: section.id, is_active: e.target.checked })}
                                />
                            }
                            label="Section visible"
                        />
                    </Stack>

                    <TableContainer>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Icon</TableCell>
                                    <TableCell>Label</TableCell>
                                    <TableCell>Path</TableCell>
                                    <TableCell>Permissions</TableCell>
                                    <TableCell>Visible</TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {section.items.map((item) => (
                                    <NavigationItemRow
                                        key={item.id}
                                        item={item}
                                        catalog={catalog.map((c) => c.name)}
                                        onSave={(patch) => handleSaveItem(item, patch)}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
                </Paper>
            ))}
        </Box>
    );
}

function NavigationItemRow({
    item,
    catalog,
    onSave,
}: {
    item: NavigationItemAdmin;
    catalog: string[];
    onSave: (patch: Partial<NavigationItemAdmin>) => void;
}) {
    const [label, setLabel] = useState(item.label);
    const [icon, setIcon] = useState(item.icon);
    const [permissions, setPermissions] = useState<string[]>(item.permissions ?? []);
    const [isActive, setIsActive] = useState(item.is_active);

    return (
        <TableRow>
            <TableCell>{navIcon(icon, 18)}</TableCell>
            <TableCell>
                <TextField size="small" value={label} onChange={(e) => setLabel(e.target.value)} />
            </TableCell>
            <TableCell>
                <Typography variant="body2">{item.path}</Typography>
            </TableCell>
            <TableCell>
                <TextField
                    select
                    size="small"
                    SelectProps={{ multiple: true, value: permissions, onChange: (e) => setPermissions(e.target.value as string[]) }}
                    sx={{ minWidth: 220 }}
                >
                    {catalog.map((name) => (
                        <MenuItem key={name} value={name}>
                            {name}
                        </MenuItem>
                    ))}
                </TextField>
            </TableCell>
            <TableCell>
                <Checkbox checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
            </TableCell>
            <TableCell>
                <Button
                    size="small"
                    variant="outlined"
                    onClick={() =>
                        onSave({
                            label,
                            icon,
                            permissions: permissions.length > 0 ? permissions : null,
                            is_active: isActive,
                        })
                    }
                >
                    Save
                </Button>
            </TableCell>
        </TableRow>
    );
}
