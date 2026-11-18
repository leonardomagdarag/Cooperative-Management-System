import { useEffect, useId, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useLanguage } from '../../contexts/LanguageContext';
import type { CoMaker } from './CoMakerList';

interface Props {
    borrowerId: number;
    requiredCount: number;
    members: CoMaker[];
    onChange: (members: CoMaker[]) => void;
    error?: string;
}

export default function CoMakerPicker({ borrowerId, requiredCount, members, onChange, error }: Props) {
    const { t } = useLanguage();
    const inputId = useId();
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    const term = search.trim();
    const limit = requiredCount || 20;
    const canAdd = borrowerId > 0 && members.length < limit;

    useEffect(() => {
        const timer = setTimeout(() => setQuery(term), 250);
        return () => clearTimeout(timer);
    }, [term]);

    const showResults = canAdd && term.length >= 2 && query === term;
    const { data, isPending, isError } = useQuery({
        queryKey: ['loan-co-maker-search', borrowerId, query],
        queryFn: async ({ signal }) => {
            const response = await api.get<{ data: CoMaker[] }>('/api/loans/search-members', {
                params: { q: query, co_maker_for: borrowerId },
                signal,
            });
            return response.data.data;
        },
        enabled: showResults,
    });
    const results = data?.filter(member => !members.some(selected => selected.id === member.id)) ?? [];

    return (
        <div className="space-y-2">
            <label htmlFor={inputId} className="block font-semibold text-gray-900">
                {t('Co-makers')}
                {requiredCount === 0 && <span className="ml-2 text-sm font-normal text-gray-500">{t('Optional')}</span>}
            </label>
            {requiredCount > 0 && (
                <p className="text-sm text-gray-600">
                    {requiredCount === 1 ? t('Add exactly 1 co-maker.') : t('Add exactly {count} co-makers.').replace('{count}', String(requiredCount))}
                </p>
            )}
            <input
                id={inputId}
                type="search"
                value={search}
                onChange={event => setSearch(event.target.value)}
                disabled={!canAdd}
                aria-invalid={!!error}
                aria-describedby={error ? `${inputId}-error` : undefined}
                placeholder={t(borrowerId ? 'Search active members by name or member number' : 'Select the borrower first')}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100"
            />
            {showResults && (
                results.length > 0 ? (
                    <ul className="max-h-48 divide-y overflow-y-auto rounded-lg border border-gray-200">
                        {results.map(member => (
                            <li key={member.id}>
                                <button
                                    type="button"
                                    onClick={() => { onChange([...members, member]); setSearch(''); }}
                                    className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                                >
                                    {member.name} <span className="text-gray-600">{member.user_number}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p role="status" className="text-sm text-gray-600">
                        {t(isError ? 'Member search failed' : isPending ? 'Searching members…' : 'No matching members')}
                    </p>
                )
            )}
            {members.length > 0 && (
                <ul className="space-y-2">
                    {members.map(member => (
                        <li key={member.id} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <span>{member.name} <span className="text-gray-600">{member.user_number}</span></span>
                            <button type="button" onClick={() => onChange(members.filter(selected => selected.id !== member.id))} className="text-red-700 hover:underline" aria-label={`${t('Remove')} ${member.name}`}>
                                {t('Remove')}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {error && <p id={`${inputId}-error`} className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
