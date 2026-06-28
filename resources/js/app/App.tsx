import { QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { ColorModeProvider } from '../theme/ColorModeProvider';
import { AppRoutes } from '../routes';
import { AuthProvider } from './AuthProvider';
import { IdleSessionGuard } from './IdleSessionGuard';
import { NavigationProvider } from './NavigationProvider';
import { queryClient } from '../api/queryClient';

/**
 * Root provider stack: React Query (server state) -> color mode/MUI theme
 * (presentation, persisted light/dark toggle) -> Router -> Auth context ->
 * route table. <IdleSessionGuard> is a sibling of the routes themselves —
 * it needs both the auth context and router (useNavigate), and renders
 * nothing of its own while logged out.
 */
export function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <ColorModeProvider>
                <BrowserRouter>
                    <AuthProvider>
                        <NavigationProvider>
                            <IdleSessionGuard />
                            <AppRoutes />
                        </NavigationProvider>
                    </AuthProvider>
                </BrowserRouter>
            </ColorModeProvider>
        </QueryClientProvider>
    );
}
