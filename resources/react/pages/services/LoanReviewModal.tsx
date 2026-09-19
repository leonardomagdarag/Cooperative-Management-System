import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import ServiceModal from './ServiceModal';

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
const fmtDate = (iso?: string) => new Date(iso ?? new Date()).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
const fmtDateTime = (iso: string) => new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

const statusPill = (s: string | null): string => {
    switch (s) {
        case 'under_review': return 'bg-blue-100 text-blue-700';
        case 'endorsed_for_approval': return 'bg-amber-100 text-amber-700';
        case 'review_rejected': return 'bg-red-100 text-red-700';
        default: return 'bg-gray-100 text-gray-700';
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
    const canFinalApprove = user?.primary_role === 'admin' || user?.primary_role === 'manager';

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
        a === 'reject' ? 'border-red-200' : a === 'endorse' ? 'border-green-200' : 'border-blue-200';

    const backButton = (
        <button onClick={backToList} className="text-white hover:text-indigo-200 transition-colors">
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
    );

    return (
        <ServiceModal
            gradient="from-indigo-600 to-violet-700"
            title={selectedLoanId ? 'Loan Application Detail' : 'Loan Application Review'}
            subtitle={selectedLoanId ? 'Review and take action on this application' : `${loans.length} application(s) awaiting review`}
            subtitleClassName="text-indigo-200 text-sm mt-0.5"
            sticky
            maxWidth="max-w-5xl"
            backButton={selectedLoanId ? backButton : undefined}
            onClose={onRequestClose}
        >
            <div className="p-6">
                {isLoading ? (
                    <div className="space-y-4">
                        {[...Array(3)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />)}
                    </div>
                ) : !selectedLoanId ? (
                    loans.length > 0 ? (
                        <div className="space-y-3">
                            {loans.map((loan: any) => (
                                <div key={loan.id} className="bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-indigo-300 transition-all">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-4">
                                            <div className="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i className="fas fa-file-contract text-indigo-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-bold text-gray-900">{loan.loan_number}</span>
                                                    <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${statusPill(loan.review_status)}`}>
                                                        {loan.review_status_label ?? fallbackLabel(loan.review_status)}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-gray-700 font-medium">
                                                    {loan.borrower?.name ?? 'Unknown'}
                                                    <span className="text-gray-500 font-normal">({loan.borrower?.user_number ?? 'N/A'})</span>
                                                </p>
                                                <p className="text-xs text-gray-500 mt-0.5">
                                                    {capitalize(loan.loan_type)} Loan · {peso(loan.loan_amount)} · Applied {loan.applied_at ? fmtDate(loan.applied_at) : 'N/A'}
                                                </p>
                                            </div>
                                        </div>
                                        <button onClick={() => setSelectedLoanId(loan.id)}
                                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors flex-shrink-0">
                                            <i className="fas fa-eye mr-1.5"></i>Review
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <div className="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i className="fas fa-clipboard-check text-indigo-400 text-4xl"></i>
                            </div>
                            <h4 className="text-lg font-bold text-gray-900 mb-2">No Applications to Review</h4>
                            <p className="text-gray-500 mb-6">All loan applications have been processed or are awaiting member submission.</p>
                            <button type="button" onClick={onComplete} className="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700">
                                Close
                            </button>
                        </div>
                    )
                ) : !selectedLoan ? (
                    <div className="text-center py-12">
                        <p className="text-gray-500">Loan not found. It may have been updated.</p>
                        <button onClick={backToList} className="mt-4 text-indigo-600 hover:underline">← Back to list</button>
                    </div>
                ) : (
                    <div className="space-y-6">
                        <div className="bg-gray-50 rounded-xl p-5 border border-gray-200">
                            <div className="flex items-start justify-between mb-4">
                                <div>
                                    <h4 className="text-lg font-bold text-gray-900">{selectedLoan.loan_number}</h4>
                                    <p className="text-sm text-gray-600 mt-0.5">
                                        {selectedLoan.borrower?.name ?? 'Unknown'} ({selectedLoan.borrower?.user_number ?? 'N/A'})
                                    </p>
                                </div>
                                <span className={`px-3 py-1 text-sm font-semibold rounded-full ${statusPill(selectedLoan.review_status)}`}>
                                    {selectedLoan.review_status_label ?? fallbackLabel(selectedLoan.review_status)}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p className="text-gray-500">Loan Type</p>
                                    <p className="font-semibold text-gray-900">{capitalize(selectedLoan.loan_type)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Amount</p>
                                    <p className="font-semibold text-gray-900">{peso(selectedLoan.loan_amount)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Term / Frequency</p>
                                    <p className="font-semibold text-gray-900">{selectedLoan.term_months}mo · {capitalize(selectedLoan.payment_frequency)}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Applied On</p>
                                    <p className="font-semibold text-gray-900">{selectedLoan.applied_at ? fmtDate(selectedLoan.applied_at) : '—'}</p>
                                </div>
                            </div>

                            {selectedLoan.interview_schedule && (
                                <div className="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-gray-500">Interview Scheduled</p>
                                        <p className="font-semibold text-gray-900">{fmtDateTime(selectedLoan.interview_schedule)}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500">Interview Status</p>
                                        <p className="font-semibold text-gray-900">{interviewLabel(selectedLoan)}</p>
                                    </div>
                                </div>
                            )}

                            {(selectedLoan.co_makers ?? []).length > 0 && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                        Co-makers ({selectedLoan.co_makers.length})
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {selectedLoan.co_makers.map((m: any) => (
                                            <span key={m.user_number} className="px-3 py-1 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-700">
                                                {m.name}
                                                {m.has_approved
                                                    ? <i className="fas fa-check-circle text-green-500 ml-1"></i>
                                                    : <i className="fas fa-clock text-gray-400 ml-1"></i>}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {selectedLoan.review_notes && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Review Notes</p>
                                    <p className="text-sm text-gray-700">{selectedLoan.review_notes}</p>
                                    {selectedLoan.reviewer && (
                                        <p className="text-xs text-gray-400 mt-1">— {selectedLoan.reviewer}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {selectedLoan.review_status === null && (
                            <div className="bg-indigo-50 border border-indigo-200 rounded-xl p-5">
                                <p className="text-sm text-indigo-700 mb-4">
                                    <i className="fas fa-info-circle mr-1.5"></i>
                                    This application has not been picked up yet. Start the review to take ownership and notify the member.
                                </p>
                                <div className="flex gap-3">
                                    <button onClick={backToList}
                                        className="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                                        Back
                                    </button>
                                    <button onClick={startReview} disabled={reviewMutation.isPending}
                                        className="flex-1 px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50">
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
                                            className={`flex-1 px-4 py-2.5 rounded-lg font-medium transition-all text-sm ${loanReviewAction === 'interview_complete' ? 'bg-blue-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-blue-300'}`}>
                                            <i className="fas fa-calendar-check mr-1.5"></i>Mark Interview Complete
                                        </button>
                                    )}
                                    <button onClick={() => pickAction('endorse')}
                                        className={`flex-1 px-4 py-2.5 rounded-lg font-medium transition-all text-sm ${loanReviewAction === 'endorse' ? 'bg-green-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-green-300'}`}>
                                        <i className="fas fa-thumbs-up mr-1.5"></i>Endorse for Approval
                                    </button>
                                    <button onClick={() => pickAction('reject')}
                                        className={`flex-1 px-4 py-2.5 rounded-lg font-medium transition-all text-sm ${loanReviewAction === 'reject' ? 'bg-red-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-red-300'}`}>
                                        <i className="fas fa-times-circle mr-1.5"></i>Reject
                                    </button>
                                </div>

                                {loanReviewAction && (
                                    <div className={`bg-gray-50 rounded-xl p-4 border-2 ${borderFor(loanReviewAction)}`}>
                                        <div className="mb-4">
                                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                                {loanReviewAction === 'reject' ? 'Rejection Reason *' : 'Review Notes (optional)'}
                                            </label>
                                            <textarea
                                                rows={3}
                                                value={loanReviewNotes}
                                                onChange={e => { setLoanReviewNotes(e.target.value); if (notesError) setNotesError(''); }}
                                                placeholder={loanReviewAction === 'reject' ? 'Explain why this application is being rejected (minimum 10 characters)...' : 'Add any notes for the record...'}
                                                className={`w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 ${loanReviewAction === 'reject' ? 'focus:ring-red-400' : 'focus:ring-indigo-400'} ${notesError ? 'border-red-400' : 'border-gray-300'}`}
                                            />
                                            {notesError && <span className="text-red-500 text-xs">{notesError}</span>}
                                        </div>

                                        <div className="flex gap-3">
                                            <button onClick={() => setLoanReviewAction(null)}
                                                className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">
                                                Cancel
                                            </button>
                                            {loanReviewAction === 'interview_complete' && (
                                                <button onClick={() => confirmAction('interview_complete')} disabled={reviewMutation.isPending}
                                                    className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                                                    <i className="fas fa-check mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Confirm Interview Complete'}
                                                </button>
                                            )}
                                            {loanReviewAction === 'endorse' && (
                                                <button onClick={() => confirmAction('endorse')} disabled={reviewMutation.isPending}
                                                    className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50">
                                                    <i className="fas fa-thumbs-up mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Endorse for Approval'}
                                                </button>
                                            )}
                                            {loanReviewAction === 'reject' && (
                                                <button onClick={() => confirmAction('reject')} disabled={reviewMutation.isPending}
                                                    className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
                                                    <i className="fas fa-times mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Confirm Rejection'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {!loanReviewAction && (
                                    <button onClick={backToList}
                                        className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors text-sm">
                                        Back to List
                                    </button>
                                )}
                            </div>
                        )}

                        {selectedLoan.review_status === 'endorsed_for_approval' && (
                            <div className="bg-amber-50 border border-amber-200 rounded-xl p-5">
                                {canFinalApprove ? (
                                    <>
                                        <p className="text-sm text-amber-800 mb-4">
                                            <i className="fas fa-star mr-1.5"></i>
                                            This application has been endorsed by an officer and is ready for your final approval.
                                        </p>
                                        <div className="space-y-3">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1.5">Notes (optional)</label>
                                                <textarea
                                                    rows={2}
                                                    value={loanReviewNotes}
                                                    onChange={e => setLoanReviewNotes(e.target.value)}
                                                    placeholder="Any final notes..."
                                                    className={`w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-amber-400 ${notesError ? 'border-red-400' : 'border-gray-300'}`}
                                                />
                                                {notesError && <span className="text-red-500 text-xs">{notesError}</span>}
                                            </div>
                                            <div className="flex gap-3">
                                                <button onClick={backToList}
                                                    className="px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">
                                                    Back
                                                </button>
                                                <button onClick={() => { setLoanReviewAction(null); reviewMutation.mutate({ loanId: selectedLoan.id, action: 'return_for_review', notes: loanReviewNotes.trim() || undefined }); }}
                                                    disabled={reviewMutation.isPending}
                                                    className="px-4 py-2.5 bg-yellow-500 text-white rounded-lg text-sm font-medium hover:bg-yellow-600 transition-colors disabled:opacity-50">
                                                    <i className="fas fa-undo mr-1.5"></i>Return for Review
                                                </button>
                                                <button onClick={() => { setLoanReviewAction(null); reviewMutation.mutate({ loanId: selectedLoan.id, action: 'approve', notes: loanReviewNotes.trim() || undefined }); }}
                                                    disabled={reviewMutation.isPending}
                                                    className="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors disabled:opacity-50">
                                                    <i className="fas fa-check-circle mr-1.5"></i>{reviewMutation.isPending ? 'Processing...' : 'Final Approve'}
                                                </button>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm text-amber-800">
                                            <i className="fas fa-clock mr-1.5"></i>
                                            This application has been endorsed and is now awaiting final approval from the admin or chairman. No further action is required from you.
                                        </p>
                                        <button onClick={backToList}
                                            className="mt-4 w-full px-4 py-2.5 border border-amber-300 text-amber-700 rounded-lg text-sm font-medium hover:bg-amber-100 transition-colors">
                                            Back to List
                                        </button>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </ServiceModal>
    );
}