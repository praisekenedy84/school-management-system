import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { buildRoutePermissions, useNavigationMenu } from '../lib/useNavigation';
import { ROUTE_PERMISSIONS } from '../config/navigation';

type NavigationContextValue = {
    routePermissions: Record<string, string[] | null>;
    isLoading: boolean;
};

const NavigationContext = createContext<NavigationContextValue>({
    routePermissions: ROUTE_PERMISSIONS,
    isLoading: false,
});

export function NavigationProvider({ children }: { children: ReactNode }) {
    const { data: sections, isLoading } = useNavigationMenu();

    const routePermissions = useMemo(() => {
        if (!sections || sections.length === 0) {
            return ROUTE_PERMISSIONS;
        }
        return { ...ROUTE_PERMISSIONS, ...buildRoutePermissions(sections) };
    }, [sections]);

    return (
        <NavigationContext.Provider value={{ routePermissions, isLoading }}>{children}</NavigationContext.Provider>
    );
}

export function useRoutePermissions() {
    return useContext(NavigationContext);
}
