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
import {
    LayoutDashboard,
    GraduationCap,
    BookOpen,
    ClipboardList,
    CalendarCheck,
    ListChecks,
    Star,
    FileText,
    Receipt,
    Wallet,
    ClipboardCheck,
    FileSpreadsheet,
    Landmark,
    ChevronLeft,
    ChevronRight,
    LogOut,
    Sun,
    Moon,
    LayoutGrid,
    CalendarRange,
    UsersRound,
    Building2,
    BedDouble,
    UtensilsCrossed,
    CalendarOff,
    DoorOpen,
    ShieldCheck,
    History,
} from 'lucide-react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/AuthProvider';
import { FINANCE_STAFF_ROLES } from '../../routes/RequireFinanceStaff';
import { HOSTEL_STAFF_ROLES } from '../../routes/RequireHostelStaff';
import { useColorMode } from '../../theme/ColorModeProvider';
import { ImpersonationBanner } from '../../features/platform/components/ImpersonationBanner';

const DRAWER_WIDTH_EXPANDED = 280;
const DRAWER_WIDTH_COLLAPSED = 88;

/**
 * Roles for whom Academic Sessions / Teacher Assignments are real workflow
 * items, not just nav clutter — both pages are API-view-open to everyone
 * (ClassRoomPolicy/AcademicSessionPolicy/TeacherAssignmentPolicy::viewAny all
 * return true), but a teacher or parent has no actual use for them, so they
 * stay out of those roles' sidebars (UX only — RULES §8). Mirrors how
 * FINANCE_STAFF_ROLES gates the Finance section's staff-only items.
 */
const ACADEMIC_ADMIN_ROLES = ['tenant_admin', 'school_admin', 'academic_director'];

type NavItem = { label: string; path: string; icon: JSX.Element; roles: string[] | null };

const NAV_SECTIONS = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', path: '/', icon: <LayoutDashboard size={20} />, roles: null }],
    },
    {
        label: 'Academics',
        items: [
            { label: 'Students', path: '/students', icon: <GraduationCap size={20} />, roles: null },
            { label: 'Classes', path: '/classes', icon: <LayoutGrid size={20} />, roles: null },
            {
                label: 'Academic Sessions',
                path: '/academic-sessions',
                icon: <CalendarRange size={20} />,
                roles: ACADEMIC_ADMIN_ROLES,
            },
            {
                label: 'Teacher Assignments',
                path: '/teacher-assignments',
                icon: <UsersRound size={20} />,
                roles: ACADEMIC_ADMIN_ROLES,
            },
            { label: 'Subjects', path: '/subjects', icon: <BookOpen size={20} />, roles: null },
            { label: 'Assignments', path: '/assignments', icon: <ClipboardList size={20} />, roles: null },
            { label: 'Attendance', path: '/attendance', icon: <CalendarCheck size={20} />, roles: null },
            { label: 'Assessments', path: '/assessments', icon: <ListChecks size={20} />, roles: null },
            { label: 'Mark Entry', path: '/assessments/marks', icon: <Star size={20} />, roles: null },
            { label: 'Report Cards', path: '/report-cards', icon: <FileText size={20} />, roles: null },
        ],
    },
    {
        label: 'Finance',
        items: [
            { label: 'Submit Payment Slip', path: '/finance/submit-slip', icon: <Wallet size={20} />, roles: null },
            { label: 'My Payment Slips', path: '/finance/my-slips', icon: <Receipt size={20} />, roles: null },
            {
                label: 'Verification Queue',
                path: '/finance/verification-queue',
                icon: <ClipboardCheck size={20} />,
                roles: FINANCE_STAFF_ROLES,
            },
            {
                label: 'Fee Structures',
                path: '/finance/fee-structures',
                icon: <FileSpreadsheet size={20} />,
                roles: FINANCE_STAFF_ROLES,
            },
            {
                label: 'Payment Methods',
                path: '/finance/payment-methods',
                icon: <Landmark size={20} />,
                roles: FINANCE_STAFF_ROLES,
            },
        ],
    },
    {
        label: 'Platform',
        items: [
            { label: 'Tenants', path: '/platform/tenants', icon: <ShieldCheck size={20} />, roles: null },
            { label: 'Audit Log', path: '/platform/audit-logs', icon: <History size={20} />, roles: null },
        ],
    },
    {
        label: 'Hostel',
        items: [
            { label: 'Hostels', path: '/hostels', icon: <Building2 size={20} />, roles: HOSTEL_STAFF_ROLES },
            {
                label: 'Hostel Rooms',
                path: '/hostel-rooms',
                icon: <BedDouble size={20} />,
                roles: HOSTEL_STAFF_ROLES,
            },
            {
                label: 'Allocations',
                path: '/hostel-allocations',
                icon: <DoorOpen size={20} />,
                roles: [...HOSTEL_STAFF_ROLES, 'parent'],
            },
            {
                label: 'Meal Plans',
                path: '/meal-plans',
                icon: <UtensilsCrossed size={20} />,
                roles: HOSTEL_STAFF_ROLES,
            },
            {
                label: 'Leave Requests',
                path: '/hostel-leave-requests',
                icon: <CalendarOff size={20} />,
                roles: [...HOSTEL_STAFF_ROLES, 'parent'],
            },
        ],
    },
] satisfies Array<{ label: string; items: NavItem[] }>;

function getInitials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Authenticated shell: topbar + a collapsible, section-grouped sidebar.
 * Navigation is currently unconditional per-section (all authenticated users
 * see all sections); the API still authorizes every action server-side
 * (RULES §8). Finer per-item permission gating can be added once permission
 * names are finalized for these modules.
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

    // A Platform Admin is a central account, not scoped to any tenant
    // (ADR-0008) — every other section is tenant-scoped and would just 404,
    // so they see the Platform section only. A tenant user never sees
    // Platform (not gated by role — there is no role check that would hide
    // it from a tenant_admin otherwise).
    const isPlatformAdmin = user?.type === 'platform_admin';

    // RULES §8: permissions drive nav visibility (UX only — the API still
    // authorizes every request server-side). A parent never sees the
    // finance-staff-only items (verification queue, fee/payment-method config).
    const visibleSections = NAV_SECTIONS.filter((section) => (section.label === 'Platform') === isPlatformAdmin)
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => item.roles === null || item.roles.some((role) => user?.roles.includes(role)),
            ),
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
                    {visibleSections.map((section) => (
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
                    ))}
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
