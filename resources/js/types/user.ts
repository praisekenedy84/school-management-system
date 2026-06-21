/**
 * Mirrors App\Http\Resources\UserResource.
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
}

/** Envelope shape returned by Laravel's JsonResource (single resource). */
export interface ApiResource<T> {
    data: T;
}
