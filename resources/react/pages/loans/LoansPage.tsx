import SortableHeader from '../../components/ui/SortableHeader';
import HelpTip from '../../components/ui/HelpTip';
import PageHeader from '../../components/ui/PageHeader';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import BranchBadge from '../../components/BranchBadge';
import BranchSelect from '../../components/BranchSelect';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { validate, loanEncodeSchema, loanEditSchema, type FieldErrors } from '../../lib/validation';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';
import LoanDetailsModal, { type LoanDetails } from '../../components/loans/LoanDetailsModal';
import CoMakerPicker from '../../components/loans/CoMakerPicker';
import type { CoMaker } from '../../components/loans/CoMakerList';
import { formatPeso } from '../../lib/format';
import DateRangeFilter from '../../components/ui/DateRangeFilter';

interface OfficerLoanDetails extends Omit<LoanDetails, 'approved_by'> {
    borrower?: { name: string; user_number?: string | null } | null;
    loan_product?: string | null;
    approved_by?: { name: string } | null;
    reviewed_by?: { name: string } | null;
    next_approver?: string | null;
}

function formatLoanDate(value?: string | null) {
    return value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' }) : null;
}

export default function LoansPage() {
    const { user } = useAuth();
    const [search, setSearch] = useState('');
    const [branchFilter, setBranchFilter] = useState<number | ''>('');
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [loanStatus, setLoanStatus] = useState('');
    const [overdueToday, setOverdueToday] = useState(false);
    const [selectedLoanId, setSelectedLoanId] = useState<number | null>(null);
    const [disbursementLoan, setDisbursementLoan] = useState<any>(null);
    const [disbursementDestination, setDisbursementDestination] = useState<'cash' | 'savings'>('cash');
    const [showEncodeModal, setShowEncodeModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editingLoan, setEditingLoan] = useState<any>(null);
    const [editForm, setEditForm] = useState({ loan_type: 'business', loan_amount: '', interest_rate: '3', term_months: '12', payment_frequency: 'monthly', due_date: '' });
    const [editCoMakers, setEditCoMakers] = useState<CoMaker[]>([]);
    const defaultEncodeForm = () => ({ user_id: '', loan_product: 'business', loan_type: 'business', loan_status: 'pending', loan_amount: '', interest_rate: '3', term_months: '12', payment_frequency: 'monthly', applied_at: new Date().toISOString().split('T')[0], branch_id: '' as number | '' });
    const [encodeForm, setEncodeForm] = useState(defaultEncodeForm);
    const [memberSearch, setMemberSearch] = useState('');
    const [coMakers, setCoMakers] = useState<CoMaker[]>([]);
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [encodeErrors, setEncodeErrors] = useState<FieldErrors>({});
    const [editErrors, setEditErrors] = useState<FieldErrors>({});
    const [confirmState, setConfirmState] = useState<{
        isOpen: boolean;
        title: string;
        message: string;
        confirmText?: string;
        variant?: 'danger' | 'warning' | 'primary';
        onConfirm: () => void;
    }>({ isOpen: false, title: '', message: '', onConfirm: () => {} });
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const canAssignBranch = !!user?.permissions?.includes('branches.view');
    const canUploadDocument = !!user?.permissions?.includes('loans.update');

    const { data, isLoading } = useQuery({
        queryKey: ['loans', search, page, sort, direction, branchFilter, from, to, loanStatus, overdueToday],
        queryFn: () => api.get('/api/loans', { params: { search: search || undefined, branch_id: branchFilter || undefined, page, per_page: 10, sort, direction, from: from || undefined, to: to || undefined, loan_status: overdueToday ? undefined : (loanStatus || undefined), overdue_today: overdueToday || undefined } }),
    });

    const { data: loanDetail, isError: loanDetailFailed } = useQuery({
        queryKey: ['loan-detail', selectedLoanId],
        queryFn: () => api.get<OfficerLoanDetails>(`/api/loans/${selectedLoanId}`),
        enabled: !!selectedLoanId,
    });

    const { data: productsData } = useQuery({
        queryKey: ['loan-products-options'],
        queryFn: () => api.get('/api/loans/loan-products'),
    });
    const products = productsData?.data?.data ?? {};
    const requiredCoMakers = Number(products[encodeForm.loan_product]?.required_co_makers ?? 0);
    const editRequiredCoMakers = Number(products[editingLoan?.loan_product]?.required_co_makers ?? 0);

    const closeEncodeModal = () => {
        setShowEncodeModal(false);
        setEncodeForm(defaultEncodeForm());
        setCoMakers([]);
        setMemberSearch('');
        setSearchResults([]);
        setEncodeErrors({});
    };

    const encodeMutation = useMutation({
        mutationFn: (payload: any) => api.post('/api/loans', payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); closeEncodeModal(); addToast('Loan encoded', 'success'); },
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
        onSuccess: (_, { loanId }) => { queryClient.invalidateQueries({ queryKey: ['loan-detail', loanId] }); addToast('Document uploaded', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Upload failed', 'error'),
    });

    const disburseMutation = useMutation({
        mutationFn: ({ id, destination }: { id: number; destination: 'cash' | 'savings' }) => api.post(`/api/loans/${id}/disburse`, { destination }),
        onSuccess: () => { setDisbursementLoan(null); queryClient.invalidateQueries({ queryKey: ['loans'] }); queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); addToast('Loan disbursed', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Disbursement failed', 'error'),
    });

    const closeMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/loans/${id}/close`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); addToast('Loan closed', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to close loan', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/loans/${id}`),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['loans'] });
            queryClient.invalidateQueries({ queryKey: ['oversight-requests'] });
            setConfirmState(s => ({ ...s, isOpen: false }));
            addToast(res.data.message || 'Loan deletion request submitted for chairman approval', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to request loan deletion', 'error'),
    });

    const editMutation = useMutation({
        mutationFn: (payload: any) => api.put(`/api/loans/${editingLoan.id}`, payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); queryClient.invalidateQueries({ queryKey: ['loan-detail', selectedLoanId] }); setShowEditModal(false); setEditingLoan(null); setEditCoMakers([]); addToast('Loan updated', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update loan', 'error'),
    });

    const openEdit = (l: any) => {
        setEditingLoan(l);
        setEditErrors({});
        setEditCoMakers(Array.isArray(l.co_makers) ? l.co_makers : []);
        setEditForm({
            loan_type: l.loan_type,
            loan_amount: String(l.loan_amount),
            interest_rate: String(l.interest_rate),
            term_months: String(l.term_months),
            payment_frequency: l.payment_frequency,
            due_date: l.due_date || '',
        });
        setShowEditModal(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(loanEditSchema, editForm);
        if (editingLoan?.loan_status === 'pending' && editRequiredCoMakers > 0 && editCoMakers.length !== editRequiredCoMakers) {
            errs.co_makers = editRequiredCoMakers === 1 ? 'Add exactly 1 co-maker.' : `Add exactly ${editRequiredCoMakers} co-makers.`;
        }
        setEditErrors(errs);
        if (Object.keys(errs).length > 0) return;
        editMutation.mutate({
            loan_type: editForm.loan_type,
            loan_amount: Number(editForm.loan_amount),
            interest_rate: Number(editForm.interest_rate),
            term_months: Number(editForm.term_months),
            payment_frequency: editForm.payment_frequency,
            due_date: editForm.due_date || null,
            ...(editingLoan?.loan_status === 'pending' ? { co_makers: editCoMakers.map(member => member.id) } : {}),
        });
    };

    const handleEncodeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(loanEncodeSchema, encodeForm);
        if (requiredCoMakers > 0 && coMakers.length !== requiredCoMakers) {
            errs.co_makers = requiredCoMakers === 1 ? 'Add exactly 1 co-maker.' : `Add exactly ${requiredCoMakers} co-makers.`;
        }
        if (Object.keys(errs).length > 0) {
            setEncodeErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setEncodeErrors({});
        encodeMutation.mutate({ ...encodeForm, co_makers: coMakers.map(member => member.id) });
    };

    const loans = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const canEncode = data?.data?.can_encode;
    const canViewAll = !!user?.permissions?.includes('loans.view');
    const role = user?.primary_role;
    const canDisburse = !!user?.permissions?.includes('loans.disburse');
    const canClose = role === 'chairman' || role === 'manager';
    const canManage = !!canEncode;
    const canRequestDelete = !!user?.permissions?.includes('loans.update');

    const getStatusColor = (s: string) => {
        const c: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-green-100 text-green-800', active: 'bg-blue-100 text-blue-800', paid_off: 'bg-gray-100 text-gray-800', rejected: 'bg-red-100 text-red-800' };
        return c[s] || 'bg-gray-100 text-gray-800';
    };

    const getStatusLabel = (s: string) => {
        const c: Record<string, string> = { pending: 'Pending', approved: 'Approved', active: 'Active', paid_off: 'Paid Off', rejected: 'Rejected' };
        return c[s] || s;
    };

    const toggleSort = (field: string) => {
        setPage(1);
        if (sort === field) {
            setDirection(d => (d === 'desc' ? 'asc' : 'desc'));
        } else {
            setSort(field);
            setDirection('asc');
        }
    };

    const sortableTh = (label: string, field: string) => (
        <SortableHeader field={field} sort={sort} direction={direction} onSort={toggleSort} className="p-4 text-left">{label}</SortableHeader>
    );

    const detail = loanDetail?.data;

    return (
        <>
            <div className="p-4">
                <PageHeader title={<>Loans <HelpTip text="Loan records move through review, approval, disbursement, and repayment. Encoding a loan records an application; it does not itself disburse funds." /></>} description="Manage loan applications and records" icon="fas fa-file-invoice-dollar" action={canEncode && (
                    <button onClick={() => setShowEncodeModal(true)} className="mt-3 sm:mt-0 bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-2 focus:ring-emerald-500/20 text-white px-4 py-2 text-sm font-medium shadow-xs transition-colors cursor-pointer">
                        <i className="fas fa-plus mr-1.5"></i> Encode Loan
                    </button>
                )} />
            </div>

            <div className="p-4">
                <input
                    type="text"
                    placeholder="Search loans by loan number, borrower name, ID..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 block w-full p-2.5 mb-4 transition-all"
                />

                <div className="table-toolbar mb-4">
                    {canViewAll && <button type="button" onClick={() => { setOverdueToday(value => !value); setLoanStatus(''); setPage(1); }} aria-pressed={overdueToday} className={`rounded-lg border px-3 py-2 text-sm font-medium ${overdueToday ? 'border-amber-600 bg-amber-50 text-amber-800' : 'border-gray-300 bg-white text-gray-700'}`}>
                        <i className="fas fa-clock mr-2"></i>{overdueToday ? 'Showing overdue today' : 'Overdue today'}
                    </button>}
                    <DateRangeFilter from={from} to={to} onFromChange={value => { setFrom(value); setPage(1); }} onToChange={value => { setTo(value); setPage(1); }} />
                    {!overdueToday && <label className="flex items-center gap-2 text-sm text-gray-600">Status
                        <select value={loanStatus} onChange={event => { setLoanStatus(event.target.value); setPage(1); }} className="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                            <option value="">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="active">Active</option><option value="paid_off">Paid off</option><option value="rejected">Rejected</option>
                        </select>
                    </label>}
                    {canAssignBranch && <div className="w-full sm:w-64"><BranchSelect label="" value={branchFilter} onChange={v => { setBranchFilter(v); setPage(1); }} allLabel="All branches" /></div>}
                </div>

                {isLoading ? (
                    <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-14 animate-pulse rounded-lg bg-gray-100" />)}</div>
                ) : loans.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                        <i className="fas fa-file-invoice-dollar text-4xl text-gray-300"></i>
                        <p className="mt-4 text-sm text-gray-500">{overdueToday ? 'No loans are due or overdue today' : 'No loans found'}</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-xs">
                        <table className="min-w-[800px] w-full divide-y divide-gray-200 table-fixed">
                            <thead className="bg-gray-100">
                                <tr>
                                    {sortableTh('Loan #', 'loan_number')}
                                    <SortableHeader field="member" sort={sort} direction={direction} onSort={toggleSort} className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</SortableHeader>
                                    {sortableTh('Amount', 'loan_amount')}
                                    {sortableTh('Type', 'loan_type')}
                                    {sortableTh('Status', 'loan_status')}
                                    {overdueToday && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Due now</th>}
                                    {canAssignBranch && <SortableHeader field="branch" sort={sort} direction={direction} onSort={toggleSort} className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Branch</SortableHeader>}
                                    {sortableTh('Date', 'created_at')}
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loans.map((l: any) => {
                                    // The API changes approved loans to active only when
                                    // disbursement completes, so approved means awaiting disbursement.
                                    const awaitingDisbursement = l.loan_status === 'approved';

                                    return (
                                    <tr key={l.id} className={awaitingDisbursement ? 'bg-emerald-50 hover:bg-emerald-100' : 'hover:bg-gray-50'}>
                                        <td className="p-4 text-sm font-medium text-gray-900">{l.loan_number}</td>
                                        <td className="p-4 text-sm font-medium text-gray-900 truncate" title={l.borrower?.name || 'Unknown'}>{l.borrower?.name || 'Unknown'}{l.borrower?.user_number && <div className="text-xs text-gray-500">{l.borrower.user_number}</div>}</td>
                                        <td className="p-4 text-base font-semibold text-gray-900">{formatPeso(l.loan_amount)}</td>
                                        <td className="p-4 truncate" title={l.loan_product_name || l.loan_product || l.loan_type}><span className="px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded capitalize">{l.loan_product_name || l.loan_product || l.loan_type}</span></td>
                                        <td className="p-4">
                                            <span className={`px-2.5 py-0.5 text-xs font-medium rounded ${getStatusColor(l.loan_status)}`}>{getStatusLabel(l.loan_status)}</span>
                                            {awaitingDisbursement && <div className="mt-1 text-xs font-medium text-emerald-800">Awaiting disbursement</div>}
                                        </td>
                                        {overdueToday && <td className="p-4 text-sm font-semibold text-amber-800">₱{Number(l.amount_due ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}<div className="text-xs font-normal text-gray-500">{l.days_overdue === 0 ? 'Due today' : `${l.days_overdue} day(s) overdue`}</div></td>}
                                        {canAssignBranch && <td className="p-4 truncate" title={l.branch_name}><BranchBadge name={l.branch_name} /></td>}
                                        <td className="p-4 text-sm text-gray-900">{l.created_at}</td>
                                        <td className="p-4">
                                            <div className="flex items-center gap-1">
                                                <button onClick={() => setSelectedLoanId(l.id)} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="View" aria-label={`View loan ${l.loan_number}`}>
                                                    <i className="fas fa-eye" aria-hidden="true"></i>
                                                </button>
                                                {canDisburse && l.loan_status === 'approved' && (
                                                    <button 
                                                        disabled={Boolean(l.approved_by && Number(l.approved_by) === Number(user?.id) && !user?.roles?.includes('admin'))}
                                                        onClick={() => { setDisbursementLoan(l); setDisbursementDestination('cash'); }} 
                                                        className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed" 
                                                        title={l.approved_by && Number(l.approved_by) === Number(user?.id) && !user?.roles?.includes('admin') ? 'Separation of duties: Cannot disburse a loan you approved' : 'Disburse'}
                                                        aria-label={`Disburse loan ${l.loan_number}`}>
                                                        <i className="fas fa-money-bill-wave" aria-hidden="true"></i>
                                                    </button>
                                                )}
                                                {canClose && l.loan_status === 'active' && (
                                                    <button onClick={() => setConfirmState({
                                                        isOpen: true,
                                                        title: 'Mark Paid Off',
                                                        message: `Mark loan ${l.loan_number} as paid off?`,
                                                        confirmText: 'Mark Paid Off',
                                                        variant: 'primary',
                                                        onConfirm: () => closeMutation.mutate(l.id),
                                                    })} className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer" title="Close" aria-label={`Mark loan ${l.loan_number} paid off`}>
                                                        <i className="fas fa-check-double" aria-hidden="true"></i>
                                                    </button>
                                                )}
                                                {canManage && (
                                                    <button onClick={() => openEdit(l)} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Edit" aria-label={`Edit loan ${l.loan_number}`}>
                                                        <i className="fas fa-edit" aria-hidden="true"></i>
                                                    </button>
                                                )}
                                                {canRequestDelete && l.can_delete && (
                                                    <button onClick={() => setConfirmState({
                                                        isOpen: true,
                                                        title: 'Request Loan Deletion',
                                                        message: `Request deletion of ${l.loan_number}? Another active chairman must approve. Only pending, unreviewed loans with no transactions, uploaded documents, co-maker records, or collection records can be removed. After approval, the borrower will no longer see this pending loan in My Loans.`,
                                                        confirmText: 'Request Deletion',
                                                        variant: 'danger',
                                                        onConfirm: () => deleteMutation.mutate(l.id),
                                                    })} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Delete">
                                                        <i className="fas fa-trash"></i>
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <div className="mt-4">
                        <Pagination
                            page={page}
                            lastPage={pagination.last_page || 1}
                            onChange={setPage}
                            total={pagination.total}
                            perPage={pagination.per_page}
                        />
                    </div>
                )}
            </div>

            {selectedLoanId !== null && (
                <LoanDetailsModal
                    key={selectedLoanId}
                    loan={detail && {
                        ...detail,
                        loan_product_name: detail.loan_product_name || detail.loan_type_label || detail.loan_product,
                        applied_at: formatLoanDate(detail.applied_at),
                        approved_at: formatLoanDate(detail.approved_at),
                        due_date: formatLoanDate(detail.due_date),
                        approved_by: detail.approved_by?.name,
                    }}
                    loadFailed={loanDetailFailed}
                    borrowerName={detail?.borrower?.name}
                    borrowerNumber={detail?.borrower?.user_number}
                    reviewerName={detail?.reviewed_by?.name}
                    nextApprover={detail?.next_approver ?? undefined}
                    onUpload={canUploadDocument ? file => uploadDocMutation.mutate({ loanId: selectedLoanId, file }) : undefined}
                    uploading={uploadDocMutation.isPending && uploadDocMutation.variables?.loanId === selectedLoanId}
                    onClose={() => setSelectedLoanId(null)}
                />
            )}

            {/* Encode Modal */}
            {showEncodeModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={closeEncodeModal}>
                    <div className="w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">Encode Loan</h2>
                                <button onClick={closeEncodeModal} aria-label="Close Encode Loan modal" className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white cursor-pointer"><i className="fas fa-times" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            <form id="encode-loan-form" onSubmit={handleEncodeSubmit} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Search Member *</label>
                                    <input type="text" value={memberSearch} onChange={e => {
                                        setMemberSearch(e.target.value);
                                        setEncodeForm(f => ({ ...f, user_id: '' }));
                                        setCoMakers([]);
                                        setSearchResults([]);
                                        if (e.target.value.trim().length >= 2) memberSearchMutation.mutate(e.target.value.trim());
                                    }} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${encodeErrors.user_id ? 'border-red-400' : 'border-gray-200'}`} placeholder="Search by name or number..." />
                                    {encodeErrors.user_id && <p className="mt-1 text-xs text-red-600">{encodeErrors.user_id}</p>}
                                    {searchResults.length > 0 && (
                                        <div className="mt-1 rounded-lg border border-gray-200 bg-white shadow-lg">
                                            {searchResults.map((m: any) => (
                                                <button key={m.id} type="button" onClick={() => { setEncodeForm(f => ({ ...f, user_id: m.id })); setMemberSearch(`${m.name} (${m.user_number})`); setSearchResults([]); setEncodeErrors(prev => { const n = { ...prev }; delete n.user_id; return n; }); }} className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 cursor-pointer">{m.name} ({m.user_number})</button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Loan Product *</label>
                                    <select
                                        value={encodeForm.loan_product}
                                        onChange={e => {
                                            const code = e.target.value;
                                            const selected = products[code];
                                            setCoMakers([]);
                                            setEncodeForm(f => ({
                                                ...f,
                                                loan_product: code,
                                                loan_type: selected?.base_type || 'business',
                                                interest_rate: selected ? String(selected.interest_rate) : f.interest_rate,
                                                term_months: selected ? String(selected.max_term) : f.term_months,
                                            }));
                                        }}
                                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                                    >
                                        {Object.values(products).map((p: any) => (
                                            <option key={p.code} value={p.code}>{p.name} ({p.base_type})</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Amount *</label><input type="number" value={encodeForm.loan_amount} onChange={e => setEncodeForm(f => ({ ...f, loan_amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${encodeErrors.loan_amount ? 'border-red-400' : 'border-gray-200'}`} />{encodeErrors.loan_amount && <p className="mt-1 text-xs text-red-600">{encodeErrors.loan_amount}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Interest Rate (%)</label><input type="number" step="0.1" value={encodeForm.interest_rate} onChange={e => setEncodeForm(f => ({ ...f, interest_rate: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${encodeErrors.interest_rate ? 'border-red-400' : 'border-gray-200'}`} />{encodeErrors.interest_rate && <p className="mt-1 text-xs text-red-600">{encodeErrors.interest_rate}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Term (months)</label><input type="number" value={encodeForm.term_months} onChange={e => setEncodeForm(f => ({ ...f, term_months: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${encodeErrors.term_months ? 'border-red-400' : 'border-gray-200'}`} />{encodeErrors.term_months && <p className="mt-1 text-xs text-red-600">{encodeErrors.term_months}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Frequency</label><select value={encodeForm.payment_frequency} onChange={e => setEncodeForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"><option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option></select></div>
                                </div>
                                <CoMakerPicker
                                    key={`${encodeForm.user_id}-${encodeForm.loan_product}`}
                                    borrowerId={Number(encodeForm.user_id)}
                                    requiredCount={requiredCoMakers}
                                    members={coMakers}
                                    onChange={setCoMakers}
                                    error={encodeErrors.co_makers}
                                />
                                {canAssignBranch && (
                                    <div className="max-w-xs">
                                        <BranchSelect value={encodeForm.branch_id} onChange={v => setEncodeForm(f => ({ ...f, branch_id: v }))} error={encodeErrors.branch_id} />
                                    </div>
                                )}
                            </form>
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                            <button type="button" onClick={closeEncodeModal} className="rounded-lg border border-gray-200 px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">Cancel</button>
                            <button type="submit" form="encode-loan-form" disabled={encodeMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-2 focus:ring-emerald-500/20 text-white px-4 py-2 text-sm font-medium shadow-xs disabled:opacity-50 transition-colors cursor-pointer">{encodeMutation.isPending ? 'Saving...' : 'Encode'}</button>
                        </div>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {showEditModal && editingLoan && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setShowEditModal(false)}>
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">Edit Loan {editingLoan.loan_number}</h2>
                                <button onClick={() => setShowEditModal(false)} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white cursor-pointer"><i className="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            <form id="edit-loan-form" onSubmit={handleEditSubmit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Type</label><select value={editForm.loan_type} onChange={e => setEditForm(f => ({ ...f, loan_type: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"><option value="business">Business</option><option value="group">Group</option><option value="special">Special</option></select></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Status</label><p className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{getStatusLabel(editingLoan.loan_status)}. Status changes are handled through the loan review and payment workflows.</p></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Amount</label><input type="number" step="0.01" value={editForm.loan_amount} onChange={e => setEditForm(f => ({ ...f, loan_amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${editErrors.loan_amount ? 'border-red-400' : 'border-gray-200'}`} />{editErrors.loan_amount && <p className="mt-1 text-xs text-red-600">{editErrors.loan_amount}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Interest Rate (%)</label><input type="number" step="0.1" value={editForm.interest_rate} onChange={e => setEditForm(f => ({ ...f, interest_rate: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${editErrors.interest_rate ? 'border-red-400' : 'border-gray-200'}`} />{editErrors.interest_rate && <p className="mt-1 text-xs text-red-600">{editErrors.interest_rate}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Term (months)</label><input type="number" value={editForm.term_months} onChange={e => setEditForm(f => ({ ...f, term_months: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${editErrors.term_months ? 'border-red-400' : 'border-gray-200'}`} />{editErrors.term_months && <p className="mt-1 text-xs text-red-600">{editErrors.term_months}</p>}</div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Frequency</label><select value={editForm.payment_frequency} onChange={e => setEditForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"><option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option></select></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Due Date</label><input type="date" value={editForm.due_date} onChange={e => setEditForm(f => ({ ...f, due_date: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" /></div>
                                </div>
                                {editingLoan.loan_status === 'pending' && (
                                    <CoMakerPicker
                                        key={`edit-${editingLoan.id}`}
                                        borrowerId={Number(editingLoan.user_id ?? editingLoan.borrower?.id ?? 0)}
                                        requiredCount={editRequiredCoMakers}
                                        members={editCoMakers}
                                        onChange={setEditCoMakers}
                                        error={editErrors.co_makers}
                                    />
                                )}
                            </form>
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                            <button type="button" onClick={() => setShowEditModal(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">Cancel</button>
                            <button type="submit" form="edit-loan-form" disabled={editMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-2 focus:ring-emerald-500/20 text-white px-4 py-2 text-sm font-medium shadow-xs disabled:opacity-50 transition-colors cursor-pointer">{editMutation.isPending ? 'Saving...' : 'Save'}</button>
                        </div>
                    </div>
                </div>
            )}
            {disbursementLoan && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" role="presentation" onClick={() => !disburseMutation.isPending && setDisbursementLoan(null)}>
                    <section className="w-full max-w-lg rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="loan-disbursement-title" onClick={e => e.stopPropagation()}>
                        <div className="border-b border-gray-200 px-6 py-5">
                            <h2 id="loan-disbursement-title" className="flex items-center gap-2 text-lg font-bold text-gray-900">Confirm Loan Disbursement <HelpTip text="Karaniwang cash ang loan proceeds. Piliin lang ang Credit to savings kung iyon talaga ang napagkasunduan at na-confirm ng borrower. Mare-record ang piniling paraan sa transaction." /></h2>
                            <p className="mt-1 text-sm text-gray-600">{disbursementLoan.loan_number} · {formatPeso(disbursementLoan.loan_amount)}</p>
                        </div>
                        <div className="space-y-3 p-6">
                            <p className="text-sm text-gray-700">Choose how the borrower will receive the loan proceeds:</p>
                            <label className={`flex cursor-pointer gap-3 rounded-xl border p-4 ${disbursementDestination === 'cash' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200'}`}>
                                <input type="radio" name="loan-disbursement-destination" value="cash" checked={disbursementDestination === 'cash'} onChange={() => setDisbursementDestination('cash')} className="mt-1 accent-emerald-600" />
                                <span><span className="block text-sm font-semibold text-gray-900">Cash received</span><span className="mt-1 block text-xs text-gray-600">Record that the borrower received the loan proceeds physically.</span></span>
                            </label>
                            <label className={`flex cursor-pointer gap-3 rounded-xl border p-4 ${disbursementDestination === 'savings' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200'}`}>
                                <input type="radio" name="loan-disbursement-destination" value="savings" checked={disbursementDestination === 'savings'} onChange={() => setDisbursementDestination('savings')} className="mt-1 accent-emerald-600" />
                                <span><span className="block text-sm font-semibold text-gray-900">Credit to savings</span><span className="mt-1 block text-xs text-gray-600">Post the loan proceeds to the borrower’s savings ledger.</span></span>
                            </label>
                            <p className="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Confirm the selected delivery method only after the disbursement has been completed.</p>
                        </div>
                        <div className="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <button type="button" disabled={disburseMutation.isPending} onClick={() => setDisbursementLoan(null)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-white disabled:opacity-50">Cancel</button>
                            <button type="button" disabled={disburseMutation.isPending} onClick={() => disburseMutation.mutate({ id: disbursementLoan.id, destination: disbursementDestination })} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                {disburseMutation.isPending ? 'Processing...' : `Confirm ${disbursementDestination === 'cash' ? 'Cash Received' : 'Savings Credit'}`}
                            </button>
                        </div>
                    </section>
                </div>
            )}
            <ConfirmDialog
                isOpen={confirmState.isOpen}
                onClose={() => setConfirmState(s => ({ ...s, isOpen: false }))}
                title={confirmState.title}
                message={confirmState.message}
                confirmText={confirmState.confirmText}
                variant={confirmState.variant}
                onConfirm={confirmState.onConfirm}
            />
        </>
    );
}
