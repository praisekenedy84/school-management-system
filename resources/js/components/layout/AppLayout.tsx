import { useState, type ReactNode } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { Bell, ChevronRight, LogOut, Moon, Sun } from 'lucide-react';
import { useAuth } from '@/app/AuthProvider';
import { canAccessWithPermissions } from '@/lib/permissions';
import { useNavigationMenu } from '@/lib/useNavigation';
import { useColorMode } from '@/theme/ColorModeProvider';
import { ImpersonationBanner } from '@/features/platform/components/ImpersonationBanner';
import { cn } from '@/lib/utils';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

const SIDEBAR_WIDTH = 260;

function getInitials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Authenticated shell with a dark sidebar and clean content area — built with
 * shadcn/ui primitives. Menu items are permission-filtered from `/me`.
 */
export function AppLayout({ children }: { children: ReactNode }) {
    const { user, logout } = useAuth();
    const { mode, toggleColorMode } = useColorMode();
    const navigate = useNavigate();
    const location = useLocation();
    const [mobileOpen, setMobileOpen] = useState(false);

    const handleLogout = async () => {
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

    const sidebarContent = (
        <>
            <div className="flex flex-col items-center gap-3 px-4 py-6">
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm">
                    <span className="text-xl font-bold text-sidebar">S</span>
                </div>
                <p className="text-center text-sm font-semibold leading-tight text-sidebar-foreground">
                    School Management
                </p>
            </div>

            <Separator className="bg-sidebar-border" />

            <ScrollArea className="flex-1 px-3 py-4">
                {navLoading && visibleSections.length === 0 ? (
                    <p className="px-3 py-2 text-sm text-sidebar-foreground/60">Loading menu…</p>
                ) : (
                    <nav className="space-y-6">
                        {visibleSections.map((section) => (
                            <div key={section.label}>
                                <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-sidebar-foreground/50">
                                    {section.label}
                                </p>
                                <ul className="space-y-1">
                                    {section.items.map((item) => {
                                        const isActive = location.pathname === item.path;
                                        return (
                                            <li key={item.path}>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        navigate(item.path);
                                                        setMobileOpen(false);
                                                    }}
                                                    className={cn(
                                                        'group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                                        isActive
                                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                                            : 'text-sidebar-foreground/80 hover:bg-sidebar-border/50 hover:text-sidebar-foreground',
                                                    )}
                                                >
                                                    <span className={cn(isActive ? 'text-sidebar-accent-foreground' : 'text-sidebar-foreground/70')}>
                                                        {item.icon}
                                                    </span>
                                                    <span className="flex-1 text-left">{item.label}</span>
                                                    {isActive && <ChevronRight className="h-4 w-4 opacity-70" />}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        ))}
                    </nav>
                )}
            </ScrollArea>
        </>
    );

    return (
        <div className="flex min-h-screen bg-background">
                {/* Desktop sidebar */}
                <aside
                    className="fixed inset-y-0 left-0 z-30 hidden flex-col bg-sidebar md:flex"
                    style={{ width: SIDEBAR_WIDTH }}
                >
                    {sidebarContent}
                </aside>

                {/* Mobile sidebar overlay */}
                {mobileOpen && (
                    <div className="fixed inset-0 z-40 md:hidden">
                        <button
                            type="button"
                            className="absolute inset-0 bg-black/50"
                            aria-label="Close menu"
                            onClick={() => setMobileOpen(false)}
                        />
                        <aside
                            className="absolute inset-y-0 left-0 flex w-[260px] flex-col bg-sidebar shadow-xl"
                        >
                            {sidebarContent}
                        </aside>
                    </div>
                )}

                {/* Main column */}
                <div className="flex min-h-screen flex-1 flex-col md:ml-[260px]">
                    <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b bg-card px-4 sm:px-6">
                        <div className="flex items-center gap-3">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="md:hidden"
                                onClick={() => setMobileOpen(true)}
                                aria-label="Open menu"
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </Button>
                            <h2 className="text-lg font-semibold text-foreground">
                                {currentItem?.label ?? 'Dashboard'}
                            </h2>
                        </div>

                        <div className="flex items-center gap-2">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon" onClick={toggleColorMode} aria-label="Toggle theme">
                                        {mode === 'light' ? <Moon className="h-4 w-4" /> : <Sun className="h-4 w-4" />}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{mode === 'light' ? 'Dark mode' : 'Light mode'}</TooltipContent>
                            </Tooltip>

                            <Button variant="ghost" size="icon" aria-label="Notifications">
                                <Bell className="h-4 w-4" />
                            </Button>

                            {user && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" className="gap-2 px-2">
                                            <Avatar className="h-8 w-8">
                                                <AvatarFallback className="bg-primary text-xs text-primary-foreground">
                                                    {getInitials(user.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="hidden text-sm font-medium sm:inline">{user.name}</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56">
                                        <DropdownMenuLabel>
                                            <p className="font-medium">{user.name}</p>
                                            <p className="text-xs font-normal text-muted-foreground">{user.email}</p>
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem onClick={handleLogout}>
                                            <LogOut className="mr-2 h-4 w-4" />
                                            Log out
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    </header>

                    <main className="flex-1 bg-background p-4 sm:p-6">
                        <ImpersonationBanner />
                        {children}
                    </main>
                </div>
        </div>
    );
}
