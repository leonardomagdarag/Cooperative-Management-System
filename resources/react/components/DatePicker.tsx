import { useCallback, useEffect, useRef, useState } from 'react';
import { DayPicker, type DayPickerProps } from 'react-day-picker';

function parseISODate(value: string | undefined): Date | undefined {
    if (!value) return undefined;
    const m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return undefined;
    return new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
}

function toISODate(d: Date): string {
    const y = d.getUTCFullYear();
    const mo = String(d.getUTCMonth() + 1).padStart(2, '0');
    const da = String(d.getUTCDate()).padStart(2, '0');
    return `${y}-${mo}-${da}`;
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function formatDisplay(value: string | undefined): string {
    if (!value) return '';
    const m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return value;
    return `${MONTHS[Number(m[2]) - 1]} ${Number(m[3])}, ${m[1]}`;
}

interface DatePickerProps {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    minDate?: string;
    maxDate?: string;
    clearable?: boolean;
    placeholder?: string;
    fromYear?: number;
    toYear?: number;
}

export default function DatePicker({ value, onChange, className = '', minDate, maxDate, clearable = true, placeholder = 'Select date', fromYear, toYear }: DatePickerProps) {
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [pos, setPos] = useState<{ top: number; left: number } | null>(null);

    const position = useCallback(() => {
        const el = triggerRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const calendarWidth = 320;
        const calendarHeight = 340;
        let left = rect.left;
        if (left + calendarWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - calendarWidth - 8);
        let top = rect.bottom + 4;
        if (top + calendarHeight > window.innerHeight - 8) top = Math.max(8, rect.top - calendarHeight - 4);
        setPos({ top, left });
    }, []);

    useEffect(() => {
        if (!open) return;
        position();
        const onScroll = () => setOpen(false);
        window.addEventListener('scroll', onScroll, true);
        return () => window.removeEventListener('scroll', onScroll, true);
    }, [open, position]);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const selected = parseISODate(value);
    const disabled: DayPickerProps['disabled'] = [
        ...(minDate ? [{ before: parseISODate(minDate)! }] : []),
        ...(maxDate ? [{ after: parseISODate(maxDate)! }] : []),
    ];
    const dayPickerProps: DayPickerProps = {
        mode: 'single',
        selected,
        timeZone: 'UTC',
        onSelect: (d?: Date) => onChange(d ? toISODate(d) : ''),
        autoFocus: true,
        disabled,
        ...(fromYear && toYear ? { captionLayout: 'dropdown' as const, fromYear, toYear } : {}),
        classNames: {
            months: 'flex',
            month: 'space-y-3',
            nav: 'flex items-center justify-between absolute inset-x-3 top-2',
            button_previous: 'h-8 w-8 rounded-full text-gray-500 hover:bg-gray-100 flex items-center justify-center',
            button_next: 'h-8 w-8 rounded-full text-gray-500 hover:bg-gray-100 flex items-center justify-center',
            chevron: 'h-4 w-4',
            month_caption: 'flex justify-center pt-1.5',
            month_grid: 'w-full border-collapse',
            weekdays: 'flex justify-around border-b border-gray-200 pb-1',
            weekday: 'text-xs font-medium text-gray-400 w-9 py-1 text-center',
            week: 'flex justify-around',
            day: 'w-9 py-1',
            day_button: 'h-8 w-8 text-sm text-gray-800 rounded-full hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-center',
            outside: 'text-gray-300 hover:bg-transparent hover:text-gray-300',
            disabled: 'text-gray-300 hover:bg-transparent hover:text-gray-300 cursor-not-allowed',
            today: 'text-emerald-600 font-semibold',
            caption_dropdowns: 'flex items-center justify-center gap-2 pt-1.5',
            dropdown_root: 'relative rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 hover:border-gray-300 hover:bg-gray-100',
            dropdown: 'absolute inset-0 w-full cursor-pointer opacity-0',
            caption_label: 'flex items-center gap-1 text-sm font-semibold text-gray-900',
            vhidden: 'sr-only',
        },
    };

    return (
        <div ref={wrapRef} className="relative">
            <button
                ref={triggerRef}
                type="button"
                onClick={() => setOpen(o => !o)}
                className={`flex items-center justify-between gap-2 text-left ${className}`}
            >
                <span className={value ? '' : 'text-gray-400'}>{value ? formatDisplay(value) : placeholder}</span>
                <span className="flex items-center gap-1 shrink-0">
                    {value && clearable && (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={(e) => { e.stopPropagation(); onChange(''); }}
                            className="rounded px-0.5 text-gray-400 hover:text-gray-600"
                        >
                            <i className="fas fa-times text-xs"></i>
                        </span>
                    )}
                    <i className="fas fa-calendar text-gray-400 text-xs"></i>
                </span>
            </button>
            {open && pos && (
                <div
                    className="fixed z-[80] rounded-xl border border-gray-200 bg-white p-3 shadow-2xl"
                    style={{ top: pos.top, left: pos.left }}
                >
                    <DayPicker {...dayPickerProps} />
                </div>
            )}
        </div>
    );
}
