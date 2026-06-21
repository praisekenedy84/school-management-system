import { createTheme, type ThemeOptions } from '@mui/material/styles';

/**
 * Minimal baseline MUI theme for the Phase 0 shell.
 *
 * TODO (Phase 5+, per FRONTEND.md / ARCHITECTURE.md §6): this should read
 * tenant branding (logo, palette) from a settings endpoint at runtime and
 * merge it into these tokens. Keep that mechanism out of scope until the
 * branding endpoint exists — for now this is a single static theme shared
 * by every tenant subdomain.
 */
const themeOptions: ThemeOptions = {
    palette: {
        mode: 'light',
        primary: {
            main: '#1565c0',
        },
        secondary: {
            main: '#2e7d32',
        },
    },
    typography: {
        fontFamily: ['"Figtree"', '"Inter"', 'system-ui', 'sans-serif'].join(','),
    },
    shape: {
        borderRadius: 8,
    },
};

export const theme = createTheme(themeOptions);
