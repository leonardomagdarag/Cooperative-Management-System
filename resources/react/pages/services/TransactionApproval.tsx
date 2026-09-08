import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import { validate, transactionApprovalSchema, type FieldErrors } from '../../lib/validation';

interface CoMaker {
    name: string;
    user_number: string;
    has_approved: boolean;
}

interface ReviewableLoan {
    id: number;
    loan_number: string;
    loan_type: string;
    loan_amount: number;
    applied_at: string;
    loan_status: string;
    borrower: { name: string; user_number: string } | null;
    review_status: string | null;
    review_status_label: string;
    interview_status: string | null;
    interview_schedule: string | null;
    review_notes: string | null;
    reviewer: string | null;
    co_makers: CoMaker[];
}

export default function TransactionApproval({ onComplete }: { onComplete: () => void }) {
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const { user } = useAuth();
    const [tab, setTab] = useState<'transactions' | 'loans'>('transactions');
    const [rejectModal, setRejectModal] = useState<{ id: number } | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [rejectErrors, setRejectErrors] = useState<FieldErrors>({});
    const [approveConfirm, setApproveConfirm] = useState<{ id: number; type: string; amount: number } | null>(null);
    const [otpCode, setOtpCode] = useState('');
    const [otpSent, setOtpSent] = useState(false);
    const [otpError, setOtpError] = useState('');
    const [expandedLoanId, setExpandedLoanId] = useState<number | null>(null);
    const [loanAction, setLoanAction] = useState<{ id: number; action: string } | null>(null);
    const [loanNotes, setLoanNotes] = useState('');
    const [loanNotesError, setLoanNotesError] = useState('');

    const canFinalApprove = user?.primary_role === 'admin' || user?.primary_role === 'manager';

    const { data: serviceInfo } = useQuery({
        queryKey: ['services-info'],
        queryFn: () => api.get('/api/services'),
    });
    const otpThreshold = Number(serviceInfo?.data?.otp_threshold ?? 2000);

    const { data, isLoading } = useQuery({
        queryKey: ['pending-transactions'],
        queryFn: () => api.get('/api/services/pending'),
    });

    const { data: loansData } = useQuery({
        queryKey: ['reviewable-loans'],
        queryFn: () => api.get('/api/loans?loan_status=pending&per_page=50'),
        enabled: tab === 'loans',
    });

    const sendOtpMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/services/transactions/${id}/send-otp`),
        onSuccess: () => { setOtpSent(true); setOtpError(''); addToast('Verification code sent to your email', 'success'); },
        onError: (err: any) => { setOtpError(err.response?.data?.message || 'Failed to send code'); addToast(err.response?.data?.message || 'Failed to send code', 'error'); },
    });

    const approveMutation = useMutation({
        mutationFn: ({ id, action, reason, otp }: { id: number; action: string; reason?: string; otp?: string }) =>
            api.post(`/api/services/approve/${id}`, { action, reason, otp_code: otp }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['pending-transactions'] });
            queryClient.invalidateQueries({ queryKey: ['services-pending'] });
            addToast('Transaction processed', 'success');
            setRejectModal(null);
            setRejectReason('');
            setApproveConfirm(null);
            setOtpCode('');
            setOtpSent(false);
            setOtpError('');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const reviewMutation = useMutation({
        mutationFn: ({ loanId, action, notes }: { loanId: number; action: string; notes?: string }) =>
            api.post(`/api/loans/${loanId}/review`, { action, notes }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['reviewable-loans'] });
            queryClient.invalidateQueries({ queryKey: ['loan-detail'] });
            addToast('Loan review action processed', 'success');
            setLoanAction(null);
            setLoanNotes('');
            setLoanNotesError('');
            setExpandedLoanId(null);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const transactions = data?.data?.data ?? [];
    const loans: ReviewableLoan[] = loansData?.data?.data ?? [];

    const getTypeColor = (t: string) => {
        const c: Record<string, string> = { deposit: 'bg-emerald-100 text-emerald-700', withdrawal: 'bg-red-100 text-red-700', loan_payment: 'bg-blue-100 text-blue-700' };
        return c[t] || 'bg-gray-100 text-gray-700';
    };

    const getLoanStatusColor = (s: string) => {
        const c: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-700', approved: 'bg-emerald-100 text-emerald-700', active: 'bg-blue-100 text-blue-700', rejected: 'bg-red-100 text-red-700', paid_off: 'bg-gray-100 text-gray-700' };
        return c[s] || 'bg-gray-100 text-gray-700';
    };

    const getReviewStatusColor = (s: string | null) => {
        const c: Record<string, string> = { under_review: 'bg-blue-100 text-blue-700', endorsed_for_approval: 'bg-amber-100 text-amber-700', review_rejected: 'bg-red-100 text-red-700' };
        return c[s || ''] || 'bg-gray-100 text-gray-700';
    };

    const handleReject = (id: number) => {
        setRejectModal({ id });
        setRejectReason('');
        setRejectErrors({});
    };

    const confirmReject = () => {
        const errs = validate(transactionApprovalSchema, { action: 'reject', reason: rejectReason });
        if (Object.keys(errs).length > 0) {
            setRejectErrors(errs);
            return;
        }
        setRejectErrors({});
        approveMutation.mutate({ id: rejectModal!.id, action: 'reject', reason: rejectReason.trim() });
    };

    const handleApprove = (t: any) => {
        if (t.transaction_type === 'withdrawal') {
            setOtpCode('');
            setOtpSent(false);
            setOtpError('');
            setApproveConfirm({ id: t.id, type: t.transaction_type, amount: Number(t.amount) });
        } else {
            approveMutation.mutate({ id: t.id, action: 'approve' });
        }
    };

    const confirmApprove = () => {
        if (!approveConfirm) return;
        const requiresOtp = approveConfirm.amount >= otpThreshold;
        if (requiresOtp && otpCode.trim() === '') {
            setOtpError('A verification code is required to approve this withdrawal.');
            return;
        }
        approveMutation.mutate({
            id: approveConfirm.id,
            action: 'approve',
            otp: requiresOtp ? otpCode.trim() : undefined,
        });
    };

    const startLoanAction = (id: number, action: string) => {
        setLoanAction({ id, action });
        setLoanNotes('');
        setLoanNotesError('');
    };

    const confirmLoanAction = () => {
        if (!loanAction) return;
        const action = loanAction.action;
        if ((action === 'reject' || action === 'return_for_review') && loanNotes.trim().length < 5) {
            setLoanNotesError('A note of at least 5 characters is required for this action.');
            return;
        }
        reviewMutation.mutate({
            loanId: loanAction.id,
            action,
            notes: loanNotes.trim() || undefined,
        });
    };

    if (isLoading) {
        return (
            <div className="space-y-3">{[...Array(3)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-100" />)}</div>
        );
    }

    return (
        <div>
            <div className="mb-4 flex rounded-lg bg-gray-100 p-1">
                <button onClick={() => setTab('transactions')} className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium ${tab === 'transactions' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}>Transactions</button>
                <button onClick={() => setTab('loans')} className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium ${tab === 'loans' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}>Loan Review</button>
            </div>

            {tab === 'transactions' && (
                <>
                    {transactions.length === 0 ? (
                        <div className="rounded-lg bg-gray-50 p-8 text-center">
                            <i className="fas fa-check-circle text-3xl text-gray-300"></i>
                            <p className="mt-2 text-sm text-gray-500">No pending transactions</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-sm text-gray-500">{transactions.length} pending transaction{transactions.length !== 1 ? 's' : ''}</p>
                            {transactions.map((t: any) => (
                                <div key={t.id} className="rounded-lg border border-gray-200 p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${getTypeColor(t.transaction_type)}`}>{t.transaction_type.replace('_', ' ')}</span>
                                                <span className="text-sm font-medium text-gray-900">{t.transaction_number}</span>
                                                {t.transaction_mode && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{t.transaction_mode.replace('_', ' ')}</span>}
                                                {t.account_type && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{t.account_type}</span>}
                                                {t.loan_number && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">Loan {t.loan_number}</span>}
                                            </div>
                                            <p className="mt-1 text-sm text-gray-500">{t.account_holder || 'Unknown'} &middot; {new Date(t.created_at).toLocaleString()}</p>
                                            {t.note && <p className="mt-1 truncate text-xs text-gray-400" title={t.note}>{t.note}</p>}
                                            {t.proof_of_payment_api_url && (
                                                <a href={t.proof_of_payment_api_url} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">
                                                    <i className="fas fa-paperclip"></i> View Proof of Payment
                                                </a>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            <p className="font-bold text-gray-900">&#8369;{Number(t.amount).toLocaleString()}</p>
                                            <div className="flex gap-1">
                                                <button onClick={() => handleApprove(t)} disabled={approveMutation.isPending} className="rounded bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-700">Approve</button>
                                                <button onClick={() => handleReject(t.id)} disabled={approveMutation.isPending} className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700">Reject</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </>
            )}

            {tab === 'loans' && (
                <div className="space-y-3">
                    {loans.length === 0 ? (
                        <div className="rounded-lg bg-gray-50 p-8 text-center">
                            <i className="fas fa-file-invoice text-3xl text-gray-300"></i>
                            <p className="mt-2 text-sm text-gray-500">No loans pending review</p>
                        </div>
                    ) : (
                        <>
                            <p className="text-sm text-gray-500">{loans.length} loan{loans.length !== 1 ? 's' : ''} pending review</p>
                            {loans.map((l: ReviewableLoan) => (
                                <div key={l.id} className="rounded-lg border border-gray-200 p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${getLoanStatusColor(l.loan_status)}`}>{l.loan_status}</span>
                                                {l.review_status && <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${getReviewStatusColor(l.review_status)}`}>{l.review_status_label}</span>}
                                                <span className="text-sm font-medium text-gray-900">{l.loan_number}</span>
                                                <span className="text-xs text-gray-400">{l.loan_type}</span>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-500">
                                                {l.borrower?.name || 'Unknown'} &middot; Applied {l.applied_at}
                                                {l.reviewer ? <span className="text-gray-400"> &middot; Reviewer: {l.reviewer}</span> : null}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            <p className="font-bold text-gray-900">&#8369;{Number(l.loan_amount).toLocaleString()}</p>
                                            <button onClick={() => setExpandedLoanId(expandedLoanId === l.id ? null : l.id)} className="text-xs font-medium text-indigo-600 hover:underline">
                                                {expandedLoanId === l.id ? 'Hide details' : 'View details'}
                                            </button>
                                        </div>
                                    </div>

                                    {expandedLoanId === l.id && (
                                        <div className="mt-3 rounded-lg bg-gray-50 p-3">
                                            {l.co_makers && l.co_makers.length > 0 && (
                                                <div className="mb-2">
                                                    <p className="text-xs font-semibold text-gray-500">Co-makers</p>
                                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                                        {l.co_makers.map(cm => (
                                                            <span key={cm.user_number} className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ${cm.has_approved ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600'}`}>
                                                                <i className={`fas ${cm.has_approved ? 'fa-check-circle' : 'fa-clock'} text-[10px]`}></i>
                                                                {cm.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                            {(l.interview_schedule || l.interview_status) && (
                                                <div className="mb-2 text-xs text-gray-600">
                                                    <span className="font-semibold text-gray-500">Interview: </span>
                                                    {l.interview_status || 'not completed'}
                                                    {l.interview_schedule ? ` — ${new Date(l.interview_schedule).toLocaleString()}` : ''}
                                                </div>
                                            )}
                                            {l.review_notes && (
                                                <div className="mb-2 text-xs text-gray-600">
                                                    <span className="font-semibold text-gray-500">Review notes: </span>
                                                    {l.review_notes}
                                                </div>
                                            )}

                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {(l.review_status === null || l.review_status === 'under_review') && (
                                                    <button onClick={() => reviewMutation.mutate({ loanId: l.id, action: 'start_review' })} disabled={reviewMutation.isPending} className="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700">Start Review</button>
                                                )}
                                                {l.review_status === 'under_review' && (
                                                    <>
                                                        {l.interview_status !== 'completed' && (
                                                            <button onClick={() => reviewMutation.mutate({ loanId: l.id, action: 'interview_complete' })} disabled={reviewMutation.isPending} className="rounded bg-purple-600 px-2 py-1 text-xs font-medium text-white hover:bg-purple-700">Interview Complete</button>
                                                        )}
                                                        <button onClick={() => startLoanAction(l.id, 'endorse')} disabled={reviewMutation.isPending} className="rounded bg-amber-600 px-2 py-1 text-xs font-medium text-white hover:bg-amber-700">Endorse</button>
                                                        <button onClick={() => startLoanAction(l.id, 'reject')} disabled={reviewMutation.isPending} className="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700">Reject</button>
                                                    </>
                                                )}
                                                {l.review_status === 'under_review' && l.interview_status !== 'completed' && (
                                                    <span className="text-[11px] text-purple-700">{l.interview_status === 'scheduled' ? 'Interview scheduled' : 'Interview required'}</span>
                                                )}
                                                {l.review_status === 'endorsed_for_approval' && (
                                                    canFinalApprove ? (
                                                        <>
                                                            <button onClick={() => startLoanAction(l.id, 'approve')} disabled={reviewMutation.isPending} className="rounded bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700">Approve Loan</button>
                                                            <button onClick={() => startLoanAction(l.id, 'return_for_review')} disabled={reviewMutation.isPending} className="rounded bg-gray-600 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">Return for Review</button>
                                                        </>
                                                    ) : (
                                                        <span className="text-xs text-amber-700">Awaiting final approval from the admin or chairman.</span>
                                                    )
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </>
                    )}
                </div>
            )}

            {loanAction && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setLoanAction(null)}>
                    <div className="w-full max-w-sm rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
                        <div className="rounded-t-xl bg-gradient-to-r from-indigo-500 to-indigo-600 p-4 text-white">
                            <h3 className="font-bold">Confirm Loan Action</h3>
                        </div>
                        <div className="space-y-3 p-4">
                            <p className="text-sm text-gray-600">
                                <strong className="capitalize">{loanAction.action.replace('_', ' ')}</strong> this loan application?
                            </p>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes {loanAction.action === 'approve' ? '(optional)' : '*'}</label>
                                <textarea
                                    value={loanNotes}
                                    onChange={e => { setLoanNotes(e.target.value); if (loanNotesError) setLoanNotesError(''); }}
                                    rows={3}
                                    className={`w-full rounded-lg border px-3 py-2 text-sm ${loanNotesError ? 'border-red-400' : 'border-gray-200'}`}
                                    placeholder="Add a note about this action..."
                                />
                                {loanNotesError && <p className="mt-1 text-xs text-red-600">{loanNotesError}</p>}
                            </div>
                            <div className="flex gap-2">
                                <button onClick={() => setLoanAction(null)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                                <button onClick={confirmLoanAction} disabled={reviewMutation.isPending} className="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                    {reviewMutation.isPending ? 'Processing...' : 'Confirm'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {rejectModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setRejectModal(null)}>
                    <div className="w-full max-w-sm rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-red-500 to-red-600 p-4 text-white rounded-t-xl">
                            <h3 className="font-bold">Reject Transaction</h3>
                        </div>
                        <div className="p-4 space-y-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Rejection Reason *</label>
                                <textarea
                                    value={rejectReason}
                                    onChange={e => { setRejectReason(e.target.value); if (rejectErrors.reason) setRejectErrors({}); }}
                                    rows={3}
                                    className={`w-full rounded-lg border px-3 py-2 text-sm ${rejectErrors.reason ? 'border-red-400' : 'border-gray-200'}`}
                                    placeholder="Enter reason for rejection..."
                                />
                                {rejectErrors.reason && <p className="mt-1 text-xs text-red-600">{rejectErrors.reason}</p>}
                            </div>
                            <div className="flex gap-2">
                                <button onClick={() => setRejectModal(null)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                                <button onClick={confirmReject} disabled={approveMutation.isPending} className="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                                    {approveMutation.isPending ? 'Rejecting...' : 'Confirm Reject'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {approveConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setApproveConfirm(null)}>
                    <div className="w-full max-w-sm rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-emerald-500 to-emerald-600 p-4 text-white rounded-t-xl">
                            <h3 className="font-bold">Approve Withdrawal</h3>
                        </div>
                        <div className="p-4 space-y-3">
                            <p className="text-sm text-gray-600">You are about to approve a <strong>withdrawal</strong> transaction. This will debit the member's account. Are you sure?</p>
                            {approveConfirm.amount >= otpThreshold && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                    <p className="text-xs font-semibold text-amber-800">High-value withdrawal — verification required</p>
                                    <p className="mt-1 text-xs text-amber-700">This withdrawal is above the ₱{Number(otpThreshold).toLocaleString()} threshold. Enter the verification code sent to your email.</p>
                                    <div className="mt-2 flex gap-2">
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            maxLength={6}
                                            value={otpCode}
                                            onChange={e => { setOtpCode(e.target.value.replace(/\D/g, '')); setOtpError(''); }}
                                            placeholder="6-digit code"
                                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm tracking-[0.3em]"
                                        />
                                        <button
                                            onClick={() => sendOtpMutation.mutate(approveConfirm.id)}
                                            disabled={sendOtpMutation.isPending}
                                            className="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {sendOtpMutation.isPending ? 'Sending...' : otpSent ? 'Resend' : 'Send code'}
                                        </button>
                                    </div>
                                    {otpError && <p className="mt-1 text-xs text-red-600">{otpError}</p>}
                                </div>
                            )}
                            <div className="flex gap-2">
                                <button onClick={() => setApproveConfirm(null)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                                <button onClick={confirmApprove} disabled={approveMutation.isPending} className="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {approveMutation.isPending ? 'Approving...' : 'Confirm Approve'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}