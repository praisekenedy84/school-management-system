import { type ReactNode } from 'react';
import {
    AppBar,
    Box,
    Button,
    Drawer,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    Toolbar,
    Typography,
} from '@mui/material';
import DashboardIcon from '@mui/icons-material/Dashboard';
import SchoolIcon from '@mui/icons-material/School';
import MenuBookIcon from '@mui/icons-material/MenuBook';
import AssignmentIcon from '@mui/icons-material/Assignment';
import EventAvailableIcon from '@mui/icons-material/EventAvailable';
import FactCheckIcon from '@mui/icons-material/FactCheck';
import GradeIcon from '@mui/icons-material/Grade';
import DescriptionIcon from '@mui/icons-material/Description';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/AuthProvider';

const DRAWER_WIDTH = 240;

const NAV_ITEMS = [
    { label: 'Dashboard', path: '/', icon: <DashboardIcon /> },
    { label: 'Students', path: '/students', icon: <SchoolIcon /> },
    { label: 'Subjects', path: '/subjects', icon: <MenuBookIcon /> },
    { label: 'Assignments', path: '/assignments', icon: <AssignmentIcon /> },
    { label: 'Attendance', path: '/attendance', icon: <EventAvailableIcon /> },
    { label: 'Assessments', path: '/assessments', icon: <FactCheckIcon /> },
    { label: 'Mark Entry', path: '/assessments/marks', icon: <GradeIcon /> },
    { label: 'Report Cards', path: '/report-cards', icon: <DescriptionIcon /> },
];

/**
 * Authenticated shell: AppBar + a fixed Drawer with module navigation.
 * Navigation is currently unconditional (all authenticated users see all
 * sections); the API still authorizes every action server-side (RULES §8).
 * Finer per-item permission gating can be added once permission names are
 * finalized for these modules.
 */
export function AppLayout({ children }: { children: ReactNode }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const handleLogout = async () => {
        await logout();
        navigate('/login', { replace: true });
    };

    return (
        <Box sx={{ display: 'flex' }}>
            <AppBar position="fixed" sx={{ zIndex: (t) => t.zIndex.drawer + 1 }}>
                <Toolbar sx={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Typography variant="h6" noWrap component="div">
                        School Management System
                    </Typography>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        {user && (
                            <Typography variant="body2" noWrap>
                                {user.name}
                            </Typography>
                        )}
                        <Button color="inherit" onClick={handleLogout}>
                            Log out
                        </Button>
                    </Box>
                </Toolbar>
            </AppBar>
            <Drawer
                variant="permanent"
                sx={{
                    width: DRAWER_WIDTH,
                    flexShrink: 0,
                    [`& .MuiDrawer-paper`]: { width: DRAWER_WIDTH, boxSizing: 'border-box' },
                }}
            >
                <Toolbar />
                <List>
                    {NAV_ITEMS.map((item) => (
                        <ListItemButton
                            key={item.path}
                            selected={location.pathname === item.path}
                            onClick={() => navigate(item.path)}
                        >
                            <ListItemIcon>{item.icon}</ListItemIcon>
                            <ListItemText primary={item.label} />
                        </ListItemButton>
                    ))}
                </List>
            </Drawer>
            <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
                <Toolbar />
                {children}
            </Box>
        </Box>
    );
}
