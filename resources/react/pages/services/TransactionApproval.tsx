import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import ServiceModal from './ServiceModal';

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const padId = (n: number | string) => String(n).padStart(8, '0');
const fmtDate = (iso?: string) => new Date(iso ?? new Date().toISOString()).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

export default function TransactionApproval({ onComplete, onRequestClose }: { onComplete: () => void; onRequestClose: () => void }) {
    const queryClient = useQueryClient();
    const { addToast } = useToast();
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

    const { data, isLoading } = useQuery({
        queryKey: ['pending-transactions'],
        queryFn: () => api.get('/api/services/pending'),
    });
    const transactions = data?.data?.data ?? [];
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

    const typeIcon = (t: string) => {
        if (t === 'deposit') return { cls: 'bg-green-100 text-green-600', icon: 'fa-arrow-down' };
        if (t === 'withdrawal') return { cls: 'bg-orange-100 text-orange-600', icon: 'fa-arrow-up' };
        return { cls: 'bg-blue-100 text-blue-600', icon: 'fa-money-bill-wave' };
    };

    return (
        <ServiceModal
            gradient="from-yellow-500 to-amber-600"
            title="Approve Transactions"
            subtitle={`${transactions.length} pending transaction${transactions.length === 1 ? '' : 's'}`}
            subtitleClassName="text-sm text-yellow-100 mt-1"
            sticky
            maxWidth="max-w-5xl"
            onClose={onRequestClose}
        >
            <div className="p-6">
                {isLoading ? (
                    <div className="space-y-4">
                        {[...Array(3)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-xl bg-gray-100" />)}
                    </div>
                ) : transactions.length === 0 ? (
                    <div className="text-center py-12">
                        <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i className="fas fa-check-circle text-gray-400 text-4xl"></i>
                        </div>
                        <h4 className="text-lg font-bold text-gray-900 mb-2">All Caught Up!</h4>
                        <p className="text-gray-600 mb-6">There are no pending transactions to approve at this time.</p>
                        <button type="button" onClick={onComplete} className="px-6 py-3 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                            Close
                        </button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {transactions.map((t: any) => {
                            const icon = typeIcon(t.transaction_type);
                            const isSelected = selectedId === t.id;
                            return (
                                <div key={t.id} className="bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-yellow-300 transition-all">
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-12 h-12 rounded-full flex items-center justify-center ${icon.cls}`}>
                                                <i className={`fas ${icon.icon} text-xl`}></i>
                                            </div>
                                            <div>
                                                <h4 className="font-bold text-gray-900 text-lg">
                                                    {String(t.transaction_type).toUpperCase().replace(/_/g, ' ')}
                                                </h4>
                                                <p className="text-sm text-gray-600">{t.account_holder ?? 'N/A'}</p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-2xl font-bold text-gray-900">{peso(t.amount)}</p>
                                            <p className="text-xs text-gray-500">{fmtDate(t.created_at)}</p>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 mb-4 text-sm">
                                        <div>
                                            <span className="text-gray-600">Account Type:</span>
                                            <span className="font-semibold text-gray-900 ml-2">
                                                {t.account_type ? String(t.account_type).replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()) : 'N/A'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-gray-600">Transaction ID:</span>
                                            <span className="font-mono font-semibold text-gray-900 ml-2">{padId(t.id)}</span>
                                        </div>
                                        {t.loan_number && (
                                            <div className="col-span-2">
                                                <span className="text-gray-600">Loan:</span>
                                                <span className="font-semibold text-gray-900 ml-2">{t.loan_number}</span>
                                            </div>
                                        )}
                                        {t.transaction_type === 'deposit' && t.proof_of_payment_api_url && (
                                            <div className="col-span-2">
                                                <span className="text-gray-600">Proof of Payment:</span>
                                                <a href={t.proof_of_payment_api_url} target="_blank" rel="noreferrer"
                                                    className="ml-2 inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700">
                                                    View uploaded proof
                                                </a>
                                            </div>
                                        )}
                                        {t.note && (
                                            <div className="col-span-2">
                                                <span className="text-gray-600">Note:</span>
                                                <p className="text-gray-900 mt-1">{t.note}</p>
                                            </div>
                                        )}
                                    </div>

                                    {isSelected ? (
                                        <div className="bg-gray-50 rounded-lg p-4 border-2 border-yellow-200">
                                            <div className="flex gap-3 mb-4">
                                                <button onClick={() => setAction('approve')} type="button"
                                                    className={`flex-1 px-4 py-2 rounded-lg font-medium transition-all ${action === 'approve' ? 'bg-green-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-green-300'}`}>
                                                    <i className="fas fa-check mr-2"></i>Approve
                                                </button>
                                                <button onClick={() => setAction('reject')} type="button"
                                                    className={`flex-1 px-4 py-2 rounded-lg font-medium transition-all ${action === 'reject' ? 'bg-red-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-red-300'}`}>
                                                    <i className="fas fa-times mr-2"></i>Reject
                                                </button>
                                            </div>

                                            {action === 'reject' && (
                                                <div className="mb-4">
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">Rejection Reason *</label>
                                                    <textarea
                                                        rows={3}
                                                        value={rejectReason}
                                                        onChange={e => { setRejectReason(e.target.value); if (rejectError) setRejectError(''); }}
                                                        placeholder="Explain why this transaction is being rejected..."
                                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                                    />
                                                    {rejectError && <span className="text-red-500 text-xs">{rejectError}</span>}
                                                </div>
                                            )}

                                            {action === 'approve' && requiresOtp && (
                                                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                                    <p className="text-sm text-amber-800 mb-3">
                                                        OTP is required for withdrawals at or above {peso(otpThreshold)}.
                                                    </p>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        Approval OTP
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={otpCode}
                                                        inputMode="numeric"
                                                        maxLength={6}
                                                        onChange={e => { setOtpCode(e.target.value.replace(/\D/g, '')); if (otpError) setOtpError(''); }}
                                                        placeholder="Enter 6-digit code"
                                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                                    />
                                                    <div className="mt-3">
                                                        <button onClick={() => sendOtpMutation.mutate(selected!.id)} type="button"
                                                            disabled={sendOtpMutation.isPending}
                                                            className="text-sm text-amber-700 hover:text-amber-800 font-medium disabled:opacity-50">
                                                            {sendOtpMutation.isPending ? 'Sending...' : (otpSent ? 'Resend OTP' : 'Send OTP')}
                                                        </button>
                                                    </div>
                                                    {otpError && <p className="mt-1 text-xs text-red-500">{otpError}</p>}
                                                </div>
                                            )}

                                            <div className="flex gap-3">
                                                <button type="button" onClick={resetForm}
                                                    className="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                                    Cancel
                                                </button>
                                                {action === 'approve' ? (
                                                    <button type="button" onClick={confirmApprove} disabled={approveMutation.isPending}
                                                        className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50">
                                                        <i className="fas fa-check mr-2"></i>{approveMutation.isPending ? 'Processing...' : 'Confirm Approval'}
                                                    </button>
                                                ) : action === 'reject' ? (
                                                    <button type="button" onClick={confirmReject} disabled={rejectMutation.isPending}
                                                        className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 disabled:opacity-50">
                                                        <i className="fas fa-times mr-2"></i>{rejectMutation.isPending ? 'Rejecting...' : 'Confirm Rejection'}
                                                    </button>
                                                ) : null}
                                            </div>
                                        </div>
                                    ) : (
                                        <button onClick={() => setSelectedId(t.id)} type="button"
                                            className="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg font-medium hover:bg-yellow-600 transition-colors">
                                            <i className="fas fa-eye mr-2"></i>Review Transaction
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </ServiceModal>
    );
}