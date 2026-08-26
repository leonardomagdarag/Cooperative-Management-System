import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { validate, loanEncodeSchema, loanEditSchema, type FieldErrors } from '../../lib/validation';

export default function LoansPage() {
    const { user } = useAuth();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [selectedLoanId, setSelectedLoanId] = useState<number | null>(null);
    const [activeTab, setActiveTab] = useState('overview');
    const [showEncodeModal, setShowEncodeModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingLoan, setEditingLoan] = useState<any>(null);
    const [editForm, setEditForm] = useState({ loan_type: 'business', loan_amount: '', interest_rate: '3', term_months: '12', payment_frequency: 'monthly', loan_status: 'pending', due_date: '' });
    const [encodeForm, setEncodeForm] = useState({ user_id: '', loan_product: 'business', loan_type: 'business', loan_status: 'pending', loan_amount: '', interest_rate: '3', term_months: '12', payment_frequency: 'monthly', applied_at: new Date().toISOString().split('T')[0] });
    const [memberSearch, setMemberSearch] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [encodeErrors, setEncodeErrors] = useState<FieldErrors>({});
    const [editErrors, setEditErrors] = useState<FieldErrors>({});
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['loans', search, page, sort, direction],
        queryFn: () => api.get('/api/loans', { params: { search: search || undefined, page, per_page: 10, sort, direction } }),
    });

    const { data: loanDetail } = useQuery({
        queryKey: ['loan-detail', selectedLoanId],
        queryFn: () => api.get(`/api/loans/${selectedLoanId}`),
        enabled: !!selectedLoanId,
    });

    const { data: productsData } = useQuery({
        queryKey: ['loan-products-options'],
        queryFn: () => api.get('/api/loans/loan-products'),
    });

    const encodeMutation = useMutation({
        mutationFn: (payload: any) => api.post('/api/loans', payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); setShowEncodeModal(false); addToast('Loan encoded', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const memberSearchMutation = useMutation({
        mutationFn: (q: string) => api.get('/api/loans/search-members', { params: { q } }),
        onSuccess: (res) => setSearchResults(res.data.data),
        onError: (err: any) => addToast(err.response?.data?.message || 'Member search failed', 'error'),
    });

    const uploadDocMutation = useMutation({
        mutationFn: ({ loanId, file }: { loanId: number; file: File }) => {
            const fd = new FormData();
            fd.append('document', file);
            return api.post(`/api/loans/${loanId}/documents`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        },
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); addToast('Document uploaded', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Upload failed', 'error'),
    });

    const disburseMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/loans/${id}/disburse`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); addToast('Loan disbursed', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Disbursement failed', 'error'),
    });

    const closeMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/loans/${id}/close`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); addToast('Loan closed', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to close loan', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/loans/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); addToast('Loan deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to delete loan', 'error'),
    });

    const editMutation = useMutation({
        mutationFn: (payload: any) => api.put(`/api/loans/${editingLoan.id}`, payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); setShowEditModal(false); setEditingLoan(null); addToast('Loan updated', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update loan', 'error'),
    });

    const openEdit = (l: any) => {
        setEditingLoan(l);
        setEditErrors({});
        setEditForm({
            loan_type: l.loan_type,
            loan_amount: String(l.loan_amount),
            interest_rate: String(l.interest_rate),
            term_months: String(l.term_months),
            payment_frequency: l.payment_frequency,
            loan_status: l.loan_status,
            due_date: l.due_date || '',
        });
        setShowEditModal(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(loanEditSchema, editForm);
        setEditErrors(errs);
        if (Object.keys(errs).length > 0) return;
        editMutation.mutate({
            loan_type: editForm.loan_type,
            loan_amount: Number(editForm.loan_amount),
            interest_rate: Number(editForm.interest_rate),
            term_months: Number(editForm.term_months),
            payment_frequency: editForm.payment_frequency,
            loan_status: editForm.loan_status,
            due_date: editForm.due_date || null,
        });
    };

    const handleEncodeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(loanEncodeSchema, encodeForm);
        if (Object.keys(errs).length > 0) {
            setEncodeErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setEncodeErrors({});
        encodeMutation.mutate(encodeForm);
    };

    const loans = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const canEncode = data?.data?.can_encode;
    const role = user?.primary_role;
    const canDisburse = role === 'admin' || role === 'manager';
    const canManage = !!canEncode;

    const getStatusColor = (s: string) => {
        const c: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-green-100 text-green-800', active: 'bg-blue-100 text-blue-800', paid_off: 'bg-gray-100 text-gray-800', rejected: 'bg-red-100 text-red-800' };
        return c[s] || 'bg-gray-100 text-gray-800';
    };

    const getStatusLabel = (s: string) => {
        const c: Record<string, string> = { pending: 'Pending', approved: 'Approved', active: 'Active', paid_off: 'Paid Off', rejected: 'Rejected' };
        return c[s] || s;
    };

    const toggleSort = (field: string) => {
        if (sort === field) {
            setDirection(d => (d === 'desc' ? 'asc' : 'desc'));
        } else {
            setSort(field);
            setDirection('asc');
        }
    };

    const sortableTh = (label: string, field: string) => (
        <th onClick={() => toggleSort(field)} className={`p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200 ${sort === field ? 'text-blue-700' : ''}`}>
            {label} {sort === field && <span className="ml-1">{direction === 'asc' ? '↑' : '↓'}</span>}
        </th>
    );

    const detail = loanDetail?.data;
    const tabs = [
        { key: 'overview', label: 'Overview', icon: 'fas fa-info-circle' },
        { key: 'payments', label: 'Payment History', icon: 'fas fa-history' },
        { key: 'schedule', label: 'Amortization Schedule', icon: 'fas fa-table' },
        { key: 'documents', label: 'Documents', icon: 'fas fa-file-alt' },
        { key: 'group', label: 'Group Members', icon: 'fas fa-users' },
    ];

    return (
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Loans</h1>
                    <p className="text-sm text-gray-500">Manage loan applications and records</p>
                </div>
                {canEncode && (
                    <button onClick={() => setShowEncodeModal(true)} className="mt-3 sm:mt-0 bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium">
                        <i className="fas fa-plus mr-1"></i> Encode Loan
                    </button>
                )}
            </div>

            <div className="p-4">
                <input
                    type="text"
                    placeholder="Search loans..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />

                {isLoading ? (
                    <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-14 animate-pulse rounded-lg bg-gray-100" />)}</div>
                ) : loans.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                        <i className="fas fa-file-invoice-dollar text-4xl text-gray-300"></i>
                        <p className="mt-4 text-sm text-gray-500">No loans found</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Loan #</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    {sortableTh('Amount', 'loan_amount')}
                                    {sortableTh('Type', 'loan_type')}
                                    {sortableTh('Status', 'loan_status')}
                                    {sortableTh('Date', 'created_at')}
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loans.map((l: any) => (
                                    <tr key={l.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm font-medium text-gray-900">{l.loan_number}</td>
                                        <td className="p-4 text-sm font-medium text-gray-900">{l.borrower?.name || 'Unknown'}{l.borrower?.user_number && <div className="text-xs text-gray-500">{l.borrower.user_number}</div>}</td>
                                        <td className="p-4 text-base font-semibold text-gray-900">₱{Number(l.loan_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="p-4"><span className="px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded capitalize">{l.loan_type}</span></td>
                                        <td className="p-4">
                                            <span className={`px-2.5 py-0.5 text-xs font-medium rounded ${getStatusColor(l.loan_status)}`}>{getStatusLabel(l.loan_status)}</span>
                                        </td>
                                        <td className="p-4 text-sm text-gray-900">{l.created_at}</td>
                                        <td className="p-4">
                                            <div className="flex items-center gap-1">
                                                <button onClick={() => { setSelectedLoanId(l.id); setActiveTab('overview'); }} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="View">
                                                    <i className="fas fa-eye"></i>
                                                </button>
                                                {canDisburse && l.loan_status === 'approved' && (
                                                    <button onClick={() => { if (confirm('Disburse loan funds to borrower savings?')) disburseMutation.mutate(l.id); }} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg" title="Disburse">
                                                        <i className="fas fa-money-bill-wave"></i>
                                                    </button>
                                                )}
                                                {canManage && l.loan_status === 'active' && (
                                                    <button onClick={() => { if (confirm('Mark this loan as paid off?')) closeMutation.mutate(l.id); }} className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg" title="Close">
                                                        <i className="fas fa-check-double"></i>
                                                    </button>
                                                )}
                                                {canManage && (
                                                    <button onClick={() => openEdit(l)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit">
                                                        <i className="fas fa-edit"></i>
                                                    </button>
                                                )}
                                                {canManage && (
                                                    <button onClick={() => { if (confirm('Delete this loan record?')) deleteMutation.mutate(l.id); }} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                                        <i className="fas fa-trash"></i>
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                        <span className="text-sm font-normal text-gray-500">Showing <span className="font-semibold text-gray-900">{((page - 1) * pagination.per_page + 1)}-{Math.min(page * pagination.per_page, pagination.total)}</span> of <span className="font-semibold text-gray-900">{pagination.total}</span></span>
                        <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                    </div>
                )}
            </div>

            {/* Detail Modal */}
            {selectedLoanId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setSelectedLoanId(null)}>
                    <div className="w-full max-w-3xl h-[90vh] rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="mb-1 flex items-center justify-between">
                                <h2 className="text-lg font-bold">{detail?.loan_number || 'Loan Details'}</h2>
                                <button onClick={() => setSelectedLoanId(null)} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white"><i className="fas fa-times"></i></button>
                            </div>
                            {detail?.borrower?.name && <p className="text-sm text-white/80">{detail.borrower.name} &middot; {detail.borrower.user_number}</p>}
                        </div>

                        {/* Tabs */}
                        <div className="flex gap-0 border-b border-gray-200 shrink-0 px-6">
                            {tabs.map(t => (
                                <button key={t.key} onClick={() => setActiveTab(t.key)} className={`flex items-center gap-1.5 whitespace-nowrap px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors ${activeTab === t.key ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                                    <i className={t.icon}></i> {t.label}
                                </button>
                            ))}
                        </div>

                        {/* Tab content */}
                        <div className="p-6 flex-1 overflow-y-auto">

                            {/* Tab: Overview */}
                            {activeTab === 'overview' && detail && (
                                <div className="space-y-3">
                                    <InfoRow label="Loan Number" value={detail.loan_number} />
                                    <InfoRow label="Borrower" value={detail.borrower?.name || '—'} />
                                    <InfoRow label="Type" value={detail.loan_type} />
                                    <InfoRow label="Amount" value={`₱${Number(detail.loan_amount).toLocaleString()}`} />
                                    <InfoRow label="Interest Rate" value={`${detail.interest_rate}%`} />
                                    <InfoRow label="Term" value={`${detail.term_months} months`} />
                                    <InfoRow label="Payment Frequency" value={detail.payment_frequency} />
                                    <InfoRow label="Status" value={<span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${getStatusColor(detail.loan_status)}`}>{detail.loan_status}</span>} />
                                    <InfoRow label="Applied" value={detail.applied_at || '—'} />
                                    <InfoRow label="Approved" value={detail.approved_at || '—'} />
                                    <InfoRow label="Due Date" value={detail.due_date || '—'} />
                                    <InfoRow label="Approved By" value={detail.approved_by?.name || '—'} />
                                </div>
                            )}

                            {/* Tab: Payment History */}
                            {activeTab === 'payments' && (
                                <div>
                                    {detail?.payments?.length > 0 ? (
                                        <table className="min-w-full divide-y divide-gray-200 rounded-lg border border-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {detail.payments.map((p: any) => (
                                                    <tr key={p.id}>
                                                        <td className="px-3 py-2 text-sm text-gray-500">{p.payment_date || '—'}</td>
                                                        <td className="px-3 py-2 text-sm text-right font-medium text-gray-900">₱{Number(p.amount).toLocaleString()}</td>
                                                        <td className="px-3 py-2"><span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${p.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>{p.status || 'completed'}</span></td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <div className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">Payment history available in loan details view</div>
                                    )}
                                </div>
                            )}

                            {/* Tab: Amortization Schedule */}
                            {activeTab === 'schedule' && (
                                <div>
                                    {detail?.payment_schedule?.length > 0 ? (
                                        <div className="overflow-x-auto rounded-lg border border-gray-200">
                                            <table className="min-w-full divide-y divide-gray-200">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                                                        <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Payment</th>
                                                        <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Principal</th>
                                                        <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Interest</th>
                                                        <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {detail.payment_schedule.map((row: any) => (
                                                        <tr key={row.period} className="hover:bg-gray-50">
                                                            <td className="px-3 py-1.5 text-xs text-gray-500">{row.period}</td>
                                                            <td className="px-3 py-1.5 text-xs text-gray-500">{row.due_date}</td>
                                                            <td className="px-3 py-1.5 text-xs text-right font-medium text-gray-900">₱{row.payment.toLocaleString()}</td>
                                                            <td className="px-3 py-1.5 text-xs text-right text-gray-500">₱{row.principal.toLocaleString()}</td>
                                                            <td className="px-3 py-1.5 text-xs text-right text-gray-500">₱{row.interest.toLocaleString()}</td>
                                                            <td className="px-3 py-1.5 text-xs text-right text-gray-500">₱{row.balance.toLocaleString()}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <div className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">No schedule data available</div>
                                    )}
                                </div>
                            )}

                            {/* Tab: Documents */}
                            {activeTab === 'documents' && (
                                <div>
                                    <div className="mb-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                                        <label className="flex cursor-pointer flex-col items-center justify-center gap-2">
                                            <i className="fas fa-cloud-upload-alt text-2xl text-gray-400"></i>
                                            <span className="text-sm font-medium text-gray-600">Upload a supporting document</span>
                                            <span className="text-xs text-gray-400">JPG, PNG, GIF, WebP or PDF &middot; max 10MB</span>
                                            <input
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                                                className="hidden"
                                                disabled={uploadDocMutation.isPending}
                                                onChange={e => {
                                                    const file = e.target.files?.[0];
                                                    if (file && selectedLoanId) {
                                                        uploadDocMutation.mutate({ loanId: selectedLoanId, file });
                                                        e.target.value = '';
                                                    }
                                                }}
                                            />
                                        </label>
                                        {uploadDocMutation.isPending && <p className="mt-2 text-center text-xs text-gray-500">Uploading...</p>}
                                    </div>
                                    {detail?.documents?.length > 0 ? (
                                        <div className="space-y-2">
                                            {detail.documents.map((d: any) => (
                                                <a key={d.id} href={d.url} target="_blank" rel="noopener noreferrer" className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">
                                                    <div className="flex items-center gap-2">
                                                        <i className="fas fa-file-alt text-gray-400"></i>
                                                        <span className="text-sm text-gray-700">{d.original_name}</span>
                                                        {d.file_size && <span className="text-xs text-gray-400">{(d.file_size / 1024).toFixed(1)} KB</span>}
                                                    </div>
                                                    <i className="fas fa-download text-xs text-gray-400"></i>
                                                </a>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">No documents uploaded yet</div>
                                    )}
                                </div>
                            )}

                            {/* Tab: Group Members */}
                            {activeTab === 'group' && (
                                <div>
                                    {detail?.group_members?.length > 0 ? (
                                        <div className="space-y-2">
                                            {detail.group_members.map((gm: any, i: number) => (
                                                <div key={i} className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                                    <span className="text-sm text-gray-700">{gm.name || 'Unknown'}</span>
                                                    <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${gm.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>{gm.status || 'pending'}</span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">Group member information available in full loan management</div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Encode Modal */}
            {showEncodeModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setShowEncodeModal(false)}>
                    <div className="w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">Encode Loan</h2>
                                <button onClick={() => setShowEncodeModal(false)} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white"><i className="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            <form id="encode-loan-form" onSubmit={handleEncodeSubmit} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Search Member *</label>
                                    <input type="text" value={memberSearch} onChange={e => { setMemberSearch(e.target.value); if (e.target.value.length >= 2) memberSearchMutation.mutate(e.target.value); }} className={`w-full rounded-lg border px-3 py-2 text-sm ${encodeErrors.user_id ? 'border-red-400' : 'border-gray-200'}`} placeholder="Search by name or number..." />
                                    {encodeErrors.user_id && <p className="mt-1 text-xs text-red-600">{encodeErrors.user_id}</p>}
                                    {searchResults.length > 0 && (
                                        <div className="mt-1 rounded-lg border border-gray-200 bg-white shadow-lg">
                                            {searchResults.map((m: any) => (
                                                <button key={m.id} type="button" onClick={() => { setEncodeForm(f => ({ ...f, user_id: m.id })); setMemberSearch(`${m.name} (${m.user_number})`); setSearchResults([]); setEncodeErrors(prev => { const n = { ...prev }; delete n.user_id; return n; }); }} className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50">{m.name} ({m.user_number})</button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Amount *</label><input type="number" value={encodeForm.loan_amount} onChange={e => setEncodeForm(f => ({ ...f, loan_amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${encodeErrors.loan_amount ? 'border-red-400' : 'border-gray-200'}`} />{encodeErrors.loan_amount && <p className="mt-1 text-xs text-red-600">{encodeErrors.loan_amount}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Interest Rate (%)</label><input type="number" step="0.1" value={encodeForm.interest_rate} onChange={e => setEncodeForm(f => ({ ...f, interest_rate: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${encodeErrors.interest_rate ? 'border-red-400' : 'border-gray-200'}`} />{encodeErrors.interest_rate && <p className="mt-1 text-xs text-red-600">{encodeErrors.interest_rate}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Term (months)</label><input type="number" value={encodeForm.term_months} onChange={e => setEncodeForm(f => ({ ...f, term_months: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${encodeErrors.term_months ? 'border-red-400' : 'border-gray-200'}`} />{encodeErrors.term_months && <p className="mt-1 text-xs text-red-600">{encodeErrors.term_months}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Frequency</label><select value={encodeForm.payment_frequency} onChange={e => setEncodeForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option></select></div>
                                </div>
                            </form>
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                            <button type="button" onClick={() => setShowEncodeModal(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" form="encode-loan-form" disabled={encodeMutation.isPending} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium disabled:opacity-50">{encodeMutation.isPending ? 'Saving...' : 'Encode'}</button>
                        </div>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {showEditModal && editingLoan && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setShowEditModal(false)}>
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">Edit Loan {editingLoan.loan_number}</h2>
                                <button onClick={() => setShowEditModal(false)} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white"><i className="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            <form id="edit-loan-form" onSubmit={handleEditSubmit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Type</label><select value={editForm.loan_type} onChange={e => setEditForm(f => ({ ...f, loan_type: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="business">Business</option><option value="group">Group</option><option value="special">Special</option></select></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Status</label><select value={editForm.loan_status} onChange={e => setEditForm(f => ({ ...f, loan_status: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="pending">Pending</option><option value="approved">Approved</option><option value="active">Active</option><option value="paid_off">Paid Off</option></select></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Amount</label><input type="number" step="0.01" value={editForm.loan_amount} onChange={e => setEditForm(f => ({ ...f, loan_amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${editErrors.loan_amount ? 'border-red-400' : 'border-gray-200'}`} />{editErrors.loan_amount && <p className="mt-1 text-xs text-red-600">{editErrors.loan_amount}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Interest Rate (%)</label><input type="number" step="0.1" value={editForm.interest_rate} onChange={e => setEditForm(f => ({ ...f, interest_rate: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${editErrors.interest_rate ? 'border-red-400' : 'border-gray-200'}`} />{editErrors.interest_rate && <p className="mt-1 text-xs text-red-600">{editErrors.interest_rate}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Term (months)</label><input type="number" value={editForm.term_months} onChange={e => setEditForm(f => ({ ...f, term_months: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${editErrors.term_months ? 'border-red-400' : 'border-gray-200'}`} />{editErrors.term_months && <p className="mt-1 text-xs text-red-600">{editErrors.term_months}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Frequency</label><select value={editForm.payment_frequency} onChange={e => setEditForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option></select></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Due Date</label><input type="date" value={editForm.due_date} onChange={e => setEditForm(f => ({ ...f, due_date: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" /></div>
                                </div>
                            </form>
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                            <button type="button" onClick={() => setShowEditModal(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" form="edit-loan-form" disabled={editMutation.isPending} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium disabled:opacity-50">{editMutation.isPending ? 'Saving...' : 'Save'}</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function InfoRow({ label, value }: { label: string; value: any }) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
            <span className="text-sm text-gray-500">{label}</span>
            <span className="text-sm font-medium text-gray-900">{value}</span>
        </div>
    );
}
