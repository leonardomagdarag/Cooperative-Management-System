import { useCallback, useEffect, useRef, useState, type ChangeEvent } from 'react';
import { DayPicker, type DayPickerProps } from 'react-day-picker';

import Button from './ui/Button';
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
const FULL_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

function formatDisplay(value: string | undefined): string {
    if (!value) return '';
    const m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return value;
    return `${MONTHS[Number(m[2]) - 1]} ${Number(m[3])}, ${m[1]}`;
}

function toUTCDate(year: number, monthIndex: number, day: number): Date | undefined {
    const d = new Date(Date.UTC(year, monthIndex, day));
    if (d.getUTCFullYear() !== year || d.getUTCMonth() !== monthIndex || d.getUTCDate() !== day) return undefined;
    return d;
}

function monthFromWord(word: string): number | undefined {
    const w = word.toLowerCase();
    const idx = FULL_MONTHS.findIndex(name => name.toLowerCase().startsWith(w));
    return idx >= 0 ? idx : undefined;
}

function parseFlexibleDate(raw: string): Date | undefined {
    const s = raw.trim();
    if (!s) return undefined;

    let m = s.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
    if (m) return toUTCDate(Number(m[1]), Number(m[2]) - 1, Number(m[3]));

    m = s.match(/^(\d{1,2})[/.](\d{1,2})[/.](\d{4})$/);
    if (m) return toUTCDate(Number(m[3]), Number(m[1]) - 1, Number(m[2]));

    m = s.match(/^([A-Za-z]+)[\s,]+(\d{1,2})[\s,]+(\d{4})$/);
    if (m) {
        const mi = monthFromWord(m[1]);
        if (mi !== undefined) return toUTCDate(Number(m[3]), mi, Number(m[2]));
    }

    m = s.match(/^(\d{1,2})[\s,]+([A-Za-z]+)[\s,]+(\d{4})$/);
    if (m) {
        const mi = monthFromWord(m[2]);
        if (mi !== undefined) return toUTCDate(Number(m[3]), mi, Number(m[1]));
    }

    return undefined;
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
    inputId?: string;
    ariaInvalid?: boolean;
    ariaDescribedBy?: string;
}

export default function DatePicker({ value, onChange, className = '', minDate, maxDate, clearable = true, placeholder = 'Select date', fromYear, toYear, inputId, ariaInvalid, ariaDescribedBy }: DatePickerProps) {
    const [open, setOpen] = useState(false);
    const [text, setText] = useState<string>(() => (value ? formatDisplay(value) : ''));
    const selected = parseISODate(value);
    const initialMonth = selected || parseISODate(maxDate) || new Date();
    const [viewMonth, setViewMonth] = useState(initialMonth);
    const wrapRef = useRef<HTMLDivElement>(null);
    const anchorRef = useRef<HTMLInputElement>(null);
    const popupRef = useRef<HTMLDivElement>(null);
    const [pos, setPos] = useState<{ top: number; left: number } | null>(null);

    const position = useCallback(() => {
        const el = anchorRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const calendarWidth = popupRef.current ? popupRef.current.offsetWidth : 320;
        const calendarHeight = popupRef.current ? popupRef.current.offsetHeight : 340;
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
        const id = requestAnimationFrame(position);
        return () => cancelAnimationFrame(id);
    }, [open, position, viewMonth]);

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
        setText(value ? formatDisplay(value) : '');
        const s = parseISODate(value);
        if (s) setViewMonth(s);
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

    const handleTextChange = (e: ChangeEvent<HTMLInputElement>) => {
        const raw = e.target.value;
        setText(raw);
        const clean = raw.trim();
        if (clean === '') {
            onChange('');
            return;
        }
        const parsed = parseFlexibleDate(clean);
        if (parsed) onChange(toISODate(parsed));
    };

    const handleBlur = () => {
        const clean = text.trim();
        const parsed = parseFlexibleDate(clean);
        if (!parsed || toISODate(parsed) !== value) setText(value ? formatDisplay(value) : '');
    };

    return (
        <div ref={wrapRef} className="relative">
            <div className="relative">
                <input
                    id={inputId}
                    ref={anchorRef}
                    type="text"
                    value={text}
                    onChange={handleTextChange}
                    onBlur={handleBlur}
                    onKeyDown={(e) => { if (e.key === 'Escape') setOpen(false); }}
                    placeholder={placeholder}
                    autoComplete="off"
                    aria-haspopup="dialog"
                    aria-expanded={open}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                    aria-label={value ? `Selected date ${formatDisplay(value)}` : placeholder}
                    className={`pr-8 ${className}`}
                />
                <div className="absolute inset-y-0 right-0 flex items-center gap-0.5 pr-2">
                    {value && clearable && (
                        <button
                            type="button"
                            aria-label="Clear date"
                            title="Clear date"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={(e) => { e.stopPropagation(); onChange(''); }}
                            className="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <i className="fas fa-times text-xs"></i>
                        </button>
                    )}
                    <button
                        type="button"
                        aria-label="Open calendar"
                        title="Open calendar"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => setOpen(o => !o)}
                        className="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <i className={`fas fa-calendar text-xs ${open ? 'text-emerald-600' : ''}`}></i>
                    </button>
                </div>
            </div>
            {open && pos && (
                <div
                    ref={popupRef}
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
