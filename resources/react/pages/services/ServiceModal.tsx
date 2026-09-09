import type { ReactNode } from 'react';

interface ServiceModalProps {
    gradient: string;
    title: string;
    subtitle?: ReactNode;
    subtitleClassName?: string;
    backButton?: ReactNode;
    stepIndicator?: ReactNode;
    sticky?: boolean;
    headerRowClassName?: string;
    maxWidth?: string;
    onClose: () => void;
    children: ReactNode;
}

export default function ServiceModal({
    gradient,
    title,
    subtitle,
    subtitleClassName = 'text-sm mt-1',
    backButton,
    stepIndicator,
    sticky,
    headerRowClassName = 'flex items-center justify-between mb-4',
    maxWidth = 'max-w-md',
    onClose,
    children,
}: ServiceModalProps) {
    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" onClick={onClose}>
            <div className="absolute inset-0 bg-black opacity-25" />
            <div
                className={`z-10 bg-white rounded-2xl shadow-2xl ${maxWidth} w-full max-h-[90vh] overflow-y-auto`}
                onClick={e => e.stopPropagation()}
            >
                <div className={`bg-gradient-to-r ${gradient} p-6 text-white ${sticky ? 'sticky top-0 z-10' : ''}`}>
                    <div className={headerRowClassName}>
                        {backButton ? (
                            <div className="flex items-center gap-3">
                                {backButton}
                                <div>
                                    <h3 className="text-2xl font-bold">{title}</h3>
                                    {subtitle && <p className={subtitleClassName}>{subtitle}</p>}
                                </div>
                            </div>
                        ) : (
                            <div>
                                <h3 className="text-2xl font-bold">{title}</h3>
                                {subtitle && <p className={subtitleClassName}>{subtitle}</p>}
                            </div>
                        )}
                        <button onClick={onClose} className="text-white hover:text-gray-200">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    {stepIndicator}
                </div>
                {children}
            </div>
        </div>
    );
}