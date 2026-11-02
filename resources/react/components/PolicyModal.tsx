import { useEffect, useRef } from 'react';

import Button from './ui/Button';
export default function PolicyModal({ title, content, onClose }: { title: string; content?: string; onClose: () => void }) {
    const dialogRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        dialogRef.current?.focus();
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            previouslyFocused?.focus();
        };
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/60 p-4" onMouseDown={event => { if (event.target === event.currentTarget) onClose(); }}>
            <section ref={dialogRef} tabIndex={-1} role="dialog" aria-modal="true" aria-label={title} className="relative max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl focus:outline-none md:p-8">
                <button type="button" onClick={onClose} aria-label="Close policy" className="absolute right-5 top-5 rounded p-2 text-slate-500 hover:bg-slate-100">
                    <i className="fas fa-times" aria-hidden="true"></i>
                </button>
                <h2 className="mb-5 pr-10 text-2xl font-bold text-slate-900">{title}</h2>
                {content ? <div className="space-y-3 text-sm leading-relaxed text-slate-700" dangerouslySetInnerHTML={{ __html: content }} /> : <p className="text-sm text-slate-600">Policy content is unavailable. Please contact the cooperative.</p>}
            </section>
        </div>
    );
}
