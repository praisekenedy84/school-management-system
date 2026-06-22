import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { CssBaseline, ThemeProvider, type PaletteMode } from '@mui/material';
import { createAppTheme } from './index';

const STORAGE_KEY = 'sms-color-mode';

function getInitialMode(): PaletteMode {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

interface ColorModeContextValue {
    mode: PaletteMode;
    toggleColorMode: () => void;
}

const ColorModeContext = createContext<ColorModeContextValue | undefined>(undefined);

/** Wraps MUI's ThemeProvider/CssBaseline with a persisted light/dark mode toggle. */
export function ColorModeProvider({ children }: { children: ReactNode }) {
    const [mode, setMode] = useState<PaletteMode>(getInitialMode);

    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, mode);
    }, [mode]);

    const toggleColorMode = useCallback(() => {
        setMode((prev) => (prev === 'light' ? 'dark' : 'light'));
    }, []);

    const value = useMemo<ColorModeContextValue>(() => ({ mode, toggleColorMode }), [mode, toggleColorMode]);
    const theme = useMemo(() => createAppTheme(mode), [mode]);

    return (
        <ColorModeContext.Provider value={value}>
            <ThemeProvider theme={theme}>
                <CssBaseline />
                {children}
            </ThemeProvider>
        </ColorModeContext.Provider>
    );
}

export function useColorMode(): ColorModeContextValue {
    const context = useContext(ColorModeContext);
    if (!context) {
        throw new Error('useColorMode must be used within a ColorModeProvider');
    }
    return context;
}
