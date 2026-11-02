import type { ReactNode } from 'react';

interface PageHeaderProps {
    title: ReactNode;
    description?: ReactNode;
    eyebrow?: string;
    icon?: string;
    action?: ReactNode;
    level?: 'h1' | 'h2';
    className?: string;
}

export default function PageHeader({ title, description, eyebrow, icon, action, level = 'h1', className = '' }: PageHeaderProps) {
    const Heading = level;

    return (
        <header className={`rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6 ${className}`}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-start gap-4">
                    {icon && <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true"><i className={icon} /></span>}
                    <div className="min-w-0">
                        {eyebrow && <p className="text-xs font-medium uppercase tracking-wider text-emerald-700">{eyebrow}</p>}
                        <Heading className="flex items-center gap-2 text-2xl font-medium tracking-tight text-slate-900">{title}</Heading>
                        {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
                    </div>
                </div>
                {action && <div className="flex shrink-0 flex-wrap items-center gap-2">{action}</div>}
            </div>
        </header>
    );
}
