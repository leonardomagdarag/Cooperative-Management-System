import DateRangeFilter from '../../components/ui/DateRangeFilter';
import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import Modal from '../../components/ui/Modal';
import { useAuth } from '../../contexts/AuthContext';

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const padId = (n: number | string) => String(n).padStart(8, '0');
const fmtDate = (iso?: string) => new Date(iso ?? new Date().toISOString()).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

export default function TransactionApproval({ onComplete, onRequestClose }: { onComplete: () => void; onRequestClose: () => void }) {
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const { user } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();

    // Filters from URL
    const typeFilter = searchParams.get('type') ? searchParams.get('type')!.split(',') : [];
    const dateFrom = searchParams.get('date_from') || '';
    const dateTo = searchParams.get('date_to') || '';
    const branchId = searchParams.get('branch_id') || '';
    const recordedBy = searchParams.get('recorded_by') || '';
    const amountMin = searchParams.get('amount_min') || '';
    const amountMax = searchParams.get('amount_max') || '';
    const excludeMine = searchParams.get('exclude_mine') === 'true';
    const searchQuery = searchParams.get('search') || '';

    const updateFilter = (key: string, value: string | boolean | null) => {
        setSearchParams(prev => {
            const next = new URLSearchParams(prev);
            if (value === null || value === '' || value === false) next.delete(key);
            else next.set(key, String(value));
            return next;
        }, { replace: true });
    };

    const toggleType = (t: string) => {
        const nextTypes = typeFilter.includes(t) ? typeFilter.filter(x => x !== t) : [...typeFilter, t];
        updateFilter('type', nextTypes.length > 0 ? nextTypes.join(',') : null);
    };

    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [action, setAction] = useState<'approve' | 'reject' | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [rejectError, setRejectError] = useState('');
    const [otpCode, setOtpCode] = useState('');
    const [otpError, setOtpError] = useState('');
    const [otpSent, setOtpSent] = useState(false);

    const { data: serviceInfo } = useQuery({
        queryKey: ['services-info'],
        queryFn: () => api.get('/api/services'),
    });
    const otpThreshold = Number(serviceInfo?.data?.otp_threshold ?? 2000);

    const qs = new URLSearchParams();
    if (typeFilter.length > 0) qs.set('type', typeFilter.join(','));
    if (dateFrom) qs.set('date_from', dateFrom);
    if (dateTo) qs.set('date_to', dateTo);
    if (branchId) qs.set('branch_id', branchId);
    if (recordedBy) qs.set('recorded_by', recordedBy);
    if (amountMin) qs.set('amount_min', amountMin);
    if (amountMax) qs.set('amount_max', amountMax);
    if (excludeMine) qs.set('exclude_mine', '1');
    if (searchQuery) qs.set('search', searchQuery);

    const { data, isLoading, isFetching } = useQuery({
        queryKey: ['pending-transactions', qs.toString()],
        queryFn: () => api.get(`/api/services/pending?${qs.toString()}`),
    });
    const transactions = data?.data?.data ?? [];
    const branches = [...new Map(transactions.filter((t: any) => t.branch_id).map((t: any) => [t.branch_id, t.branch_name])).entries()];
    const recorders = [...new Map(transactions.filter((t: any) => t.recorded_by_id).map((t: any) => [t.recorded_by_id, t.recorded_by])).entries()];
    const selected = selectedId ? transactions.find((t: any) => t.id === selectedId) ?? null : null;
    const requiresOtp = !!selected && selected.transaction_type === 'withdrawal' && Number(selected.amount) >= otpThreshold;

    const resetForm = () => {
        setSelectedId(null);
        setAction(null);
        setRejectReason('');
        setRejectError('');
        setOtpCode('');
        setOtpError('');
        setOtpSent(false);
    };

    const sendOtpMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/services/transactions/${id}/send-otp`),
        onSuccess: () => { setOtpSent(true); setOtpError(''); addToast('Verification code sent to your email', 'success'); },
        onError: (err: any) => { setOtpError(err.response?.data?.message || 'Failed to send code'); addToast(err.response?.data?.message || 'Failed to send code', 'error'); },
    });

    const approveMutation = useMutation({
        mutationFn: ({ id, reason, otp }: { id: number; reason?: string; otp?: string }) =>
            api.post(`/api/services/approve/${id}`, { action: 'approve', reason, otp_code: otp }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['pending-transactions'] });
            queryClient.invalidateQueries({ queryKey: ['services-pending'] });
            addToast('Transaction approved', 'success');
            resetForm();
        },
        onError: (err: any) => { addToast(err.response?.data?.message || 'Failed', 'error'); resetForm(); },
    });

    const rejectMutation = useMutation({
        mutationFn: ({ id, reason }: { id: number; reason: string }) =>
            api.post(`/api/services/approve/${id}`, { action: 'reject', reason }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['pending-transactions'] });
            queryClient.invalidateQueries({ queryKey: ['services-pending'] });
            addToast('Transaction rejected', 'success');
            resetForm();
        },
        onError: (err: any) => { addToast(err.response?.data?.message || 'Failed', 'error'); resetForm(); },
    });

    const confirmApprove = () => {
        if (!selected) return;
        if (requiresOtp && otpCode.trim() === '') {
            setOtpError('The approval OTP is required for this withdrawal.');
            return;
        }
        approveMutation.mutate({ id: selected.id, otp: requiresOtp ? otpCode.trim() : undefined });
    };

    const confirmReject = () => {
        if (!selected) return;
        if (!rejectReason.trim()) {
            setRejectError('Rejection reason is required.');
            return;
        }
        rejectMutation.mutate({ id: selected.id, reason: rejectReason.trim() });
    };

    const getTypeColor = (t: string) => {
        if (t === 'deposit') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        if (t === 'withdrawal') return 'bg-rose-100 text-rose-800 border-rose-200';
        if (t === 'loan_payment') return 'bg-blue-100 text-blue-800 border-blue-200';
        return 'bg-slate-100 text-slate-800 border-slate-200';
    };

    const getTypeIcon = (t: string) => {
        if (t === 'deposit') return 'fa-arrow-down';
        if (t === 'withdrawal') return 'fa-arrow-up';
        if (t === 'loan_payment') return 'fa-money-bill-wave';
        return 'fa-receipt';
    };

    return (
        <Modal
            open={true}
            onClose={onRequestClose}
            title="Approve Transactions"
            size="2xl"
            bodyClassName="p-0 bg-slate-50"
            headerClassName="bg-white border-b border-slate-200 p-6"
        >
            <div className="flex flex-col md:flex-row h-[70vh]">
                {/* Sidebar Filters */}
                <div className="w-full md:w-64 bg-white border-r border-slate-200 p-6 shrink-0 overflow-y-auto">
                    <h3 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4">Filters</h3>
                    
                    <div className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Search</label>
                            <input 
                                type="text"
                                placeholder="Name or loan #..."
                                value={searchQuery}
                                onChange={e => updateFilter('search', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-brand-500 focus:border-brand-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Type</label>
                            <div className="space-y-2">
                                {['deposit', 'withdrawal', 'loan_payment'].map(t => (
                                    <label key={t} className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                        <input 
                                            type="checkbox"
                                            checked={typeFilter.includes(t)}
                                            onChange={() => toggleType(t)}
                                            className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        />
                                        <span className="capitalize">{t.replace('_', ' ')}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <DateRangeFilter from={dateFrom} to={dateTo}
                            onFromChange={value => updateFilter('date_from', value)}
                            onToChange={value => updateFilter('date_to', value)}
                            onRangeChange={(from, to) => setSearchParams(prev => {
                                const next = new URLSearchParams(prev);
                                if (from) next.set('date_from', from); else next.delete('date_from');
                                if (to) next.set('date_to', to); else next.delete('date_to');
                                return next;
                            }, { replace: true })} />

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Branch</label>
                            <select value={branchId} onChange={e => updateFilter('branch_id', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-700 focus:ring-brand-500 focus:border-brand-500">
                                <option value="">All branches</option>
                                {branches.map(([id, name]: any) => <option key={id} value={id}>{name || `Branch ${id}`}</option>)}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Recorded by</label>
                            <select value={recordedBy} onChange={e => updateFilter('recorded_by', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-700 focus:ring-brand-500 focus:border-brand-500">
                                <option value="">Anyone</option>
                                {recorders.map(([id, name]: any) => <option key={id} value={id}>{name || `User ${id}`}</option>)}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-2">Amount range</label>
                            <div className="grid grid-cols-2 gap-2">
                                <input type="number" min="0" step="0.01" aria-label="Minimum amount" placeholder="Min" value={amountMin} onChange={e => updateFilter('amount_min', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-brand-500 focus:border-brand-500" />
                                <input type="number" min="0" step="0.01" aria-label="Maximum amount" placeholder="Max" value={amountMax} onChange={e => updateFilter('amount_max', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-brand-500 focus:border-brand-500" />
                            </div>
                        </div>

                        <div>
                            <label className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none">
                                <input 
                                    type="checkbox"
                                    checked={excludeMine}
                                    onChange={e => updateFilter('exclude_mine', e.target.checked)}
                                    className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                />
                                <span>Exclude my recorded</span>
                            </label>
                            <p className="text-xs text-slate-700 mt-1 ml-6">
                                Hide transactions you recorded (you cannot approve them).
                            </p>
                        </div>
                    </div>
                </div>

                {/* Main Content */}
                <div className="flex-1 overflow-y-auto p-6 relative bg-slate-50">
                    <div className="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        <strong>Approval workflow:</strong> Staff-recorded member counter cash posts immediately. Member requests and online or bank transfers stay pending for a manager or chairman. Managers can approve only their branch when branch bypass is off; when bypass is on, only the chairman can approve. Xendit must verify payment before approval.
                    </div>
                    {isFetching && (
                        <div className="absolute top-4 right-4">
                            <i className="fas fa-circle-notch fa-spin text-brand-500 text-xl"></i>
                        </div>
                    )}

                    {isLoading ? (
                        <div className="space-y-4">
                            {[...Array(3)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-xl bg-slate-200" />)}
                        </div>
                    ) : transactions.length === 0 ? (
                        <div className="text-center py-16">
                            <div className="w-16 h-16 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i className="fas fa-inbox text-slate-600 text-3xl"></i>
                            </div>
                            <h4 className="text-lg font-bold text-slate-900 mb-1">No transactions found</h4>
                            <p className="text-slate-700 mb-6 text-sm">Try adjusting your filters, or you are all caught up.</p>
                            <button type="button" onClick={onComplete} className="ui-btn ui-btn-secondary">
                                Close Modal
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex justify-between items-end mb-4 border-b border-slate-200 pb-2">
                                <h4 className="text-slate-700 font-medium text-sm">{transactions.length} pending transaction{transactions.length === 1 ? '' : 's'}</h4>
                            </div>
                            
                            {transactions.map((t: any) => {
                                const isSelected = selectedId === t.id;
                                const isRecordedByCurrentUser = Number(t.recorded_by_id) === Number(user?.id);
                                const isSelfApprovalBlocked = isRecordedByCurrentUser && !user?.permissions?.includes('transactions.approve-own');
                                const typeBadgeClass = getTypeColor(t.transaction_type);
                                return (
                                    <div key={t.id} className={`bg-white border rounded-xl p-5 transition-all ${isSelected ? 'border-brand-400 ring-1 ring-brand-400 shadow-sm' : 'border-slate-200 hover:border-brand-200 shadow-sm'}`}>
                                        <div className="flex items-start justify-between mb-4">
                                            <div className="flex items-start gap-4">
                                                <div className={`w-10 h-10 shrink-0 rounded-full flex items-center justify-center border ${typeBadgeClass}`}>
                                                    <i className={`fas ${getTypeIcon(t.transaction_type)}`}></i>
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${typeBadgeClass} uppercase tracking-wider`}>
                                                            {t.transaction_type.replace('_', ' ')}
                                                        </span>
                                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full border bg-slate-100 text-slate-600 border-slate-200 uppercase tracking-wider">
                                                            Pending
                                                        </span>
                                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full border bg-indigo-50 text-indigo-700 border-indigo-200 uppercase tracking-wider">
                                                            {String(t.transaction_mode || 'counter').replace('_', ' ')}
                                                        </span>
                                                        {t.provider_payment_verified && (
                                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200 uppercase tracking-wider">
                                                                Xendit verified
                                                            </span>
                                                        )}
                                                        {isRecordedByCurrentUser && (
                                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full border bg-amber-50 text-amber-800 border-amber-200 uppercase tracking-wider">
                                                                Recorded by you
                                                            </span>
                                                        )}
                                                    </div>
                                                    <h4 className="font-bold text-slate-900 text-lg">
                                                        {t.account_holder ?? 'N/A'}
                                                    </h4>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-xl font-bold text-slate-900">{peso(t.amount)}</p>
                                                {Number(t.fee_amount) > 0 && <p className="text-xs font-medium text-amber-700">Fee {peso(t.fee_amount)}</p>}
                                                {Number(t.fee_amount) > 0 && <p className="text-xs text-slate-600">Total {peso(Number(t.amount) + Number(t.fee_amount))}</p>}
                                                <p className="text-xs text-slate-700 mt-1">{fmtDate(t.created_at)}</p>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-y-2 gap-x-4 mb-5 text-sm">
                                            <div>
                                                <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Account Type</span>
                                                <p className="font-medium text-slate-900 mt-0.5">
                                                    {t.account_type ? String(t.account_type).replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()) : 'N/A'}
                                                </p>
                                            </div>
                                            <div>
                                                <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Transaction ID</span>
                                                <p className="font-mono text-slate-900 mt-0.5">{padId(t.id)}</p>
                                            </div>
                                            <div>
                                                <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Recorded By</span>
                                                <p className="font-medium text-slate-900 mt-0.5">{t.recorded_by || 'Member request'}</p>
                                            </div>
                                            <div>
                                                <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Next Approver</span>
                                                <p className="font-medium text-slate-900 mt-0.5">{t.next_approver || 'Authorized officer'}</p>
                                            </div>
                                            {t.loan_number && (
                                                <div className="col-span-2">
                                                    <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Loan Number</span>
                                                    <p className="font-medium text-slate-900 mt-0.5">{t.loan_number}</p>
                                                </div>
                                            )}
                                            {t.transaction_type === 'deposit' && t.proof_of_payment_api_url && (
                                                <div className="col-span-2">
                                                    <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Proof of Payment</span>
                                                    <p className="mt-0.5">
                                                        <a href={t.proof_of_payment_api_url} target="_blank" rel="noreferrer"
                                                            className="inline-flex items-center text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">
                                                            View uploaded proof <i className="fas fa-external-link-alt ml-1 text-xs"></i>
                                                        </a>
                                                    </p>
                                                </div>
                                            )}
                                            {t.note && (
                                                <div className="col-span-2">
                                                    <span className="text-slate-700 text-xs uppercase tracking-wider font-semibold">Note</span>
                                                    <p className="text-slate-900 mt-0.5 whitespace-pre-line bg-slate-50 p-2 rounded border border-slate-100">{t.note}</p>
                                                </div>
                                            )}
                                        </div>

                                        {isSelected ? (
                                            <div className="bg-slate-100 rounded-lg p-5 border border-slate-200">
                                                {isSelfApprovalBlocked ? (
                                                    <p role="status" className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                                        You recorded this transaction. Another authorized officer must approve or reject it.
                                                    </p>
                                                ) : null}

                                                {!isSelfApprovalBlocked && (
                                                    <div className="flex gap-3 mb-5">
                                                        <button onClick={() => setAction('approve')} type="button"
                                                            className={`flex-1 py-2 rounded-lg font-medium transition-colors ${action === 'approve' ? 'bg-brand-600 text-white shadow-sm' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'}`}>
                                                            <i className="fas fa-check mr-2"></i>Approve
                                                        </button>
                                                        <button onClick={() => setAction('reject')} type="button"
                                                            className={`flex-1 py-2 rounded-lg font-medium transition-colors ${action === 'reject' ? 'bg-red-600 text-white shadow-sm' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'}`}>
                                                            <i className="fas fa-times mr-2"></i>Reject
                                                        </button>
                                                    </div>
                                                )}

                                                {!isSelfApprovalBlocked && action === 'reject' && (
                                                    <div className="mb-5">
                                                        <label className="block text-sm font-medium text-slate-700 mb-2">Rejection Reason *</label>
                                                        <textarea
                                                            rows={3}
                                                            value={rejectReason}
                                                            onChange={e => { setRejectReason(e.target.value); if (rejectError) setRejectError(''); }}
                                                            placeholder="Explain why this transaction is being rejected..."
                                                            className="w-full px-3 py-2 border border-slate-300 rounded-md focus:ring-brand-500 focus:border-brand-500"
                                                        />
                                                        {rejectError && <span className="text-red-500 text-xs mt-1 block">{rejectError}</span>}
                                                    </div>
                                                )}

                                                {!isSelfApprovalBlocked && action === 'approve' && requiresOtp && (
                                                    <div className="mb-5 rounded-lg border border-slate-200 bg-slate-100 p-4">
                                                        <p className="text-sm text-slate-700 mb-3">
                                                            OTP is required for withdrawals at or above {peso(otpThreshold)}.
                                                        </p>
                                                        <label className="block text-sm font-medium text-slate-700 mb-2">
                                                            Approval OTP
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={otpCode}
                                                            inputMode="numeric"
                                                            maxLength={6}
                                                            onChange={e => { setOtpCode(e.target.value.replace(/\D/g, '')); if (otpError) setOtpError(''); }}
                                                            placeholder="Enter 6-digit code"
                                                            className="w-full px-3 py-2 border border-slate-300 rounded-md focus:ring-brand-500 focus:border-brand-500"
                                                        />
                                                        <div className="mt-3">
                                                            <button onClick={() => sendOtpMutation.mutate(selected!.id)} type="button"
                                                                disabled={sendOtpMutation.isPending}
                                                                className="text-sm text-brand-700 hover:text-brand-800 font-medium disabled:opacity-50 hover:underline">
                                                                {sendOtpMutation.isPending ? 'Sending...' : (otpSent ? 'Resend OTP' : 'Send OTP')}
                                                            </button>
                                                        </div>
                                                        {otpError && <p className="mt-1 text-xs text-red-500">{otpError}</p>}
                                                    </div>
                                                )}
                                                <div className="flex gap-3 justify-end pt-4 border-t border-slate-200">
                                                    <button type="button" onClick={resetForm}
                                                        className="px-4 py-2 border border-transparent rounded-lg text-slate-600 font-medium hover:bg-slate-200">
                                                        Cancel
                                                    </button>
                                                    {!isSelfApprovalBlocked && action === 'approve' ? (
                                                        <button type="button" onClick={confirmApprove} disabled={approveMutation.isPending}
                                                            className="px-6 py-2 bg-brand-600 text-white rounded-lg font-medium hover:bg-brand-700 disabled:opacity-50 shadow-sm">
                                                            <i className="fas fa-check mr-2"></i>{approveMutation.isPending ? 'Processing...' : 'Confirm Approval'}
                                                        </button>
                                                    ) : !isSelfApprovalBlocked && action === 'reject' ? (
                                                        <button type="button" onClick={confirmReject} disabled={rejectMutation.isPending}
                                                            className="px-6 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 shadow-sm">
                                                            <i className="fas fa-times mr-2"></i>{rejectMutation.isPending ? 'Rejecting...' : 'Confirm Rejection'}
                                                        </button>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ) : (
                                            <button onClick={() => setSelectedId(t.id)} type="button"
                                                className="w-full py-2 bg-white text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors border border-slate-300 text-sm shadow-sm">
                                                Review Transaction
                                            </button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </Modal>
    );
}
