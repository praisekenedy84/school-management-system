import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import { useAuth } from '../app/AuthProvider';
import { NAV_SECTIONS, type NavSection } from '../config/navigation';
import { navIcon } from '../config/navIcons';

export type ApiNavigationSection = {
    id: string;
    label: string;
    sort_order: number;
    platform_only: boolean;
    is_active: boolean;
    items: ApiNavigationItem[];
};

export type ApiNavigationItem = {
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

export type ResolvedNavSection = {
    id?: string;
    label: string;
    platformOnly?: boolean;
    items: {
        id?: string;
        label: string;
        path: string;
        icon: JSX.Element;
        permissions: string[] | null;
    }[];
};

export const NAVIGATION_QUERY_KEY = ['navigation'] as const;

function fallbackSections(isPlatformAdmin: boolean): ResolvedNavSection[] {
    return NAV_SECTIONS.filter((section) => Boolean(section.platformOnly) === isPlatformAdmin).map((section: NavSection) => ({
        label: section.label,
        platformOnly: section.platformOnly,
        items: section.items.map((item) => ({
            label: item.label,
            path: item.path,
            icon: item.icon,
            permissions: item.permissions,
        })),
    }));
}

function mapApiSections(sections: ApiNavigationSection[]): ResolvedNavSection[] {
    return sections
        .filter((section) => section.is_active)
        .map((section) => ({
            id: section.id,
            label: section.label,
            platformOnly: section.platform_only,
            items: section.items
                .filter((item) => item.is_active)
                .map((item) => ({
                    id: item.id,
                    label: item.label,
                    path: item.path,
                    icon: navIcon(item.icon),
                    permissions: item.permissions,
                })),
        }))
        .filter((section) => section.items.length > 0);
}

export function useNavigationMenu(): UseQueryResult<ResolvedNavSection[]> {
    const { user } = useAuth();
    const isPlatformAdmin = user?.type === 'platform_admin';
    const endpoint = isPlatformAdmin ? '/platform/navigation' : '/navigation';

    return useQuery({
        queryKey: [...NAVIGATION_QUERY_KEY, isPlatformAdmin ? 'platform' : 'tenant'],
        queryFn: async () => {
            try {
                const { data } = await apiClient.get<{ data: ApiNavigationSection[] }>(endpoint);
                const mapped = mapApiSections(data.data);
                return mapped.length > 0 ? mapped : fallbackSections(isPlatformAdmin);
            } catch {
                return fallbackSections(isPlatformAdmin);
            }
        },
        staleTime: 60 * 1000,
        enabled: user !== null,
    });
}

/** Route guard map built from the live navigation tree. */
export function buildRoutePermissions(sections: ResolvedNavSection[]): Record<string, string[] | null> {
    return Object.fromEntries(sections.flatMap((section) => section.items.map((item) => [item.path, item.permissions])));
}
