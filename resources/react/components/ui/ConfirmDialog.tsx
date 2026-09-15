import { useEffect, type ReactNode } from 'react';
import Button from './Button';

export interface ConfirmDialogProps {
    /** Canonical visibility prop */
    open?: boolean;
    /** Legacy alias for `open` – accepted so existing callers don't need changes */
    isOpen?: boolean;
    title: string;
    message: ReactNode;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'warning' | 'primary';
    onConfirm: () => void;
    /** Canonical cancel handler */
    onCancel?: () => void;
    /** Legacy alias for `onCancel` – accepted so existing callers don't need changes */
    onClose?: () => void;
    isLoading?: boolean;
}

const variantStyles = {
    danger: {
        icon: 'fas fa-exclamation-triangle',
        iconBg: 'bg-red-100 text-red-600',
        confirmVariant: 'danger' as const,
    },
    warning: {
        icon: 'fas fa-exclamation-circle',
        iconBg: 'bg-amber-100 text-amber-600',
        confirmVariant: 'primary' as const,
    },
    primary: {
        icon: 'fas fa-question-circle',
        iconBg: 'bg-emerald-100 text-emerald-600',
        confirmVariant: 'primary' as const,
    },
};

export default function ConfirmDialog({
    open,
    isOpen,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'danger',
    onConfirm,
    onCancel,
    onClose,
    isLoading = false,
}: ConfirmDialogProps) {
    // Support both canonical and legacy prop aliases
    const isVisible = open ?? isOpen ?? false;
    const handleCancel = onCancel ?? onClose ?? (() => {});

    useEffect(() => {
        if (!isVisible) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !isLoading) {
                handleCancel();
            }
        };
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.body.style.overflow = prevOverflow;
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isVisible, isLoading]);

    if (!isVisible) return null;

    const style = variantStyles[variant] || variantStyles.danger;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
            role="presentation"
            onClick={() => !isLoading && handleCancel()}
        >
            <div
                className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-200 animate__animated animate__fadeIn animate__faster"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-dialog-title"
                onClick={e => e.stopPropagation()}
            >
                <div className="p-6">
                    <div className="flex items-start gap-4">
                        <div
                            className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-lg ${style.iconBg}`}
                        >
                            <i className={style.icon} />
                        </div>
                        <div className="min-w-0 flex-1 pt-0.5">
                            <h3 id="confirm-dialog-title" className="text-base font-bold text-slate-900">
                                {title}
                            </h3>
                            <div className="mt-2 text-sm text-slate-600 leading-relaxed">
                                {message}
                            </div>
                        </div>
                    </div>
                </div>
                <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <Button
                        variant="secondary"
                        size="md"
                        disabled={isLoading}
                        onClick={handleCancel}
                    >
                        {cancelText}
                    </Button>
                    <Button
                        variant={style.confirmVariant}
                        size="md"
                        isLoading={isLoading}
                        loadingText="Processing..."
                        onClick={onConfirm}
                    >
                        {confirmText}
                    </Button>
                </div>
            </div>
        </div>
    );
}
