import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../app/AuthProvider';

/**
 * Route guard for Platform Admin-only pages (tenant management, the
 * cross-tenant audit log) — mirrors RequireFinanceStaff/RequireHostelStaff.
 * Gated on `user.type`, not a role string: Platform Admin is a separate
 * central account (ADR-0008), never Spatie-roled. UX only — the API
 * authorizes via the `platform` guard regardless (RULES §8).
 */
export function RequirePlatformAdmin({ children }: { children: ReactNode }) {
    const { user } = useAuth();

    if (user?.type !== 'platform_admin') {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}
