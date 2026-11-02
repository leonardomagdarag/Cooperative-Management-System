import usePaginatedRows from '../../hooks/usePaginatedRows';
import SortableHeader from '../../components/ui/SortableHeader';
import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import Button from '../../components/ui/Button';
import Badge from '../../components/ui/Badge';
import { useToast } from '../../contexts/ToastContext';
import Modal from '../../components/ui/Modal';
import ModalHeader from '../../components/ui/ModalHeader';
import Pagination from '../../components/ui/Pagination';
import DateRangeFilter from '../../components/ui/DateRangeFilter';

export default function RunHistoryTab({ canViewAll }: { canViewAll: boolean }) {
    const { addToast } = useToast();
    const [runs, setRuns] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [sort, setSort] = useState('collection_date');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [selectedRun, setSelectedRun] = useState<any>(null);
    const itemPagination = usePaginatedRows<any>(selectedRun?.items ?? []);
    const [availableOfficers, setAvailableOfficers] = useState<any[]>([]);
    const [replacementOfficerId, setReplacementOfficerId] = useState('');
    const [actionReason, setActionReason] = useState('');
    const canManageRuns = canViewAll;

    useEffect(() => {
        fetchHistory(1);
    }, [statusFilter, search, from, to, sort, direction]);

    const fetchHistory = async (page: number) => {
        setLoading(true);
        try {
            const params = new URLSearchParams({ page: String(page), per_page: '15', sort, direction });
            if (statusFilter) params.set('status', statusFilter);
            if (search.trim()) params.set('search', search.trim());
            if (from) params.set('from', from);
            if (to) params.set('to', to);
            const res = await api.get(`/api/collections?${params.toString()}`);
            setRuns(res.data.data);
            setPagination({
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                total: res.data.total
            });
        } catch (err) {
            addToast('error', 'Failed to load run history');
        } finally {
            setLoading(false);
        }
    };

    const viewRun = async (runId: number) => {
        try {
            const res = await api.get(`/api/collections/${runId}`);
            setSelectedRun(res.data.data);
            itemPagination.setPage(1);
            setActionReason('');
            setReplacementOfficerId('');
            if (canManageRuns) {
                const officers = await api.get('/api/collections/officers');
                setAvailableOfficers(officers.data.data);
                const currentOfficer = officers.data.data.find((officer: any) => officer.id === res.data.data.loan_officer_id);
                setReplacementOfficerId(String(currentOfficer?.id ?? officers.data.data[0]?.id ?? ''));
            }
        } catch (err) {
            addToast('error', 'Failed to load run details');
        }
    };

    const manageRun = async (action: 'replacement' | 'reassign' | 'void' | 'retry') => {
        if (!selectedRun) return;
        try {
            if (action === 'replacement') {
                const response = await api.post(`/api/collections/${selectedRun.id}/replacement`, {
                    loan_officer_id: replacementOfficerId || undefined,
                });
                addToast('success', `Created linked run ${response.data.data.run_number}`);
            } else if (action === 'reassign') {
                await api.post(`/api/collections/${selectedRun.id}/reassign`, {
                    loan_officer_id: replacementOfficerId,
                    reason: actionReason,
                });
                addToast('success', 'Orphaned run reassigned');
            } else if (action === 'void') {
                await api.post(`/api/collections/${selectedRun.id}/void`, { reason: actionReason });
                addToast('success', 'Orphaned run voided');
            } else {
                await api.post(`/api/collections/${selectedRun.id}/retry`, { reason: actionReason });
                addToast('success', 'Failed collection items retried');
            }
            setSelectedRun(null);
            fetchHistory(1);
        } catch (err: any) {
            addToast('error', err.response?.data?.message || 'Run action failed');
        }
    };

    const handleColumnSort = (field: string) => {
        setDirection(sort === field && direction === 'asc' ? 'desc' : 'asc');
        setSort(field);
    };

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-lg font-bold text-slate-800">Run History</h2>
                
                <div className="table-toolbar">
                <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search run number or officer..." aria-label="Search collection runs" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <DateRangeFilter from={from} to={to} onFromChange={setFrom} onToChange={setTo} />
                <select value={`${sort}:${direction}`} onChange={e => { const [field, order] = e.target.value.split(':'); setSort(field); setDirection(order as 'asc' | 'desc'); }} aria-label="Sort collection runs" className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="collection_date:desc">Newest date</option><option value="collection_date:asc">Oldest date</option><option value="run_number:asc">Run number A–Z</option><option value="run_number:desc">Run number Z–A</option><option value="status:asc">Status A–Z</option><option value="status:desc">Status Z–A</option>
                </select>
                <select 
                    className="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                    value={statusFilter}
                    onChange={e => setStatusFilter(e.target.value)}
                >
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="in_progress">In Progress</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="partially_applied">Partially Applied</option>
                    <option value="voided">Voided</option>
                </select>
                </div>
            </div>

            {loading ? (
                <div className="text-center py-8 text-slate-500"><i className="fa-solid fa-spinner fa-spin mr-2"></i>Loading history...</div>
            ) : runs.length === 0 ? (
                <div className="text-center py-12 text-slate-500 bg-slate-50 rounded-lg">
                    <p>No collection runs found.</p>
                </div>
            ) : (
                <>
                    <div className="overflow-x-auto border border-slate-200 rounded-lg mb-4">
                        <table className="w-full min-w-[700px] text-left text-sm">
                            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
                                <tr>
                                    <SortableHeader field="run_number" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Run Number</SortableHeader>
                                    {canViewAll && <SortableHeader field="officer" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Officer</SortableHeader>}
                                    <SortableHeader field="collection_date" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Date</SortableHeader>
                                    <SortableHeader field="item_count" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Items</SortableHeader>
                                    <SortableHeader field="total_amount" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Total Amount</SortableHeader>
                                    <SortableHeader field="status" sort={sort} direction={direction} onSort={handleColumnSort} className="p-3 font-semibold">Status</SortableHeader>
                                    <th className="p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {runs.map(run => (
                                    <tr key={run.id} className="hover:bg-slate-50">
                                        <td className="p-3 font-mono text-slate-700">{run.run_number}</td>
                                        {canViewAll && (
                                            <td className="p-3">
                                                {run.loan_officer?.personal_data?.first_name} {run.loan_officer?.personal_data?.last_name}
                                            </td>
                                        )}
                                        <td className="p-3">{run.collection_date ? new Date(`${String(run.collection_date).slice(0, 10)}T00:00:00`).toLocaleDateString() : '—'}</td>
                                        <td className="p-3">{run.item_count}</td>
                                        <td className="p-3 font-medium">₱{parseFloat(run.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                        <td className="p-3">
                                            <Badge variant={
                                                run.status === 'approved' ? 'emerald' :
                                                run.status === 'rejected' || run.status === 'voided' ? 'red' :
                                                run.status === 'draft' ? 'slate' : 'blue'
                                            }>
                                                {run.status.replace('_', ' ')}
                                            </Badge>
                                            {run.orphaned_at && <Badge variant="red" className="ml-2">Officer inactive</Badge>}
                                        </td>
                                        <td className="p-3">
                                            <Button variant="secondary" size="sm" onClick={() => viewRun(run.id)}>
                                                View
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    
                    <Pagination
                        page={pagination.current_page}
                        lastPage={pagination.last_page}
                        onChange={fetchHistory}
                        total={pagination.total}
                        perPage={15}
                    />
                </>
            )}

            {/* Run Details Modal */}
            <Modal
                open={!!selectedRun}
                onClose={() => setSelectedRun(null)}
                title="Collection Run Details"
                header={<ModalHeader title="Collection Run Details" onClose={() => setSelectedRun(null)} />}
            >
                <div className="p-6">
                    <div className="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <div className="text-sm text-slate-500">Run Number</div>
                            <div className="font-mono font-bold text-slate-800">{selectedRun?.run_number}</div>
                            <div className="text-sm text-slate-500 mt-2">Status</div>
                            <div className="font-semibold capitalize text-slate-800">{selectedRun?.status.replace('_', ' ')}</div>
                        </div>
                        <div>
                            <div className="text-sm text-slate-500">Total Collected</div>
                            <div className="font-semibold text-lg text-emerald-600">₱{parseFloat(selectedRun?.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                            <div className="text-sm text-slate-500 mt-2">Items</div>
                            <div className="font-semibold text-slate-800">{selectedRun?.items?.length}</div>
                        </div>
                    </div>

                    <h3 className="font-semibold text-slate-800 mb-3">Collected Items</h3>
                    <div className="max-h-64 overflow-y-auto border border-slate-200 rounded-lg">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th className="p-2 font-semibold">Member</th>
                                    <th className="p-2 font-semibold text-right">Amount</th>
                                <th className="p-2 font-semibold text-center">Outcome</th>
                                <th className="p-2 font-semibold">Failure reason</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {itemPagination.rows.map((item: any) => (
                                    <tr key={item.id}>
                                        <td className="p-2">
                                            <div>{item.member?.personal_data?.first_name} {item.member?.personal_data?.last_name}</div>
                                            <div className="text-xs text-slate-500">{item.loan?.loan_number}</div>
                                        </td>
                                        <td className="p-2 text-right font-medium">₱{parseFloat(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                        <td className="p-2 text-center">
                                            <Badge variant={
                                                item.outcome === 'applied' ? 'emerald' : 
                                                item.outcome === 'failed' ? 'red' : 'slate'
                                            }>
                                                {item.outcome}
                                            </Badge>
                                        </td>
                                        <td className="p-2 text-xs text-red-700">{item.failure_reason || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={itemPagination.page} lastPage={itemPagination.lastPage} total={itemPagination.total} perPage={itemPagination.perPage} onChange={itemPagination.setPage} scrollToTop={false} />

                    {canManageRuns && (selectedRun?.status === 'rejected' || (selectedRun?.status === 'draft' && selectedRun?.orphaned_at)) && (
                        <section className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 className="font-semibold text-slate-800">
                                {selectedRun?.status === 'rejected' ? 'Create linked replacement' : 'Resolve inactive officer run'}
                            </h3>
                            <label className="mt-3 block text-sm font-medium text-slate-700">Assigned loan officer</label>
                            <select value={replacementOfficerId} onChange={e => setReplacementOfficerId(e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select officer</option>
                                {availableOfficers.map(officer => <option key={officer.id} value={officer.id}>{officer.name || `Officer ${officer.id}`}{officer.branch_name ? ` · ${officer.branch_name}` : ''}</option>)}
                            </select>
                            {selectedRun?.status === 'draft' && selectedRun?.orphaned_at && (
                                <>
                                    <label className="mt-3 block text-sm font-medium text-slate-700">Reason</label>
                                    <textarea value={actionReason} onChange={e => setActionReason(e.target.value)} rows={2} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                </>
                            )}
                            <div className="mt-3 flex justify-end gap-2">
                                {selectedRun?.status === 'rejected' ? (
                                    <Button variant="primary" disableOnClick disabled={!replacementOfficerId} onClick={() => manageRun('replacement')}>Create replacement</Button>
                                ) : (
                                    <>
                                        <Button variant="danger" disableOnClick disabled={actionReason.trim().length < 5} onClick={() => manageRun('void')}>Void run</Button>
                                        <Button variant="primary" disableOnClick disabled={!replacementOfficerId || actionReason.trim().length < 5} onClick={() => manageRun('reassign')}>Reassign run</Button>
                                    </>
                                )}
                            </div>
                        </section>
                    )}

                    {canManageRuns && selectedRun?.status === 'partially_applied' && (
                        <section className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h3 className="font-semibold text-slate-800">Retry failed items</h3>
                            <p className="mt-1 text-sm text-slate-600">Applied items remain unchanged. The retry revalidates only the failed items and records this action.</p>
                            <label className="mt-3 block text-sm font-medium text-slate-700">Reason</label>
                            <textarea value={actionReason} onChange={e => setActionReason(e.target.value)} rows={2} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                            <div className="mt-3 flex justify-end">
                                <Button variant="primary" disableOnClick disabled={actionReason.trim().length < 5} onClick={() => manageRun('retry')}>Retry failed items</Button>
                            </div>
                        </section>
                    )}
                </div>
            </Modal>
        </div>
    );
}
