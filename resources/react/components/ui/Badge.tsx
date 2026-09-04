import type { ReactNode } from 'react';

type BadgeVariant = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

export default function Badge({ children, variant = 'neutral', className = '' }: { children: ReactNode; variant?: BadgeVariant; className?: string }) {
    return <span className={`ui-badge ui-badge-${variant} ${className}`}>{children}</span>;
}
