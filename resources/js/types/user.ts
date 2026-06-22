/** Metadata attached to `/me` (and the impersonate-start response) while a
 * Platform Admin is impersonating this user — see
 * App\Http\Controllers\Api\Auth\AuthController::me() and
 * App\Services\Platform\ImpersonationService. */
export interface ImpersonationContext {
    platform_admin_id: string;
    platform_admin_name: string;
    started_at: string;
}

/**
 * Mirrors App\Http\Resources\UserResource (tenant users) and
 * App\Http\Resources\PlatformAdminResource (`type: 'platform_admin'`,
 * `roles`/`permissions` empty — central account, not Spatie-scoped).
 * Roles/permissions are plain string arrays (Spatie's getRoleNames() /
 * getAllPermissions()->pluck('name')).
 */
export interface User {
    id: string;
    school_id: string | null;
    name: string;
    email: string;
    phone: string | null;
    locale: string | null;
    roles: string[];
    permissions: string[];
    type?: 'platform_admin';
    impersonation?: ImpersonationContext;
}

/**
 * Envelope shape returned by Laravel's JsonResource (single resource).
 * `impersonation` is an optional SIBLING of `data`, added via the backend's
 * `->additional([...])` — present only on `/me` and the impersonate-start
 * response while a Platform Admin is impersonating someone.
 */
export interface ApiResource<T> {
    data: T;
    impersonation?: ImpersonationContext;
}
