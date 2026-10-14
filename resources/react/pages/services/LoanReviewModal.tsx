import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import Modal from '../../components/ui/Modal';

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
const fmtDate = (iso?: string) => new Date(iso ?? new Date()).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
const fmtDateTime = (iso: string) => new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

const statusPill = (s: string | null): string => {
    switch (s) {
        case 'under_review': return 'bg-brand-50 text-brand-700 border-brand-200';
        case 'endorsed_for_approval': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'review_rejected': return 'bg-red-50 text-red-700 border-red-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const fallbackLabel = (s: string | null): string => {
    switch (s) {
        case 'under_review': return 'Under Review';
        case 'endorsed_for_approval': return 'Endorsed for Approval';
        case 'review_rejected': return 'Review Rejected';
        default: return 'Awaiting Review';
    }
};

export default function LoanReviewModal({ onComplete, onRequestClose }: { onComplete: () => void; onRequestClose: () => void }) {
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const { user } = useAuth();
    const perms = user?.permissions || [];
    const canFinalApprove = perms.includes('loans.approve');

    const [selectedLoanId, setSelectedLoanId] = useState<number | null>(null);
    const [loanReviewAction, setLoanReviewAction] = useState<'interview_complete' | 'endorse' | 'reject' | null>(null);
    const [loanReviewNotes, setLoanReviewNotes] = useState('');
    const [notesError, setNotesError] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['reviewable-loans'],
        queryFn: () => api.get('/api/loans?loan_status=pending&per_page=50'),
    });
    const loans: any[] = (data?.data?.data ?? []).filter(
        (l: any) => l.review_status === null || l.review_status === 'under_review' || l.review_status === 'endorsed_for_approval',
    );
    const selectedLoan = selectedLoanId ? loans.find((l: any) => l.id === selectedLoanId) ?? null : null;
    const isCurrentReviewer = selectedLoan && user
        ? Number(selectedLoan.reviewed_by) === user.id
        : false;

    const reviewMutation = useMutation({
        mutationFn: ({ loanId, action, notes }: { loanId: number; action: string; notes?: string }) =>
            api.post(`/api/loans/${loanId}/review`, { action, notes }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['reviewable-loans'] });
            queryClient.invalidateQueries({ queryKey: ['loan-detail'] });
            addToast('Loan review action processed', 'success');
            setLoanReviewAction(null);
            setLoanReviewNotes('');
            setNotesError('');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const backToList = () => {
        setSelectedLoanId(null);
        setLoanReviewAction(null);
        setLoanReviewNotes('');
        setNotesError('');
    };

    const pickAction = (a: 'interview_complete' | 'endorse' | 'reject') => {
        setLoanReviewAction(a);
        setLoanReviewNotes('');
        setNotesError('');
    };

    const confirmAction = (action: string) => {
        if (!selectedLoan) return;
        if (action === 'reject' && loanReviewNotes.trim().length < 10) {
            setNotesError('Rejection reason must be at least 10 characters.');
            return;
        }
        reviewMutation.mutate({ loanId: selectedLoan.id, action, notes: loanReviewNotes.trim() || undefined });
    };

    const startReview = () => {
        if (!selectedLoan) return;
        reviewMutation.mutate({ loanId: selectedLoan.id, action: 'start_review' });
    };

    const interviewLabel = (l: any) =>
        l.interview_status ? capitalize(String(l.interview_status).replace(/_/g, ' ')) : 'Pending';

    const borderFor = (a: string | null) =>
        a === 'reject' ? 'border-red-200' : a === 'endorse' ? 'border-brand-200' : 'border-blue-200';

    return (
        <Modal
            open={true}
            onClose={onRequestClose}
            title={selectedLoanId ? 'Loan Application Detail' : 'Loan Application Review'}
            size="2xl"
            bodyClassName="p-0 bg-slate-50"
            headerClassName="bg-white border-b border-slate-200 p-6"
            header={
                <div className="flex items-center justify-between p-6 bg-white border-b border-slate-200">
                    <div className="flex items-center gap-4">
                        {selectedLoanId && (
                            <button onClick={backToList} className="text-slate-500 hover:text-slate-700 transition-colors">
                                <i className="fas fa-arrow-left text-lg"></i>
                            </button>
                        )}
                        <div>
                            <h2 className="text-xl font-bold text-slate-900">{selectedLoanId ? 'Loan Application Detail' : 'Loan Application Review'}</h2>
                            <p className="text-sm text-slate-500 mt-1">
                                {selectedLoanId ? 'Review and take action on this application' : `${loans.length} application(s) awaiting review`}
                            </p>
                        </div>
                    </div>
                    <button onClick={onRequestClose} className="text-slate-400 hover:text-slate-600">
                        <i className="fas fa-times text-xl"></i>
                    </button>
                </div>
            }
        >
            <div className="p-6">
                {isLoading ? (
                    <div className="space-y-4">
                        {[...Array(3)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-slate-200" />)}
                    </div>
                ) : !selectedLoanId ? (
                    loans.length > 0 ? (
                        <div className="space-y-3">
                            {loans.map((loan: any) => (
                                <div key={loan.id} className="bg-white border rounded-xl p-5 hover:border-brand-300 transition-all shadow-sm">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-4">
                                            <div className="w-12 h-12 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i className="fas fa-file-contract text-slate-500 text-xl"></i>
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-bold text-slate-900">{loan.loan_number}</span>
                                                    <span className={`px-2 py-0.5 text-[10px] uppercase tracking-wider font-bold rounded-full border ${statusPill(loan.review_status)}`}>
                                                        {loan.review_status_label ?? fallbackLabel(loan.review_status)}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-slate-700 font-medium">
                                                    {loan.borrower?.name ?? 'Unknown'}
                                                    <span className="text-slate-500 font-normal ml-1">({loan.borrower?.user_number ?? 'N/A'})</span>
                                                </p>
                                                <p className="text-xs text-slate-500 mt-1">
                                                    {capitalize(loan.loan_type)} Loan · {peso(loan.loan_amount)} · Applied {loan.applied_at ? fmtDate(loan.applied_at) : 'N/A'}
                                                </p>
                                            </div>
                                        </div>
                                        <button onClick={() => setSelectedLoanId(loan.id)}
                                            className="px-4 py-2 bg-white text-slate-700 border border-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex-shrink-0 shadow-sm">
                                            <i className="fas fa-eye mr-1.5"></i>Review
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <div className="w-16 h-16 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i className="fas fa-clipboard-check text-slate-400 text-3xl"></i>
                            </div>
                            <h4 className="text-lg font-bold text-slate-900 mb-1">No Applications to Review</h4>
                            <p className="text-slate-500 mb-6 text-sm">All loan applications have been processed or are awaiting member submission.</p>
                            <button type="button" onClick={onComplete} className="ui-btn ui-btn-secondary">
                                Close Modal
                            </button>
                        </div>
                    )
                ) : !selectedLoan ? (
                    <div className="text-center py-12">
                        <p className="text-slate-500">Loan not found. It may have been updated.</p>
                        <button onClick={backToList} className="mt-4 text-brand-600 hover:underline">← Back to list</button>
                    </div>
                ) : (
                    <div className="space-y-6">
                        <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                            <div className="flex items-start justify-between mb-5 pb-4 border-b border-slate-100">
                                <div>
                                    <h4 className="text-lg font-bold text-slate-900">{selectedLoan.loan_number}</h4>
                                    <p className="text-sm text-slate-600 mt-0.5">
                                        {selectedLoan.borrower?.name ?? 'Unknown'} ({selectedLoan.borrower?.user_number ?? 'N/A'})
                                    </p>
                                </div>
                                <span className={`px-3 py-1 text-[10px] uppercase tracking-wider font-bold rounded-full border ${statusPill(selectedLoan.review_status)}`}>
                                    {selectedLoan.review_status_label ?? fallbackLabel(selectedLoan.review_status)}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-y-4 gap-x-4 text-sm">
                                <div>
                                    <p className="text-slate-500 text-xs uppercase tracking-wider font-semibold">Loan Type</p>
                                    <p className="font-medium text-slate-900 mt-0.5">{capitalize(selectedLoan.loan_type)}</p>
                                </div>
                                <div>
                                    <p className="text-slate-500 text-xs uppercase tracking-wider font-semibold">Amount</p>
                                    <p className="font-medium text-slate-900 mt-0.5">{peso(selectedLoan.loan_amount)}</p>
                                </div>
                                <div>
                                    <p className="text-slate-500 text-xs uppercase tracking-wider font-semibold">Term / Freq</p>
                                    <p className="font-medium text-slate-900 mt-0.5">{selectedLoan.term_months}mo · {capitalize(selectedLoan.payment_frequency)}</p>
                                </div>
                                <div>
                                    <p className="text-slate-500 text-xs uppercase tracking-wider font-semibold">Applied On</p>
                                    <p className="font-medium text-slate-900 mt-0.5">{selectedLoan.applied_at ? fmtDate(selectedLoan.applied_at) : '—'}</p>
                                </div>
                            </div>

                            {selectedLoan.interview_schedule && (
                                <div className="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-slate-500 text-xs uppercase tracking-wider font-semibold">Interview Scheduled</p>
                                        <p className="font-medium text-slate-900 mt-0.5">{fmtDateTime(selectedLoan.interview_schedule)}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-500 text-xs uppercase tracking-wider font-semibold">Interview Status</p>
                                        <p className="font-medium text-slate-900 mt-0.5">{interviewLabel(selectedLoan)}</p>
                                    </div>
                                </div>
                            )}

                            {(selectedLoan.co_makers ?? []).length > 0 && (
                                <div className="mt-4 pt-4 border-t border-slate-100">
                                    <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                                        Co-makers ({selectedLoan.co_makers.length})
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {selectedLoan.co_makers.map((m: any) => (
                                            <span key={m.user_number} className="px-3 py-1 bg-slate-50 border border-slate-200 rounded-full text-[11px] uppercase tracking-wide font-semibold text-slate-700">
                                                {m.name}
                                                {m.has_approved
                                                    ? <i className="fas fa-check-circle text-brand-500 ml-1.5"></i>
                                                    : <i className="fas fa-clock text-slate-400 ml-1.5"></i>}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {selectedLoan.review_notes && (
                                <div className="mt-4 pt-4 border-t border-slate-100">
                                    <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Review Notes</p>
                                    <p className="text-sm text-slate-700 bg-slate-50 p-2.5 rounded border border-slate-100 whitespace-pre-line">{selectedLoan.review_notes}</p>
                                    {selectedLoan.reviewer && (
                                        <p className="text-xs text-slate-400 mt-1.5 text-right">— {selectedLoan.reviewer}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {selectedLoan.review_status === null && (
                            <div className="bg-brand-50 border border-brand-200 rounded-xl p-5 shadow-sm">
                                <p className="text-sm text-brand-700 mb-4 font-medium">
                                    <i className="fas fa-info-circle mr-1.5"></i>
                                    This application has not been picked up yet. Start the review to take ownership and notify the member.
                                </p>
                                <div className="flex gap-3">
                                    <button onClick={backToList}
                                        className="px-5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50 transition-colors shadow-sm">
                                        Back
                                    </button>
                                    <button onClick={startReview} disabled={reviewMutation.isPending}
                                        className="flex-1 px-5 py-2.5 bg-brand-600 text-white rounded-lg font-medium hover:bg-brand-700 transition-colors disabled:opacity-50 shadow-sm">
                                        <i className="fas fa-play-circle mr-2"></i>{reviewMutation.isPending ? 'Starting...' : 'Start Review'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {selectedLoan.review_status === 'under_review' && (
                            <div className="space-y-4">
                                <div className="flex gap-3">
                                    {selectedLoan.interview_schedule && selectedLoan.interview_status !== 'completed' && (
                                        <button onClick={() => pickAction('interview_complete')}
                                            className={`flex-1 py-2.5 rounded-lg font-medium transition-all text-sm shadow-sm border ${loanReviewAction === 'interview_complete' ? 'bg-blue-600 text-white border-transparent' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'}`}>
                                            <i className="fas fa-calendar-check mr-1.5"></i>Interview Complete
                                        </button>
                                    )}
                                    <button onClick={() => pickAction('endorse')}
                                        className={`flex-1 py-2.5 rounded-lg font-medium transition-all text-sm shadow-sm border ${loanReviewAction === 'endorse' ? 'bg-brand-600 text-white border-transparent' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'}`}>
                                        <i className="fas fa-thumbs-up mr-1.5"></i>Endorse
                                    </button>
                                    <button onClick={() => pickAction('reject')}
                                        className={`flex-1 py-2.5 rounded-lg font-medium transition-all text-sm shadow-sm border ${loanReviewAction === 'reject' ? 'bg-red-600 text-white border-transparent' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'}`}>
                                        <i className="fas fa-times-circle mr-1.5"></i>Reject
                                    </button>
                                </div>

                                {loanReviewAction && (
                                    <div className={`bg-white rounded-xl p-5 border-2 shadow-sm ${borderFor(loanReviewAction)}`}>
                                        <div className="mb-5">
                                            <label className="block text-sm font-medium text-slate-700 mb-2">
                                                {loanReviewAction === 'reject' ? 'Rejection Reason *' : 'Review Notes (optional)'}
                                            </label>
                                            <textarea
                                                rows={3}
                                                value={loanReviewNotes}
                                                onChange={e => { setLoanReviewNotes(e.target.value); if (notesError) setNotesError(''); }}
                                                placeholder={loanReviewAction === 'reject' ? 'Explain why this application is being rejected (minimum 10 characters)...' : 'Add any notes for the record...'}
                                                className={`w-full px-3 py-2 border rounded-md text-sm focus:ring-brand-500 focus:border-brand-500 ${notesError ? 'border-red-400' : 'border-slate-300'}`}
                                            />
                                            {notesError && <span className="text-red-500 text-xs mt-1 block">{notesError}</span>}
                                        </div>

                                        <div className="flex gap-3 justify-end">
                                            <button onClick={() => setLoanReviewAction(null)}
                                                className="px-4 py-2 border border-transparent rounded-lg text-slate-600 text-sm font-medium hover:bg-slate-100">
                                                Cancel
                                            </button>
                                            {loanReviewAction === 'interview_complete' && (
                                                <button onClick={() => confirmAction('interview_complete')} disabled={reviewMutation.isPending}
                                                    className="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 shadow-sm">
                                                    <i className="fas fa-check mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Confirm Interview Complete'}
                                                </button>
                                            )}
                                            {loanReviewAction === 'endorse' && (
                                                <button onClick={() => confirmAction('endorse')} disabled={reviewMutation.isPending}
                                                    className="px-6 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50 shadow-sm">
                                                    <i className="fas fa-thumbs-up mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Confirm Endorsement'}
                                                </button>
                                            )}
                                            {loanReviewAction === 'reject' && (
                                                <button onClick={() => confirmAction('reject')} disabled={reviewMutation.isPending}
                                                    className="px-6 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50 shadow-sm">
                                                    <i className="fas fa-times mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Confirm Rejection'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {!loanReviewAction && (
                                    <button onClick={backToList}
                                        className="w-full py-2.5 bg-white border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50 transition-colors text-sm shadow-sm">
                                        Back to List
                                    </button>
                                )}
                            </div>
                        )}

                        {selectedLoan.review_status === 'endorsed_for_approval' && (
                            <div className="bg-blue-50 border border-blue-200 rounded-xl p-5 shadow-sm">
                                {canFinalApprove && !isCurrentReviewer ? (
                                    <>
                                        <p className="text-sm text-blue-800 mb-4 font-medium">
                                            <i className="fas fa-star mr-1.5"></i>
                                            This application has been endorsed by an officer and is ready for your final approval.
                                        </p>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-1.5">Notes (optional)</label>
                                                <textarea
                                                    rows={2}
                                                    value={loanReviewNotes}
                                                    onChange={e => setLoanReviewNotes(e.target.value)}
                                                    placeholder="Any final notes..."
                                                    className={`w-full px-3 py-2 border rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 ${notesError ? 'border-red-400' : 'border-slate-300'}`}
                                                />
                                                {notesError && <span className="text-red-500 text-xs mt-1 block">{notesError}</span>}
                                            </div>
                                            <div className="flex gap-3">
                                                <button onClick={backToList}
                                                    className="px-5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-700 text-sm font-medium hover:bg-slate-50 shadow-sm">
                                                    Back
                                                </button>
                                                <button onClick={() => { setLoanReviewAction(null); reviewMutation.mutate({ loanId: selectedLoan.id, action: 'return_for_review', notes: loanReviewNotes.trim() || undefined }); }}
                                                    disabled={reviewMutation.isPending}
                                                    className="px-5 py-2.5 bg-slate-600 text-white rounded-lg text-sm font-medium hover:bg-slate-700 transition-colors disabled:opacity-50 shadow-sm">
                                                    <i className="fas fa-undo mr-1.5"></i>Return for Review
                                                </button>
                                                <button onClick={() => { setLoanReviewAction(null); reviewMutation.mutate({ loanId: selectedLoan.id, action: 'approve', notes: loanReviewNotes.trim() || undefined }); }}
                                                    disabled={reviewMutation.isPending}
                                                    className="flex-1 px-5 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors disabled:opacity-50 shadow-sm">
                                                    <i className="fas fa-check-circle mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Final Approve'}
                                                </button>
                                            </div>
                                        </div>
                                    </>
                                ) : isCurrentReviewer ? (
                                    <>
                                        <p className="text-sm text-blue-800 font-medium">
                                            <i className="fas fa-user-shield mr-1.5"></i>
                                            This application has been endorsed. Separation of duties requires a different authorized officer to issue final approval.
                                        </p>
                                        <button onClick={backToList}
                                            className="mt-5 w-full py-2.5 bg-white border border-blue-300 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors shadow-sm">
                                            Back to List
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm text-blue-800 font-medium">
                                            <i className="fas fa-clock mr-1.5"></i>
                                            This application has been endorsed and is now awaiting final approval from the chairman. No further action is required from you.
                                        </p>
                                        <button onClick={backToList}
                                            className="mt-5 w-full py-2.5 bg-white border border-blue-300 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors shadow-sm">
                                            Back to List
                                        </button>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
}
