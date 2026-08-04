import { useEffect, type ReactNode } from 'react';
import Button from './Button';

export default function Modal({ open, onClose, title, children, size = 'md', footer }: { open: boolean; onClose: () => void; title: string; children: ReactNode; size?: 'sm' | 'md' | 'lg' | 'xl'; footer?: ReactNode }) {
    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);
        return () => { document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', onKeyDown); };
    }, [open, onClose]);

    if (!open) return null;
    return (
        <div className="ui-modal-backdrop" role="presentation" onMouseDown={onClose}>
            <section className={`ui-modal ui-modal-${size}`} role="dialog" aria-modal="true" aria-labelledby="ui-modal-title" onMouseDown={event => event.stopPropagation()}>
                <header className="ui-modal-header">
                    <h2 id="ui-modal-title" className="ui-modal-title">{title}</h2>
                    <Button variant="ghost" size="sm" onClick={onClose} aria-label="Close dialog" className="ui-modal-close"><i className="fas fa-times" /></Button>
                </header>
                <div className="ui-modal-body">{children}</div>
                {footer && <footer className="ui-modal-footer">{footer}</footer>}
            </section>
        </div>
    );
}
