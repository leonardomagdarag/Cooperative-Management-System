import usePaginatedRows from '../../hooks/usePaginatedRows';
import { useQuery } from '@tanstack/react-query';
import Pagination from '../../components/ui/Pagination';
import SortableHeader from '../../components/ui/SortableHeader';
import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import Button from '../../components/ui/Button';
import Badge from '../../components/ui/Badge';
import { useToast } from '../../contexts/ToastContext';
import Modal from '../../components/ui/Modal';
import ModalHeader from '../../components/ui/ModalHeader';
import DateRangeFilter from '../../components/ui/DateRangeFilter';

export default function ApprovalsQueueTab() {
    const { addToast } = useToast();
    const [selectedRun, setSelectedRun] = useState<any>(null);
    const itemPagination = usePaginatedRows<any>(selectedRun?.items ?? []);
    const [selectedIsStalled, setSelectedIsStalled] = useState(false);
    const [isApproving, setIsApproving] = useState(false);
    const [search, setSearch] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [sort, setSort] = useState('date');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');

    const [page, setPage] = useState(1);
    const sortFields: Record<string, string> = { date: 'collection_date', run: 'run_number', officer: 'officer', items: 'item_count', amount: 'total_amount', stage: 'current_stage' };
    const { data, isLoading: loading, isError, refetch: fetchQueues } = useQuery({
        queryKey: ['collection-approval-queue', page, search, from, to, sort, direction],
        queryFn: ({ signal }) => api.get('/api/collections/queues', {
            signal,
            params: { page, per_page: 15, search: search || undefined, from: from || undefined, to: to || undefined, sort: sortFields[sort], direction },
        }),
    });
    const runs: any[] = data?.data?.data ?? [];
    const stalledRuns: any[] = data?.data?.stalled ?? [];
    const pagination = data?.data;
    useEffect(() => {
        if (pagination && page > pagination.last_page) setPage(Math.max(1, pagination.last_page));
    }, [pagination, page]);

    const viewRun = async (runId: number, isStalled = false) => {
        try {
            const res = await api.get(`/api/collections/${runId}`);
            setSelectedRun(res.data.data);
            itemPagination.setPage(1);
            setSelectedIsStalled(isStalled);
        } catch (err) {
            addToast('error', 'Failed to load run details');
        }
    };

    const handleApproveReject = async (action: 'approved' | 'rejected') => {
        if (!selectedRun) return;
        
        let note = '';
        if (action === 'rejected') {
            note = prompt('Reason for rejection:') || '';
            if (!note) return;
        }

        setIsApproving(true);
        try {
            await api.post(`/api/collections/${selectedRun.id}/approve`, { action, note });
            addToast('success', `Run ${action} successfully`);
            setSelectedRun(null);
            fetchQueues();
        } catch (err: any) {
            addToast('error', err.response?.data?.message || `Failed to ${action} run`);
        } finally {
            setIsApproving(false);
        }
    };

    if (loading) return <div className="p-8 text-center text-slate-500"><i className="fa-solid fa-spinner fa-spin mr-2"></i>Loading queue...</div>;

    const handleColumnSort = (field: string) => {
        setDirection(sort === field && direction === 'asc' ? 'desc' : 'asc');
        setSort(field);
        setPage(1);
    };

    return (
        <div className="p-6">
            <h2 className="text-lg font-bold text-slate-800 mb-6">Runs Awaiting Approval</h2>
            <div className="table-toolbar mb-4">
                <input value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} placeholder="Search run number or officer..." aria-label="Search approval runs" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <DateRangeFilter from={from} to={to} onFromChange={value => { setFrom(value); setPage(1); }} onToChange={value => { setTo(value); setPage(1); }} />
            </div>

            {stalledRuns.length > 0 && (
                <section className="mb-6 rounded-lg border border-red-200 bg-red-50 p-4" aria-label="Stalled collection runs">
                    <h3 className="font-semibold text-red-800">Stalled runs need oversight</h3>
                    <p className="mt-1 text-sm text-red-700">No active user can act on the configured approval stage. Review the run and correct its workflow configuration.</p>
                    <ul className="mt-3 space-y-2">
                        {stalledRuns.map(run => (
                            <li key={run.id} className="flex items-center justify-between gap-3 rounded bg-white p-3">
                                <span className="font-mono text-sm text-slate-700">{run.run_number}</span>
                                <Button variant="secondary" size="sm" onClick={() => viewRun(run.id, true)}>Review stalled run</Button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            
            {isError ? <div role="alert" className="p-6 text-center text-red-700">Failed to load approvals. <button type="button" onClick={() => fetchQueues()} className="underline">Retry</button></div> : runs.length === 0 ? (
                <div className="text-center py-12 text-slate-500">
                    <i className="fa-solid fa-inbox text-4xl mb-3 text-slate-300"></i>
                    <p>No runs awaiting your approval.</p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[700px] text-left text-sm">
                        <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
                            <tr>
                                <SortableHeader field="run" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Run Number</SortableHeader>
                                <SortableHeader field="officer" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Officer</SortableHeader>
                                <SortableHeader field="date" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Date</SortableHeader>
                                <SortableHeader field="items" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Items</SortableHeader>
                                <SortableHeader field="amount" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Total Amount</SortableHeader>
                                <SortableHeader field="stage" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Stage</SortableHeader>
                                <th className="p-3 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {runs.map(run => (
                                <tr key={run.id} className="hover:bg-slate-50">
                                    <td className="p-3 font-mono text-slate-700">{run.run_number}</td>
                                    <td className="p-3">
                                        {run.loan_officer?.personal_data?.first_name} {run.loan_officer?.personal_data?.last_name}
                                    </td>
                                    <td className="p-3">
                                        {run.collection_date
                                            ? new Date(`${String(run.collection_date).slice(0, 10)}T00:00:00`).toLocaleDateString()
                                            : '—'}
                                        {run.is_late ? <Badge variant="red" className="ml-2">Late</Badge> : null}
                                    </td>
                                    <td className="p-3">{run.item_count}</td>
                                    <td className="p-3 text-emerald-600 font-semibold">₱{parseFloat(run.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                    <td className="p-3 text-slate-500">Stage {run.current_stage + 1}</td>
                                    <td className="p-3">
                                        <Button variant="secondary" size="sm" onClick={() => viewRun(run.id)}>
                                            Review
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {pagination && !isError && <Pagination page={page} lastPage={pagination.last_page} onChange={setPage} total={pagination.total} perPage={pagination.per_page} />}

            {/* Review Modal */}
            <Modal
                open={!!selectedRun}
                onClose={() => { setSelectedRun(null); setSelectedIsStalled(false); }}
                title="Review Collection Run"
                header={<ModalHeader title="Review Collection Run" onClose={() => { setSelectedRun(null); setSelectedIsStalled(false); }} />}
            >
                <div className="p-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <div className="text-sm text-slate-500">Run Number</div>
                            <div className="font-mono font-bold text-slate-800">{selectedRun?.run_number}</div>
                        </div>
                        <div>
                            <div className="text-sm text-slate-500">Total Collected</div>
                            <div className="font-semibold text-lg text-emerald-600">₱{parseFloat(selectedRun?.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                        </div>
                    </div>

                    <h3 className="font-semibold text-slate-800 mb-3">Collected Items ({selectedRun?.items?.length})</h3>
                    <div className="max-h-64 overflow-y-auto border border-slate-200 rounded-lg mb-6">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th className="p-2 font-semibold">Member</th>
                                    <th className="p-2 font-semibold">Loan</th>
                                    <th className="p-2 font-semibold text-right">Amount</th>
                                    <th className="p-2 font-semibold text-center">Visited</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {itemPagination.rows.map((item: any) => (
                                    <tr key={item.id}>
                                        <td className="p-2">{item.member?.personal_data?.first_name} {item.member?.personal_data?.last_name}</td>
                                        <td className="p-2 text-slate-500">{item.loan?.loan_number}</td>
                                        <td className="p-2 text-right text-emerald-600 font-semibold">{'\u20b1'}{parseFloat(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}{Number(item.fee_amount) > 0 && <div className="text-xs font-normal text-amber-700">+ {'\u20b1'}{Number(item.fee_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })} fee</div>}</td>
                                        <td className="p-2 text-center text-slate-500">
                                            {item.visited ? <i className="fa-solid fa-check text-emerald-500"></i> : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={itemPagination.page} lastPage={itemPagination.lastPage} total={itemPagination.total} perPage={itemPagination.perPage} onChange={itemPagination.setPage} scrollToTop={false} />

                    <div className="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                        <Button variant="secondary" onClick={() => setSelectedRun(null)}>Cancel</Button>
                        {!selectedIsStalled && <Button 
                            variant="danger" 
                            disabled={isApproving}
                            onClick={() => handleApproveReject('rejected')}
                        >
                            Reject Run
                        </Button>}
                        {!selectedIsStalled && <Button 
                            variant="primary" 
                            disabled={isApproving}
                            onClick={() => handleApproveReject('approved')}
                        >
                            {isApproving ? 'Approving...' : 'Approve Run'}
                        </Button>}
                    </div>
                </div>
            </Modal>
        </div>
    );
}
