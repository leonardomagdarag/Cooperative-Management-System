import { useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { helpCopy } from '../../lib/helpCopy';

/** Short, contextual guidance. The copy is always Taglish for both UI languages. */
export default function HelpTip({ text, label = 'Paliwanag / More info', inverted = false }: { text: string; label?: string; inverted?: boolean }) {
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState({ top: 0, left: 0, width: 288, above: false });
    const buttonRef = useRef<HTMLButtonElement>(null);
    const tooltipRef = useRef<HTMLSpanElement>(null);
    const tooltipId = useId();

    useLayoutEffect(() => {
        if (!open) return;
        const button = buttonRef.current;
        if (!button) return;

        const updatePosition = () => {
            const rect = button.getBoundingClientRect();
            const width = Math.min(288, window.innerWidth - 32);
            const left = Math.max(16, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 16));
            const tooltipHeight = tooltipRef.current?.offsetHeight ?? 120;
            const above = rect.bottom + tooltipHeight + 8 > window.innerHeight && rect.top - tooltipHeight - 8 >= 0;
            setPosition({ top: above ? rect.top - 8 : rect.bottom + 8, left, width, above });
        };

        const close = (event: PointerEvent) => {
            if (!button.contains(event.target as Node) && !tooltipRef.current?.contains(event.target as Node)) setOpen(false);
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
        };
        const onScroll = () => setOpen(false);

        updatePosition();
        document.addEventListener('pointerdown', close);
        document.addEventListener('keydown', onKeyDown);
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', onScroll, true);
        return () => {
            document.removeEventListener('pointerdown', close);
            document.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', onScroll, true);
        };
    }, [open]);

    return (
        <span className="inline-flex align-middle">
            <button
                ref={buttonRef}
                type="button"
                aria-label={label}
                aria-expanded={open}
                aria-describedby={open ? tooltipId : undefined}
                onClick={event => { event.stopPropagation(); setOpen(value => !value); }}
                className={`inline-flex h-5 w-5 items-center justify-center rounded-full border text-xs font-semibold focus-visible:outline-2 focus-visible:outline-emerald-600 ${inverted ? 'border-white/70 text-white hover:bg-white/20' : 'border-gray-300 text-gray-500 hover:border-emerald-500 hover:text-emerald-700'}`}
            >?</button>
            {open && createPortal(
                <span
                    ref={tooltipRef}
                    id={tooltipId}
                    role="tooltip"
                    className="fixed z-[100] rounded-lg bg-gray-900 px-3 py-2 text-left text-xs font-normal leading-relaxed text-white shadow-lg"
                    style={{ top: position.top, left: position.left, width: position.width, transform: position.above ? 'translateY(-100%)' : undefined }}
                >{helpCopy[text] ?? text}</span>,
                document.body,
            )}
        </span>
    );
}
