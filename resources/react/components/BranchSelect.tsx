import { useQuery } from '@tanstack/react-query';
import { getBranchOptions, BranchOption } from '../api/branches';

interface BranchSelectProps {
    value: number | string | undefined;
    onChange: (value: number | '') => void;
    required?: boolean;
    disabled?: boolean;
    className?: string;
    label?: string;
    error?: string;
    allowClear?: boolean;
    allLabel?: string;
}

export default function BranchSelect({
    value,
    onChange,
    required = false,
    disabled = false,
    className = '',
    label = 'Branch',
    error,
    allowClear = true,
    allLabel = 'No branch (head office)',
}: BranchSelectProps) {
    const { data, isLoading } = useQuery({
        queryKey: ['branch-options'],
        queryFn: () => getBranchOptions(),
        staleTime: 60_000,
    });

    const options: BranchOption[] = data?.data?.data ?? [];

    const base = 'w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:ring-2 disabled:bg-gray-100 disabled:text-gray-500';
    const border = error ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:border-blue-500 focus:ring-blue-100';

    return (
        <div>
            {label && (
                <label className="mb-1.5 block text-sm font-medium text-gray-700">
                    {label} {required && <span className="text-red-500">*</span>}
                </label>
            )}
            <div className="relative">
                <select
                    value={value ?? ''}
                    disabled={disabled || isLoading}
                    onChange={e => onChange(e.target.value ? Number(e.target.value) : '')}
                    className={`${base} ${border} ${className}`}
                >
                    {allowClear && <option value="">{isLoading ? 'Loading branches…' : allLabel}</option>}
                    {!allowClear && <option value="">Select a branch…</option>}
                    {options.map(opt => (
                        <option key={opt.id} value={opt.id}>
                            {opt.name}{opt.is_head_office ? ' (Head Office)' : ''}
                        </option>
                    ))}
                </select>
            </div>
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}