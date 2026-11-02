import type { ReactNode } from 'react';

import Button from './Button';
interface ModalHeaderProps {
    title: ReactNode;
    subtitle?: ReactNode;
    onClose: () => void;
    gradientClassName?: string;
    children?: ReactNode;
}

export default function ModalHeader({ title, subtitle, onClose, gradientClassName = 'from-emerald-600 to-cyan-600', children }: ModalHeaderProps) {
    return (
        <div className={`bg-linear-to-r ${gradientClassName} px-6 py-5 text-white shrink-0`}>
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-bold">{title}</h2>
                    {subtitle && <p className="text-sm text-white/80">{subtitle}</p>}
                </div>
                <button onClick={onClose} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white transition-colors" aria-label="Close dialog">
                    <i className="fas fa-times"></i>
                </button>
            </div>
            {children}
        </div>
    );
}