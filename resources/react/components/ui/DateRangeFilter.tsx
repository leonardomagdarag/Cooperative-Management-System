import { useState } from 'react';

interface DateRangeFilterProps {
    from: string;
    to: string;
    onFromChange: (value: string) => void;
    onToChange: (value: string) => void;
    onRangeChange?: (from: string, to: string) => void;
}
type Preset = 'all' | 'today' | 'week' | 'month' | 'year' | 'custom';
const inputClass = 'rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-100';
const labelClass = 'flex flex-col gap-1 text-xs font-normal text-gray-600';
const dateString = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

export function presetDates(preset: Exclude<Preset, 'custom'>, now = new Date()) {
    if (preset === 'all') return { from: '', to: '' };
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const end = new Date(start);
    if (preset === 'week') {
        start.setDate(start.getDate() - (start.getDay() + 6) % 7);
        end.setTime(start.getTime());
        end.setDate(end.getDate() + 6);
    } else if (preset === 'month') {
        start.setDate(1);
        end.setMonth(end.getMonth() + 1, 0);
    } else if (preset === 'year') {
        start.setMonth(0, 1);
        end.setMonth(11, 31);
    }
    return { from: dateString(start), to: dateString(end) };
}

export default function DateRangeFilter({ from, to, onFromChange, onToChange, onRangeChange }: DateRangeFilterProps) {
    const [selection, setSelection] = useState<{ preset: Preset; from: string; to: string }>(() => ({ preset: from || to ? 'custom' : 'all', from, to }));
    // Parent resets and restored URL filters take precedence over the local selection.
    const preset = selection.from === from && selection.to === to ? selection.preset : from || to ? 'custom' : 'all';
    const changeRange = (nextFrom: string, nextTo: string, nextPreset: Preset) => {
        setSelection({ preset: nextPreset, from: nextFrom, to: nextTo });
        if (onRangeChange) onRangeChange(nextFrom, nextTo);
        else {
            onFromChange(nextFrom);
            onToChange(nextTo);
        }
    };
    return (
        <div className="flex flex-wrap items-end gap-3" role="group" aria-label="Filter by date range">
            <label className={labelClass}>Period
                <select value={preset} className={inputClass} onChange={event => {
                    const next = event.target.value as Preset;
                    const dates = next === 'custom' ? { from, to } : presetDates(next);
                    changeRange(dates.from, dates.to, next);
                }}>
                    <option value="all">All time</option>
                    <option value="today">Today</option>
                    <option value="week">Weekly</option>
                    <option value="month">Monthly</option>
                    <option value="year">Yearly</option>
                    <option value="custom">Custom</option>
                </select>
            </label>
            {preset === 'custom' && <>
                <label className={labelClass}>From
                    <input type="date" value={from} max={to || undefined} onChange={event => changeRange(event.target.value, to, 'custom')} className={inputClass} />
                </label>
                <label className={labelClass}>To
                    <input type="date" value={to} min={from || undefined} onChange={event => changeRange(from, event.target.value, 'custom')} className={inputClass} />
                </label>
            </>}
        </div>
    );
}
