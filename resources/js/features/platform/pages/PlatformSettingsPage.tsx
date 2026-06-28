import { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    FormControlLabel,
    Paper,
    Stack,
    Switch,
    TextField,
    Typography,
} from '@mui/material';
import { usePlatformSettings, useUpdatePlatformSettings } from '../../admin/api/useAdmin';
import { getErrorMessage } from '../../../lib/getErrorMessage';

export function PlatformSettingsPage() {
    const { data: settings, isLoading, error } = usePlatformSettings();
    const update = useUpdatePlatformSettings();
    const [form, setForm] = useState({
        platform_name: '',
        support_email: '',
        default_locale: 'en',
        default_currency: 'TZS',
        maintenance_mode: false,
        max_tenants: '',
        logo_url: '',
        primary_color: '',
        support_url: '',
    });
    const [message, setMessage] = useState<string | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);

    useEffect(() => {
        if (!settings) return;
        setForm({
            platform_name: settings.platform_name,
            support_email: settings.support_email ?? '',
            default_locale: settings.default_locale,
            default_currency: settings.default_currency,
            maintenance_mode: settings.maintenance_mode,
            max_tenants: settings.max_tenants?.toString() ?? '',
            logo_url: settings.branding.logo_url ?? '',
            primary_color: settings.branding.primary_color ?? '',
            support_url: settings.branding.support_url ?? '',
        });
    }, [settings]);

    const handleSave = async () => {
        setMessage(null);
        setSaveError(null);
        try {
            await update.mutateAsync({
                platform_name: form.platform_name,
                support_email: form.support_email || null,
                default_locale: form.default_locale,
                default_currency: form.default_currency,
                maintenance_mode: form.maintenance_mode,
                max_tenants: form.max_tenants ? Number(form.max_tenants) : null,
                branding: {
                    logo_url: form.logo_url,
                    primary_color: form.primary_color,
                    support_url: form.support_url,
                },
            });
            setMessage('Platform settings saved.');
        } catch (e) {
            setSaveError(getErrorMessage(e, 'Unable to save platform settings.'));
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
        return <Alert severity="error">{getErrorMessage(error, 'Unable to load platform settings.')}</Alert>;
    }

    return (
        <Box>
            <Typography variant="h5" mb={3}>
                Platform Settings
            </Typography>

            <Paper sx={{ p: 3 }}>
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

                <Stack spacing={2}>
                    <TextField label="Platform name" value={form.platform_name} onChange={(e) => setForm((p) => ({ ...p, platform_name: e.target.value }))} />
                    <TextField label="Support email" value={form.support_email} onChange={(e) => setForm((p) => ({ ...p, support_email: e.target.value }))} />
                    <TextField label="Default locale" value={form.default_locale} onChange={(e) => setForm((p) => ({ ...p, default_locale: e.target.value }))} />
                    <TextField label="Default currency" value={form.default_currency} onChange={(e) => setForm((p) => ({ ...p, default_currency: e.target.value }))} />
                    <TextField label="Max tenants" type="number" value={form.max_tenants} onChange={(e) => setForm((p) => ({ ...p, max_tenants: e.target.value }))} helperText="Leave empty for unlimited" />
                    <FormControlLabel
                        control={<Switch checked={form.maintenance_mode} onChange={(e) => setForm((p) => ({ ...p, maintenance_mode: e.target.checked }))} />}
                        label="Maintenance mode"
                    />
                    <Typography variant="subtitle1" pt={1}>
                        Branding
                    </Typography>
                    <TextField label="Logo URL" value={form.logo_url} onChange={(e) => setForm((p) => ({ ...p, logo_url: e.target.value }))} />
                    <TextField label="Primary color" value={form.primary_color} onChange={(e) => setForm((p) => ({ ...p, primary_color: e.target.value }))} />
                    <TextField label="Support URL" value={form.support_url} onChange={(e) => setForm((p) => ({ ...p, support_url: e.target.value }))} />
                </Stack>

                <Box mt={3}>
                    <Button variant="contained" onClick={handleSave} disabled={update.isPending}>
                        Save changes
                    </Button>
                </Box>
            </Paper>
        </Box>
    );
}
