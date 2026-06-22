/** Mirrors App\Http\Resources\Platform\TenantResource. */
export interface Tenant {
    id: string;
    created_at: string;
}

export interface CreateTenantRequest {
    tenant_id: string;
    school_name: string;
    school_code: string;
    admin_name: string;
    admin_email: string;
    admin_password: string;
}

/** Mirrors App\Http\Resources\Platform\TenantUserResource. */
export interface TenantUser {
    id: string;
    name: string;
    email: string;
    roles: string[];
}

/** Mirrors App\Http\Resources\Platform\AuditLogResource. */
export interface AuditLogEntry {
    id: string;
    tenant_id: string | null;
    actor_type: 'platform_admin' | 'user';
    actor_id: string | null;
    actor_name: string | null;
    actor_email: string | null;
    action: string;
    subject_type: string | null;
    subject_id: string | null;
    changes: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
}

export interface AuditLogFilters {
    tenant_id?: string;
    actor_id?: string;
    action?: string;
    from?: string;
    to?: string;
}
