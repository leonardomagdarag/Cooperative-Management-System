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
    const selected = parseISODate(value);
    const initialMonth = selected || parseISODate(maxDate) || new Date();
    const [viewMonth, setViewMonth] = useState(initialMonth);
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
        const onResize = () => position();
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
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

    useEffect(() => {
        if (selected) setViewMonth(selected);
    }, [value]);

    const viewYear = viewMonth.getUTCFullYear();
    const viewMonthNumber = viewMonth.getUTCMonth();
    const firstYear = fromYear ?? Math.min(viewYear - 100, new Date().getFullYear() - 10);
    const lastYear = toYear ?? Math.max(viewYear + 10, new Date().getFullYear() + 10);
    const monthOptions = MONTHS.map((label, index) => ({ label, value: index }));
    const yearOptions = Array.from({ length: lastYear - firstYear + 1 }, (_, index) => firstYear + index);
    const todayISO = toISODate(new Date());
    const canSelectToday = (!minDate || todayISO >= minDate) && (!maxDate || todayISO <= maxDate);
    const disabled: DayPickerProps['disabled'] = [
        ...(minDate ? [{ before: parseISODate(minDate)! }] : []),
        ...(maxDate ? [{ after: parseISODate(maxDate)! }] : []),
    ];
    const dayPickerProps: DayPickerProps = {
        mode: 'single',
        selected,
        month: viewMonth,
        onMonthChange: setViewMonth,
        timeZone: 'UTC',
        onSelect: (d?: Date) => { onChange(d ? toISODate(d) : ''); if (d) setOpen(false); },
        disabled,
        classNames: {
            months: 'flex',
            month: 'space-y-3',
            // The month/year controls above the calendar must not be covered by the navigation layer.
            nav: 'absolute inset-x-0 top-0 z-10 flex items-center justify-between px-1',
            button_previous: 'h-8 w-8 rounded-lg text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-center transition-colors',
            button_next: 'h-8 w-8 rounded-lg text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-center transition-colors',
            chevron: 'h-4 w-4',
            month_caption: 'relative flex justify-center pt-1',
            month_grid: 'w-full border-collapse',
            weekdays: 'flex justify-around border-b border-gray-200 pb-1',
            weekday: 'text-xs font-medium text-gray-400 w-9 py-1 text-center',
            week: 'flex justify-around',
            day: 'w-9 py-1',
            day_button: 'h-8 w-8 text-sm text-gray-800 rounded-lg hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-center transition-colors',
            outside: 'text-gray-300 hover:bg-transparent hover:text-gray-300',
            disabled: 'text-gray-300 hover:bg-transparent hover:text-gray-300 cursor-not-allowed',
            today: 'text-emerald-600 font-semibold',
            caption_dropdowns: 'flex items-center justify-center gap-2 pt-1',
            dropdown_root: 'rounded-md border border-gray-200 bg-white px-1.5 py-1 hover:border-emerald-400 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-100',
            dropdown: 'cursor-pointer bg-transparent text-sm font-semibold text-gray-900 outline-none',
            caption_label: 'hidden',
            vhidden: 'sr-only',
        },
    };

    return (
        <div ref={wrapRef} className="relative">
            <button
                ref={triggerRef}
                type="button"
                onClick={() => setOpen(o => !o)}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-label={value ? `Selected date ${formatDisplay(value)}` : placeholder}
                className={`flex items-center justify-between gap-2 text-left ${className}`}
            >
                <span className={value ? '' : 'text-gray-400'}>{value ? formatDisplay(value) : placeholder}</span>
                <span className="flex items-center gap-1 shrink-0">
                    {value && clearable && (
                        <span
                            role="button"
                            tabIndex={0}
                            aria-label="Clear date"
                            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); onChange(''); } }}
                            onClick={(e) => { e.stopPropagation(); onChange(''); }}
                            className="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <i className="fas fa-times text-xs"></i>
                        </span>
                    )}
                    <i className="fas fa-calendar text-gray-400 text-xs"></i>
                </span>
            </button>
            {open && pos && (
                <div
                    role="dialog"
                    aria-label="Choose date"
                    className="fixed z-[80] max-w-[calc(100vw-1rem)] rounded-xl border border-gray-200 bg-white p-3 shadow-2xl"
                    style={{ top: pos.top, left: pos.left }}
                >
                    <div className="mb-3 flex items-center justify-center gap-2 border-b border-gray-100 pb-3">
                        <label className="sr-only" htmlFor="date-picker-month">Choose month</label>
                        <select
                            id="date-picker-month"
                            value={viewMonthNumber}
                            onChange={event => setViewMonth(new Date(Date.UTC(viewYear, Number(event.target.value), 1)))}
                            className="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm font-semibold text-gray-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >
                            {monthOptions.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                        <label className="sr-only" htmlFor="date-picker-year">Choose year</label>
                        <select
                            id="date-picker-year"
                            value={viewYear}
                            onChange={event => setViewMonth(new Date(Date.UTC(Number(event.target.value), viewMonthNumber, 1)))}
                            className="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm font-semibold text-gray-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >
                            {yearOptions.map(year => <option key={year} value={year}>{year}</option>)}
                        </select>
                    </div>
                    <DayPicker {...dayPickerProps} />
                    <div className="mt-2 flex items-center justify-between border-t border-gray-100 pt-2">
                        <button type="button" onClick={() => { onChange(''); setOpen(false); }} disabled={!value || !clearable} className="rounded-md px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Clear</button>
                        <button type="button" onClick={() => { onChange(todayISO); setOpen(false); }} disabled={!canSelectToday} className="rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40">Today</button>
                    </div>
                </div>
            )}
        </div>
    );
}
