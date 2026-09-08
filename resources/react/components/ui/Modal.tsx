import { useEffect, type ReactNode } from 'react';
import Button from './Button';

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl';

const maxWidths: Record<ModalSize, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-2xl',
    xl: 'max-w-3xl',
    '2xl': 'max-w-5xl',
};

export interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    size?: ModalSize;
    footer?: ReactNode;
    header?: ReactNode;
    fixed?: boolean;
    headerClassName?: string;
    bodyClassName?: string;
    overlayClassName?: string;
}

export default function Modal({
    open,
    onClose,
    title,
    children,
    size = 'md',
    footer,
    header,
    fixed = false,
    headerClassName = '',
    bodyClassName = '',
    overlayClassName = '',
}: ModalProps) {
    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);
        return () => { document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', onKeyDown); };
    }, [open, onClose]);

    if (!open) return null;

    const heightClass = fixed ? 'h-[90vh]' : 'max-h-[90vh]';

    return (
        <div className={`ui-modal-backdrop ${overlayClassName}`} role="presentation" onMouseDown={onClose}>
            <section
                className={`ui-modal ${maxWidths[size]} ${heightClass} ${fixed ? 'flex flex-col overflow-hidden' : 'overflow-y-auto'}`}
                role="dialog"
                aria-modal="true"
                aria-labelledby="ui-modal-title"
                onMouseDown={event => event.stopPropagation()}
            >
                {header ?? (
                    <header className="ui-modal-header shrink-0">
                        <h2 id="ui-modal-title" className="ui-modal-title">{title}</h2>
                        <Button variant="ghost" size="sm" onClick={onClose} aria-label="Close dialog" className="ui-modal-close"><i className="fas fa-times" /></Button>
                    </header>
                )}
                <div className={`${fixed ? 'flex-1 overflow-y-auto' : ''} ui-modal-body ${bodyClassName}`}>{children}</div>
                {footer && <footer className="ui-modal-footer shrink-0">{footer}</footer>}
            </section>
        </div>
    );
}
