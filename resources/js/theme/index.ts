import { alpha, createTheme, type PaletteMode, type ThemeOptions } from '@mui/material/styles';

/**
 * Glassmorphism theme, light/dark mode aware. Accent is a two-tone blue
 * pair — dark blue + light blue — with the roles swapped per mode so the
 * accent always has contrast against the surface it sits on: dark blue
 * reads as the primary accent on light glass, light blue on dark glass.
 *
 * TODO (Phase 5+, per FRONTEND.md / ARCHITECTURE.md §6): this should read
 * tenant branding (logo, palette) from a settings endpoint at runtime and
 * merge it into these tokens. Keep that mechanism out of scope until the
 * branding endpoint exists — for now this is a single static theme shared
 * by every tenant, with the only per-user variable being light/dark mode.
 */
const GREY_500 = '#919EAB';
const ACCENT_DARK_BLUE = '#0B3D91';
const ACCENT_LIGHT_BLUE = '#42A5F5';

const GLASS_BLUR = 'blur(20px) saturate(180%)';

export function getDesignTokens(mode: PaletteMode): ThemeOptions {
    const isLight = mode === 'light';

    const glassSurfaceColor = isLight ? '#FFFFFF' : '#0F2942';
    const glassBorderColor = isLight ? alpha('#FFFFFF', 0.5) : alpha('#9FB3CC', 0.12);

    return {
        palette: {
            mode,
            primary: {
                main: isLight ? ACCENT_DARK_BLUE : ACCENT_LIGHT_BLUE,
            },
            secondary: {
                main: isLight ? ACCENT_LIGHT_BLUE : ACCENT_DARK_BLUE,
            },
            background: {
                default: isLight ? '#EAF2FF' : '#0A1929',
                paper: isLight ? '#FFFFFF' : '#0F2942',
            },
            divider: alpha(GREY_500, isLight ? 0.2 : 0.16),
            text: {
                primary: isLight ? '#13213A' : '#E6F1FF',
                secondary: isLight ? '#5B6B82' : '#9FB3CC',
            },
        },
        typography: {
            fontFamily: ['"Figtree"', '"Inter"', 'system-ui', 'sans-serif'].join(','),
            h5: { fontWeight: 700 },
            h6: { fontWeight: 700 },
            subtitle1: { fontWeight: 600 },
            subtitle2: { fontWeight: 600 },
            button: { fontWeight: 600, textTransform: 'none' },
        },
        shape: {
            borderRadius: 14,
        },
        components: {
            MuiCssBaseline: {
                styleOverrides: {
                    body: {
                        minHeight: '100vh',
                        backgroundAttachment: 'fixed',
                        backgroundImage: isLight
                            ? 'radial-gradient(at 15% 0%, #D6E9FF 0%, transparent 55%), ' +
                              'radial-gradient(at 85% 100%, #C7E6FF 0%, transparent 55%), ' +
                              'linear-gradient(135deg, #F4F9FF 0%, #E7F1FF 100%)'
                            : 'radial-gradient(at 15% 0%, #15314F 0%, transparent 55%), ' +
                              'radial-gradient(at 85% 100%, #0E263F 0%, transparent 55%), ' +
                              'linear-gradient(135deg, #071524 0%, #0A1929 100%)',
                    },
                },
            },
            MuiPaper: {
                styleOverrides: {
                    root: {
                        backgroundImage: 'none',
                        backgroundColor: alpha(glassSurfaceColor, isLight ? 0.65 : 0.55),
                        backdropFilter: GLASS_BLUR,
                        WebkitBackdropFilter: GLASS_BLUR,
                    },
                },
            },
            MuiCard: {
                styleOverrides: {
                    root: {
                        border: `1px solid ${glassBorderColor}`,
                        boxShadow: `0 8px 32px 0 ${alpha('#0B3D91', isLight ? 0.1 : 0.3)}`,
                    },
                },
            },
            MuiAppBar: {
                styleOverrides: {
                    root: {
                        backgroundColor: alpha(glassSurfaceColor, isLight ? 0.7 : 0.6),
                        backdropFilter: GLASS_BLUR,
                        WebkitBackdropFilter: GLASS_BLUR,
                        backgroundImage: 'none',
                        color: isLight ? '#13213A' : '#E6F1FF',
                        boxShadow: 'none',
                        borderBottom: `1px solid ${glassBorderColor}`,
                    },
                },
            },
            MuiDrawer: {
                styleOverrides: {
                    paper: {
                        borderRight: `1px solid ${glassBorderColor}`,
                        backgroundColor: alpha(glassSurfaceColor, isLight ? 0.7 : 0.6),
                        backdropFilter: GLASS_BLUR,
                        WebkitBackdropFilter: GLASS_BLUR,
                        backgroundImage: 'none',
                    },
                },
            },
            MuiMenu: {
                styleOverrides: {
                    paper: {
                        backgroundColor: alpha(glassSurfaceColor, isLight ? 0.85 : 0.8),
                        backdropFilter: GLASS_BLUR,
                        WebkitBackdropFilter: GLASS_BLUR,
                        backgroundImage: 'none',
                        border: `1px solid ${glassBorderColor}`,
                    },
                },
            },
            MuiListItemButton: {
                styleOverrides: {
                    root: {
                        borderRadius: 8,
                        '&.Mui-selected': {
                            backgroundColor: alpha(isLight ? ACCENT_DARK_BLUE : ACCENT_LIGHT_BLUE, 0.16),
                            color: isLight ? ACCENT_DARK_BLUE : ACCENT_LIGHT_BLUE,
                            '& .MuiListItemIcon-root': { color: isLight ? ACCENT_DARK_BLUE : ACCENT_LIGHT_BLUE },
                            '&:hover': {
                                backgroundColor: alpha(isLight ? ACCENT_DARK_BLUE : ACCENT_LIGHT_BLUE, 0.24),
                            },
                        },
                    },
                },
            },
            MuiButton: {
                styleOverrides: {
                    root: { boxShadow: 'none' },
                    contained: { '&:hover': { boxShadow: 'none' } },
                },
            },
        },
    };
}

export function createAppTheme(mode: PaletteMode) {
    return createTheme(getDesignTokens(mode));
}
