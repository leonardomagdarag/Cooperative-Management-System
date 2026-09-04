import type { ReactNode } from 'react';

export default function EmptyState({ icon = 'fa-inbox', title, description, action }: { icon?: string; title: string; description?: string; action?: ReactNode }) {
    return <div className="ui-empty-state"><i className={`fas ${icon} ui-empty-icon`} /><h3>{title}</h3>{description && <p>{description}</p>}{action}</div>;
}
