export type SchoolAdmin = {
    id: string;
    name: string;
    code: string;
    locale: string;
    currency: string;
    timezone: string;
    branding: Record<string, string>;
    calendar_type: string | null;
    grading_scale: Record<string, unknown>;
    fee_terms: Record<string, unknown>;
    billing: Record<string, string>;
    hostel_available: boolean;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type SchoolRequest = {
    name: string;
    code: string;
    locale?: string;
    currency?: string;
    timezone?: string;
    calendar_type?: string | null;
    hostel_available?: boolean;
    is_active?: boolean;
};

export type SchoolSettingsRequest = {
    locale?: string;
    currency?: string;
    timezone?: string;
    calendar_type?: string | null;
    grading_scale?: Record<string, unknown>;
    fee_terms?: Record<string, unknown>;
    hostel_available?: boolean;
};

export type SchoolBrandingRequest = {
    branding: {
        logo_url?: string;
        primary_color?: string;
        secondary_color?: string;
        tagline?: string;
    };
};

export type SchoolBillingRequest = {
    billing: {
        billing_contact_name?: string;
        billing_contact_email?: string;
        billing_contact_phone?: string;
        tax_id?: string;
        billing_address?: string;
        invoice_notes?: string;
    };
};

export type AdminUser = {
    id: string;
    school_id: string | null;
    name: string;
    email: string;
    phone: string | null;
    locale: string | null;
    is_active: boolean;
    roles: string[];
    permissions: string[];
};

export type RoleDefinition = {
    name: string;
    permissions: string[];
    is_protected: boolean;
};

export type PermissionCatalogEntry = {
    name: string;
    description: string;
};

export type NavigationSectionAdmin = {
    id: string;
    label: string;
    sort_order: number;
    platform_only: boolean;
    is_active: boolean;
    items: NavigationItemAdmin[];
};

export type NavigationItemAdmin = {
    id: string;
    section_id: string;
    label: string;
    path: string;
    icon: string;
    permissions: string[] | null;
    sort_order: number;
    is_active: boolean;
    is_system: boolean;
};

export type PlatformSettings = {
    id: string;
    platform_name: string;
    support_email: string | null;
    default_locale: string;
    default_currency: string;
    maintenance_mode: boolean;
    max_tenants: number | null;
    branding: Record<string, string>;
    updated_at: string | null;
};

export type PlatformSettingsRequest = {
    platform_name?: string;
    support_email?: string | null;
    default_locale?: string;
    default_currency?: string;
    maintenance_mode?: boolean;
    max_tenants?: number | null;
    branding?: Record<string, string>;
};
