import type { ReactNode } from 'react';

interface Props {
    field: string;
    sort: string;
    direction: string;
    onSort: (field: string) => void;
    children: ReactNode;
    className?: string;
    hint?: string;
    categorical?: boolean;
}

export default function SortableHeader({ field, sort, direction, onSort, children, className = '', hint, categorical = false }: Props) {
    const active = field === sort;
    return <th scope="col" className={className} aria-sort={active ? categorical ? 'other' : direction === 'asc' ? 'ascending' : 'descending' : 'none'}>
        <button type="button" onClick={() => onSort(field)} title={hint || `Sort by ${typeof children === 'string' ? children : field}`} className="table-sort-button">
            {children}
            <svg aria-hidden="true" className={`h-3.5 w-3.5 shrink-0 ${active ? 'text-emerald-700' : 'text-gray-400'}`} viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                <path d={active
                    ? categorical
                        ? 'M16 7a6 6 0 1 0 0 6 M16 3v4h-4'
                        : direction === 'asc'
                            ? 'M10 16V4 M5 9l5-5 5 5'
                            : 'M10 4v12 M5 11l5 5 5-5'
                    : 'M6 16V4 M3 7l3-3 3 3 M14 4v12 M11 13l3 3 3-3'} />
            </svg>
        </button>
    </th>;
}
