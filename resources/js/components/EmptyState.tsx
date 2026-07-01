import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface EmptyStateProps {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}

/** Centered empty-state panel for list pages with no records yet. */
export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('flex flex-col items-center justify-center py-20 text-center', className)}>
            {icon ?? <KoalaIllustration className="mb-6 h-40 w-40 text-muted-foreground/40" />}
            <h3 className="text-lg font-semibold text-foreground">{title}</h3>
            {description && <p className="mt-2 max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

function KoalaIllustration({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden>
            <ellipse cx="80" cy="130" rx="50" ry="8" fill="currentColor" opacity="0.15" />
            <path
                d="M55 95c-8-20 5-45 25-45s33 25 25 45"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <circle cx="80" cy="55" r="28" stroke="currentColor" strokeWidth="2" />
            <circle cx="68" cy="50" r="4" fill="currentColor" />
            <circle cx="92" cy="50" r="4" fill="currentColor" />
            <path d="M74 62 Q80 68 86 62" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <circle cx="58" cy="42" r="10" stroke="currentColor" strokeWidth="2" />
            <circle cx="102" cy="42" r="10" stroke="currentColor" strokeWidth="2" />
            <path d="M40 70 Q30 60 35 50" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path d="M120 70 Q130 60 125 50" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <text x="105" y="35" fill="currentColor" fontSize="12" fontWeight="600" opacity="0.5">
                zzz
            </text>
        </svg>
    );
}
