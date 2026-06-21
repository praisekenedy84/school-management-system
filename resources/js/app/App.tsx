import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { CssBaseline, ThemeProvider } from '@mui/material';
import { BrowserRouter } from 'react-router-dom';
import { theme } from '../theme';
import { AppRoutes } from '../routes';
import { AuthProvider } from './AuthProvider';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1,
        },
    },
});

/**
 * Root provider stack: React Query (server state) -> MUI theme/CssBaseline
 * (presentation) -> Router -> Auth context -> route table.
 */
export function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <BrowserRouter>
                    <AuthProvider>
                        <AppRoutes />
                    </AuthProvider>
                </BrowserRouter>
            </ThemeProvider>
        </QueryClientProvider>
    );
}
