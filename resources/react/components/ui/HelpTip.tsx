import { useState } from 'react';

/** Short, contextual guidance for actions whose effects are not obvious. */
export default function HelpTip({ text, label = 'More information' }: { text: string; label?: string }) {
    const [open, setOpen] = useState(false);
    return (
        <span className="relative inline-flex align-middle">
            <button type="button" aria-label={label} aria-expanded={open} onClick={() => setOpen(value => !value)} className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 text-xs font-semibold text-gray-500 hover:border-emerald-500 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">?</button>
            {open && <span role="tooltip" className="absolute left-1/2 top-full z-40 mt-2 w-64 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-left text-xs font-normal leading-relaxed text-white shadow-lg">{text}</span>}
        </span>
    );
}
