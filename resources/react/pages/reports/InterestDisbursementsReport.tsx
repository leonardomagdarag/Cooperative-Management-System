import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

interface DisbursementRow {
    id: number;
    account_type: string;
    account_label: string;
    rate: number;
    interval: string;
    period_label: string;
    member_count: number;
    skipped_count: number;
    total_amount: number;
    status: string;
    trigger: string;
    requested_by: string | null;
    approved_by: string | null;
    created_at: string;
}

interface DisbursementItemRow {
    id: number;
    member_name: string;
    account_number: string | null;
    amount: number;
    balance_before: number;
    balance_after: number;
    skipped: boolean;
    skip_reason: string | null;
    transaction_id: number | null;
    created_at: string;
}

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDateTime = (iso?: string | null) => (iso ? new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—');

export default function InterestDisbursementsReport({ branchId }: { branchId: string }) {
    const [page, setPage] = useState(1);
    const [accountType, setAccountType] = useState('');
    const [selected, setSelected] = useState<DisbursementRow | null>(null);
    const [detailPage, setDetailPage] = useState(1);

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['reports-interest-disbursements', branchId, page, accountType],
        queryFn: () => api.get('/api/reports/interest-disbursements', { params: { page, branch_id: branchId || undefined, account_type: accountType || undefined } }),
    });

    const { data: detailData, isLoading: detailLoading } = useQuery({
        queryKey: ['reports-interest-disbursement', selected?.id, branchId, detailPage],
        queryFn: () => api.get(`/api/reports/interest-disbursements/${selected!.id}`, { params: { page: detailPage, branch_id: branchId || undefined } }),
        enabled: !!selected,
    });

    const rows: DisbursementRow[] = data?.data?.data ?? [];
    const meta = data?.data?.meta ?? null;
    const items: DisbursementItemRow[] = detailData?.data?.data ?? [];
    const detailMeta = detailData?.data?.meta ?? null;

    return (
        <div className="p-4 bg-white border-b border-gray-200">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-gray-900">Interest Disbursements</h2>
                    <p className="text-xs text-gray-500">Each batch shows which members received interest and how much.</p>
                </div>
                <div className="flex items-center gap-2">
                    <label htmlFor="interest-account-type" className="text-xs font-medium text-gray-500">Account</label>
                    <select
                        id="interest-account-type"
                        value={accountType}
                        onChange={e => { setAccountType(e.target.value); setPage(1); }}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="">All</option>
                        <option value="savings">Savings</option>
                        <option value="share-capital">Share Capital</option>
                    </select>
                </div>
            </div>

            {isError ? (
                <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">Failed to load interest disbursements: {(error as any)?.message || 'Unknown error'}</div>
            ) : (
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Account</th>
                                <th className="px-3 py-2">Period</th>
                                <th className="px-3 py-2">Rate</th>
                                <th className="px-3 py-2 text-right">Members</th>
                                <th className="px-3 py-2 text-right">Skipped</th>
                                <th className="px-3 py-2 text-right">Total</th>
                                <th className="px-3 py-2">Trigger</th>
                                <th className="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                [...Array(4)].map((_, i) => (
                                    <tr key={i} className="border-b border-gray-100"><td colSpan={9} className="px-3 py-3"><div className="h-4 animate-pulse rounded bg-gray-100" /></td></tr>
                                ))
                            ) : rows.length === 0 ? (
                                <tr><td colSpan={9} className="px-3 py-6 text-center text-sm text-gray-500">No interest batches yet.</td></tr>
                            ) : rows.map(row => (
                                <tr key={row.id} className="border-b border-gray-100 hover:bg-gray-50">
                                    <td className="px-3 py-2 text-gray-700">{formatDateTime(row.created_at)}</td>
                                    <td className="px-3 py-2 text-gray-700">{row.account_label}</td>
                                    <td className="px-3 py-2 text-gray-700">{row.period_label}</td>
                                    <td className="px-3 py-2 text-gray-700">{row.rate}% / {row.interval}</td>
                                    <td className="px-3 py-2 text-right text-gray-700">{row.member_count}</td>
                                    <td className="px-3 py-2 text-right text-gray-500">{row.skipped_count}</td>
                                    <td className="px-3 py-2 text-right font-medium text-gray-900">{peso(row.total_amount)}</td>
                                    <td className="px-3 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${row.trigger === 'manual' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>{row.trigger}</span>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <button type="button" onClick={() => { setSelected(row); setDetailPage(1); }} className="text-sm font-medium text-blue-600 hover:text-blue-800">View</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {meta && meta.last_page > 1 && (
                <div className="mt-3 flex items-center justify-between text-sm text-gray-600">
                    <span>Page {meta.current_page} of {meta.last_page} · {meta.total} batches</span>
                    <div className="flex gap-2">
                        <button type="button" disabled={meta.current_page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))} className="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-40">Previous</button>
                        <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => setPage(p => p + 1)} className="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-40">Next</button>
                    </div>
                </div>
            )}

            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => setSelected(null)}>
                    <div className="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <div>
                                <h3 className="font-semibold text-gray-900">{selected.account_label} interest · {selected.period_label}</h3>
                                <p className="mt-0.5 text-sm text-gray-500">{selected.member_count} credited · {selected.skipped_count} skipped · {peso(selected.total_amount)} total</p>
                            </div>
                            <button type="button" onClick={() => setSelected(null)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100" aria-label="Close"><i className="fas fa-times"></i></button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-5">
                            {detailLoading ? (
                                <div className="space-y-2">{[...Array(5)].map((_, i) => <div key={i} className="h-8 animate-pulse rounded bg-gray-100" />)}</div>
                            ) : (
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                                            <th className="px-2 py-2">Member</th>
                                            <th className="px-2 py-2">Account</th>
                                            <th className="px-2 py-2 text-right">Interest</th>
                                            <th className="px-2 py-2 text-right">Balance after</th>
                                            <th className="px-2 py-2">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.length === 0 ? (
                                            <tr><td colSpan={5} className="px-2 py-6 text-center text-gray-500">No lines in this batch.</td></tr>
                                        ) : items.map(item => (
                                            <tr key={item.id} className="border-b border-gray-100">
                                                <td className="px-2 py-2 text-gray-700">{item.member_name}</td>
                                                <td className="px-2 py-2 text-gray-500">{item.account_number || '—'}</td>
                                                <td className="px-2 py-2 text-right font-medium text-gray-900">{peso(item.amount)}</td>
                                                <td className="px-2 py-2 text-right text-gray-700">{peso(item.balance_after)}</td>
                                                <td className="px-2 py-2">
                                                    {item.skipped
                                                        ? <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{item.skip_reason || 'Skipped'}</span>
                                                        : <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Credited</span>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        {detailMeta && detailMeta.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm text-gray-600">
                                <span>Page {detailMeta.current_page} of {detailMeta.last_page} · {detailMeta.total} lines</span>
                                <div className="flex gap-2">
                                    <button type="button" disabled={detailMeta.current_page <= 1} onClick={() => setDetailPage(p => Math.max(1, p - 1))} className="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-40">Previous</button>
                                    <button type="button" disabled={detailMeta.current_page >= detailMeta.last_page} onClick={() => setDetailPage(p => p + 1)} className="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-40">Next</button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
