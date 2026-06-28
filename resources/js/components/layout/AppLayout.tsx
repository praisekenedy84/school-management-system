import { useState, type ReactNode } from 'react';
import {
    AppBar,
    Avatar,
    Box,
    Divider,
    Drawer,
    IconButton,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    Menu,
    MenuItem,
    Toolbar,
    Tooltip,
    Typography,
} from '@mui/material';
import { ChevronLeft, ChevronRight, LogOut, Sun, Moon } from 'lucide-react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/AuthProvider';
import { canAccessWithPermissions } from '../../lib/permissions';
import { useNavigationMenu } from '../../lib/useNavigation';
import { useColorMode } from '../../theme/ColorModeProvider';
import { ImpersonationBanner } from '../../features/platform/components/ImpersonationBanner';

const DRAWER_WIDTH_EXPANDED = 280;
const DRAWER_WIDTH_COLLAPSED = 88;

function getInitials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Authenticated shell: topbar + a collapsible, permission-filtered sidebar.
 * Menu items appear when the user holds at least one mapped Spatie permission
 * from `/me` — including permissions granted directly by an admin. Hiding
 * items is UX only; the API still authorizes every request (RULES §8).
 */
export function AppLayout({ children }: { children: ReactNode }) {
    const { user, logout } = useAuth();
    const { mode, toggleColorMode } = useColorMode();
    const navigate = useNavigate();
    const location = useLocation();
    const [collapsed, setCollapsed] = useState(false);
    const [menuAnchor, setMenuAnchor] = useState<HTMLElement | null>(null);

    const drawerWidth = collapsed ? DRAWER_WIDTH_COLLAPSED : DRAWER_WIDTH_EXPANDED;

    const handleLogout = async () => {
        setMenuAnchor(null);
        await logout();
        navigate('/login', { replace: true });
    };

    const { data: navSections = [], isLoading: navLoading } = useNavigationMenu();

    const visibleSections = navSections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => canAccessWithPermissions(user, item.permissions)),
        }))
        .filter((section) => section.items.length > 0);

    const currentItem = visibleSections.flatMap((s) => s.items).find((item) => item.path === location.pathname);

    return (
        <Box sx={{ display: 'flex' }}>
            <AppBar
                position="fixed"
                sx={{
                    width: `calc(100% - ${drawerWidth}px)`,
                    ml: `${drawerWidth}px`,
                    transition: (t) => t.transitions.create(['width', 'margin'], { duration: t.transitions.duration.shorter }),
                }}
            >
                <Toolbar sx={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Typography variant="h6" noWrap component="div">
                        {currentItem?.label ?? 'School Management System'}
                    </Typography>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                        <Tooltip title={mode === 'light' ? 'Switch to dark mode' : 'Switch to light mode'}>
                            <IconButton onClick={toggleColorMode} size="small">
                                {mode === 'light' ? <Moon size={20} /> : <Sun size={20} />}
                            </IconButton>
                        </Tooltip>
                        {user && (
                            <>
                                <Typography variant="body2" color="text.secondary" noWrap>
                                    {user.name}
                                </Typography>
                                <IconButton onClick={(e) => setMenuAnchor(e.currentTarget)} size="small">
                                    <Avatar sx={{ width: 36, height: 36, bgcolor: 'primary.main', fontSize: 14 }}>
                                        {getInitials(user.name)}
                                    </Avatar>
                                </IconButton>
                            </>
                        )}
                    </Box>
                </Toolbar>
            </AppBar>

            <Drawer
                variant="permanent"
                sx={{
                    width: drawerWidth,
                    flexShrink: 0,
                    whiteSpace: 'nowrap',
                    transition: (t) => t.transitions.create('width', { duration: t.transitions.duration.shorter }),
                    [`& .MuiDrawer-paper`]: {
                        width: drawerWidth,
                        boxSizing: 'border-box',
                        overflowX: 'hidden',
                        display: 'flex',
                        flexDirection: 'column',
                        transition: (t) => t.transitions.create('width', { duration: t.transitions.duration.shorter }),
                    },
                }}
            >
                <Box
                    sx={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 1.5,
                        justifyContent: collapsed ? 'center' : 'flex-start',
                        px: 2.5,
                        minHeight: 64,
                    }}
                >
                    <Avatar variant="rounded" sx={{ bgcolor: 'primary.main', width: 36, height: 36 }}>
                        S
                    </Avatar>
                    {!collapsed && (
                        <Typography variant="subtitle1" noWrap>
                            School Mgmt
                        </Typography>
                    )}
                </Box>
                <Divider />

                <List sx={{ flexGrow: 1, px: collapsed ? 0.5 : 1.5, py: 1 }}>
                    {navLoading && visibleSections.length === 0 ? (
                        <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 1 }}>
                            Loading menu…
                        </Typography>
                    ) : (
                        visibleSections.map((section) => (
                        <Box key={section.label} sx={{ mb: 1.5 }}>
                            {!collapsed && (
                                <ListSubheader
                                    sx={{
                                        bgcolor: 'transparent',
                                        fontSize: 11,
                                        fontWeight: 700,
                                        letterSpacing: 0.6,
                                        color: 'text.disabled',
                                        lineHeight: 2.5,
                                    }}
                                >
                                    {section.label.toUpperCase()}
                                </ListSubheader>
                            )}
                            {section.items.map((item) => {
                                const button = (
                                    <ListItemButton
                                        key={item.path}
                                        selected={location.pathname === item.path}
                                        onClick={() => navigate(item.path)}
                                        sx={{ justifyContent: collapsed ? 'center' : 'flex-start', minHeight: 44 }}
                                    >
                                        <ListItemIcon sx={{ minWidth: collapsed ? 0 : 36, justifyContent: 'center' }}>
                                            {item.icon}
                                        </ListItemIcon>
                                        {!collapsed && (
                                            <ListItemText
                                                primary={item.label}
                                                primaryTypographyProps={{ fontSize: 14, fontWeight: 500 }}
                                            />
                                        )}
                                    </ListItemButton>
                                );
                                return collapsed ? (
                                    <Tooltip key={item.path} title={item.label} placement="right">
                                        {button}
                                    </Tooltip>
                                ) : (
                                    button
                                );
                            })}
                        </Box>
                        ))
                    )}
                </List>

                <Box sx={{ display: 'flex', justifyContent: collapsed ? 'center' : 'flex-end', p: 1 }}>
                    <IconButton size="small" onClick={() => setCollapsed((c) => !c)}>
                        {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
                    </IconButton>
                </Box>
            </Drawer>

            <Box component="main" sx={{ flexGrow: 1, p: 3, minHeight: '100vh' }}>
                <Toolbar />
                <ImpersonationBanner />
                {children}
            </Box>

            <Menu anchorEl={menuAnchor} open={Boolean(menuAnchor)} onClose={() => setMenuAnchor(null)}>
                {user && (
                    <Box sx={{ px: 2, py: 1, minWidth: 200 }}>
                        <Typography variant="subtitle2" noWrap>
                            {user.name}
                        </Typography>
                        <Typography variant="body2" color="text.secondary" noWrap>
                            {user.email}
                        </Typography>
                    </Box>
                )}
                <Divider />
                <MenuItem onClick={handleLogout}>
                    <ListItemIcon>
                        <LogOut size={18} />
                    </ListItemIcon>
                    Log out
                </MenuItem>
            </Menu>
        </Box>
    );
}
