import { useEffect, useMemo, useState } from 'react';
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
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { useAuth } from '../../../app/AuthProvider';
import {
    useAdminSchool,
    useAdminSchools,
    useUpdateSchoolBilling,
    useUpdateSchoolBranding,
    useUpdateSchoolSettings,
} from '../api/useAdmin';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { usePermissions } from '../../../lib/usePermissions';
import { Permission } from '../../../config/permissions';

export function TenantSettingsPage() {
    const { user } = useAuth();
    const { can } = usePermissions();
    const canSettings = can(Permission.tenant.manageSettings);
    const canBranding = can(Permission.tenant.manageBranding);
    const canBilling = can(Permission.tenant.manageBilling);

    const { data: schools, isLoading: schoolsLoading } = useAdminSchools();
    const defaultSchoolId = user?.school_id ?? schools?.[0]?.id ?? null;
    const [schoolId, setSchoolId] = useState<string | null>(defaultSchoolId);
    const [tab, setTab] = useState(0);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const { data: school, isLoading: schoolLoading } = useAdminSchool(schoolId);
    const updateSettings = useUpdateSchoolSettings();
    const updateBranding = useUpdateSchoolBranding();
    const updateBilling = useUpdateSchoolBilling();

    const [settingsForm, setSettingsForm] = useState({
        locale: 'en',
        currency: 'TZS',
        timezone: 'Africa/Dar_es_Salaam',
        calendar_type: '',
        hostel_available: false,
    });
    const [brandingForm, setBrandingForm] = useState({
        logo_url: '',
        primary_color: '',
        secondary_color: '',
        tagline: '',
    });
    const [billingForm, setBillingForm] = useState({
        billing_contact_name: '',
        billing_contact_email: '',
        billing_contact_phone: '',
        tax_id: '',
        billing_address: '',
        invoice_notes: '',
    });

    useEffect(() => {
        if (defaultSchoolId && !schoolId) {
            setSchoolId(defaultSchoolId);
        }
    }, [defaultSchoolId, schoolId]);

    useEffect(() => {
        if (!school) {
            return;
        }
        setSettingsForm({
            locale: school.locale,
            currency: school.currency,
            timezone: school.timezone,
            calendar_type: school.calendar_type ?? '',
            hostel_available: school.hostel_available,
        });
        setBrandingForm({
            logo_url: school.branding.logo_url ?? '',
            primary_color: school.branding.primary_color ?? '',
            secondary_color: school.branding.secondary_color ?? '',
            tagline: school.branding.tagline ?? '',
        });
        setBillingForm({
            billing_contact_name: school.billing.billing_contact_name ?? '',
            billing_contact_email: school.billing.billing_contact_email ?? '',
            billing_contact_phone: school.billing.billing_contact_phone ?? '',
            tax_id: school.billing.tax_id ?? '',
            billing_address: school.billing.billing_address ?? '',
            invoice_notes: school.billing.invoice_notes ?? '',
        });
    }, [school]);

    const visibleTabs = useMemo(() => {
        const tabs: { label: string; key: 'settings' | 'branding' | 'billing' }[] = [];
        if (canSettings) tabs.push({ label: 'General', key: 'settings' });
        if (canBranding) tabs.push({ label: 'Branding', key: 'branding' });
        if (canBilling) tabs.push({ label: 'Billing', key: 'billing' });
        return tabs;
    }, [canSettings, canBranding, canBilling]);

    const activeTab = visibleTabs[tab]?.key ?? 'settings';
    const busy = updateSettings.isPending || updateBranding.isPending || updateBilling.isPending;

    const handleSave = async () => {
        if (!schoolId) return;
        setMessage(null);
        setError(null);
        try {
            if (activeTab === 'settings' && canSettings) {
                await updateSettings.mutateAsync({ id: schoolId, ...settingsForm, calendar_type: settingsForm.calendar_type || null });
            } else if (activeTab === 'branding' && canBranding) {
                await updateBranding.mutateAsync({ id: schoolId, branding: brandingForm });
            } else if (activeTab === 'billing' && canBilling) {
                await updateBilling.mutateAsync({ id: schoolId, billing: billingForm });
            }
            setMessage('Settings saved.');
        } catch (e) {
            setError(getErrorMessage(e, 'Unable to save settings.'));
        }
    };

    if (!canSettings && !canBranding && !canBilling) {
        return <Alert severity="warning">You do not have permission to manage tenant settings.</Alert>;
    }

    if (schoolsLoading || schoolLoading) {
        return (
            <Box display="flex" justifyContent="center" py={6}>
                <CircularProgress />
            </Box>
        );
    }

    return (
        <Box>
            <Typography variant="h5" mb={3}>
                Tenant Settings
            </Typography>

            {user?.school_id === null && (schools?.length ?? 0) > 1 && (
                <TextField
                    select
                    label="School"
                    value={schoolId ?? ''}
                    onChange={(e) => setSchoolId(e.target.value)}
                    sx={{ mb: 2, minWidth: 280 }}
                >
                    {(schools ?? []).map((s) => (
                        <MenuItem key={s.id} value={s.id}>
                            {s.name} ({s.code})
                        </MenuItem>
                    ))}
                </TextField>
            )}

            <Paper sx={{ p: 3 }}>
                <Tabs value={tab} onChange={(_, value) => setTab(value)} sx={{ mb: 3 }}>
                    {visibleTabs.map((t) => (
                        <Tab key={t.key} label={t.label} />
                    ))}
                </Tabs>

                {message && (
                    <Alert severity="success" sx={{ mb: 2 }}>
                        {message}
                    </Alert>
                )}
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {error}
                    </Alert>
                )}

                {activeTab === 'settings' && (
                    <Stack spacing={2}>
                        <TextField label="Locale" value={settingsForm.locale} onChange={(e) => setSettingsForm((p) => ({ ...p, locale: e.target.value }))} />
                        <TextField label="Currency" value={settingsForm.currency} onChange={(e) => setSettingsForm((p) => ({ ...p, currency: e.target.value }))} />
                        <TextField label="Timezone" value={settingsForm.timezone} onChange={(e) => setSettingsForm((p) => ({ ...p, timezone: e.target.value }))} />
                        <TextField label="Calendar type" value={settingsForm.calendar_type} onChange={(e) => setSettingsForm((p) => ({ ...p, calendar_type: e.target.value }))} />
                        <FormControlLabel
                            control={<Checkbox checked={settingsForm.hostel_available} onChange={(e) => setSettingsForm((p) => ({ ...p, hostel_available: e.target.checked }))} />}
                            label="Hostel module enabled"
                        />
                    </Stack>
                )}

                {activeTab === 'branding' && (
                    <Stack spacing={2}>
                        <TextField label="Logo URL" value={brandingForm.logo_url} onChange={(e) => setBrandingForm((p) => ({ ...p, logo_url: e.target.value }))} />
                        <TextField label="Primary color" value={brandingForm.primary_color} onChange={(e) => setBrandingForm((p) => ({ ...p, primary_color: e.target.value }))} />
                        <TextField label="Secondary color" value={brandingForm.secondary_color} onChange={(e) => setBrandingForm((p) => ({ ...p, secondary_color: e.target.value }))} />
                        <TextField label="Tagline" value={brandingForm.tagline} onChange={(e) => setBrandingForm((p) => ({ ...p, tagline: e.target.value }))} />
                    </Stack>
                )}

                {activeTab === 'billing' && (
                    <Stack spacing={2}>
                        <TextField label="Contact name" value={billingForm.billing_contact_name} onChange={(e) => setBillingForm((p) => ({ ...p, billing_contact_name: e.target.value }))} />
                        <TextField label="Contact email" value={billingForm.billing_contact_email} onChange={(e) => setBillingForm((p) => ({ ...p, billing_contact_email: e.target.value }))} />
                        <TextField label="Contact phone" value={billingForm.billing_contact_phone} onChange={(e) => setBillingForm((p) => ({ ...p, billing_contact_phone: e.target.value }))} />
                        <TextField label="Tax ID" value={billingForm.tax_id} onChange={(e) => setBillingForm((p) => ({ ...p, tax_id: e.target.value }))} />
                        <TextField label="Billing address" multiline minRows={2} value={billingForm.billing_address} onChange={(e) => setBillingForm((p) => ({ ...p, billing_address: e.target.value }))} />
                        <TextField label="Invoice notes" multiline minRows={2} value={billingForm.invoice_notes} onChange={(e) => setBillingForm((p) => ({ ...p, invoice_notes: e.target.value }))} />
                    </Stack>
                )}

                <Box mt={3}>
                    <Button variant="contained" onClick={handleSave} disabled={busy || !schoolId}>
                        Save changes
                    </Button>
                </Box>
            </Paper>
        </Box>
    );
}
