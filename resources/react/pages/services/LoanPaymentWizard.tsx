import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import ServiceModal from './ServiceModal';
import StepCircles from './StepCircles';
import ReceiptQR from '../../components/ReceiptQR';
import {
    getPayMongoMethods,
    createPayMongoCheckout,
    verifyPayMongoPayment,
    type CheckoutResponse,
} from '../../api/paymongo';

interface AvailabilityFlags {
    global?: boolean;
    member?: boolean;
    officer?: boolean;
    counter?: boolean;
    online?: boolean;
}

interface LoanOption {
    id: number;
    loan_number: string;
    loan_type: string;
    loan_type_label: string;
    loan_amount: number;
    loan_status: string;
    remaining_balance: number;
    total_paid: number;
    payment_schedule: Array<{
        period: number;
        due_date: string | null;
        payment: number;
        principal: number;
        interest: number;
        balance: number;
    }>;
}

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const padId = (n: number | string) => String(n).padStart(8, '0');
const formatDateTime = (iso?: string) => new Date(iso ?? new Date().toISOString()).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.replace(/_/g, ' ');

const modeLabels: Record<string, { title: string; sub: string; icon: string }> = {
    counter: { title: 'Over the Counter', sub: 'Visit our office to make payment', icon: 'fa-cash-register' },
    online: { title: 'Online/Digital Wallet', sub: 'GCash, Bank Transfer, or Online Banking', icon: 'fa-mobile-alt' },
};

export default function LoanPaymentWizard({ onComplete, onRequestClose, selectedMember, onDirtyChange, availability }: {
    onComplete: () => void;
    onRequestClose: () => void;
    selectedMember?: any;
    onDirtyChange?: (dirty: boolean) => void;
    availability?: AvailabilityFlags;
}) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ loan_id: '', schedule_key: '', amount: '', transaction_mode: '', note: '' });
    const [receipt, setReceipt] = useState<any>(null);
    const [checkoutSession, setCheckoutSession] = useState<CheckoutResponse | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const { addToast } = useToast();
    const requestIdRef = useRef<string | null>(null);

    // Fetch PayMongo enabled status and active methods
    const { data: paymongoData } = useQuery({
        queryKey: ['paymongo-methods'],
        queryFn: getPayMongoMethods,
    });

    const isPayMongoAvailable = Boolean(paymongoData?.enabled && paymongoData.methods.length > 0);

    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    useEffect(() => {
        onDirtyChange?.(!receipt && (form.loan_id !== '' || form.schedule_key !== '' || form.amount !== '' || form.transaction_mode !== '' || form.note !== ''));
    }, [form, receipt, onDirtyChange]);

    const isModeAvailable = (mode: 'counter' | 'online' | 'paymongo'): boolean => {
        if (mode === 'paymongo') {
            if (!isPayMongoAvailable) return false;
            if (availability && (!availability.global || availability.online === false)) return false;
            return true;
        }
        if (!availability) return true;
        if (!availability.global) return false;
        return availability[mode] !== false;
    };

    const { data: loansData, isLoading: loansLoading } = useQuery({
        queryKey: ['member-loans', selectedMember?.id],
        queryFn: () => api.get('/api/member-loans', { params: selectedMember ? { user_id: selectedMember.id } : {} }),
    });

    const loans: LoanOption[] = loansData?.data?.data ?? [];
    const activeLoans = loans.filter(l => l.loan_status === 'approved' || l.loan_status === 'active');
    const selectedLoan = activeLoans.find(l => l.id === Number(form.loan_id));

    const availableSchedules = useMemo(() => {
        if (!selectedLoan?.payment_schedule) return [];
        let cumulative = 0;
        const out: Array<{ period: number; due_date: string | null; payment_amount: number; amount_due: number }> = [];
        for (const s of selectedLoan.payment_schedule) {
            const entry = Number(s.payment);
            const covered = Math.min(Math.max(Number(selectedLoan.total_paid ?? 0) - cumulative, 0), entry);
            const amountDue = Math.round((entry - covered) * 100) / 100;
            cumulative += entry;
            if (amountDue > 0) out.push({ period: s.period, due_date: s.due_date, payment_amount: entry, amount_due: amountDue });
        }
        return out;
    }, [selectedLoan]);

    const selectedSchedule = availableSchedules.find(s => String(s.period) === form.schedule_key) ?? null;

    const selectLoan = (id: string) => {
        const loan = activeLoans.find(l => l.id === Number(id));
        let value = { loan_id: id, schedule_key: '', amount: '' };
        if (!loan) { setForm(f => ({ ...f, ...value })); return; }
        const schedules: Array<{ period: number; due_date: string | null; payment_amount: number; amount_due: number }> = [];
        let cumulative = 0;
        for (const s of loan.payment_schedule ?? []) {
            const entry = Number(s.payment);
            const covered = Math.min(Math.max(Number(loan.total_paid ?? 0) - cumulative, 0), entry);
            const amountDue = Math.round((entry - covered) * 100) / 100;
            cumulative += entry;
            if (amountDue > 0) schedules.push({ period: s.period, due_date: s.due_date, payment_amount: entry, amount_due: amountDue });
        }
        if (schedules.length === 1) {
            value = { loan_id: id, schedule_key: String(schedules[0].period), amount: String(schedules[0].amount_due) };
        }
        setErrors({});
        setForm(f => ({ ...f, ...value }));
    };

    const selectSchedule = (key: string) => {
        const s = availableSchedules.find(x => String(x.period) === key);
        setErrors(prev => ({ ...prev, schedule_key: '', amount: '' }));
        setForm(f => ({ ...f, schedule_key: key, amount: s ? String(s.amount_due) : '' }));
    };

    const validateStepOne = () => {
        const next: Record<string, string> = {};
        if (!form.loan_id) next.loan_id = 'Please select a loan.';
        if (!form.schedule_key) next.schedule_key = 'Please select a payment schedule.';
        if (!next.schedule_key && selectedSchedule && Number(form.amount) > Number(selectedLoan?.remaining_balance ?? 0)) {
            next.amount = `Payment exceeds remaining loan balance (${peso(selectedLoan?.remaining_balance ?? 0)})`;
        }
        setErrors(next);
        if (Object.keys(next).length > 0) {
            addToast(Object.values(next)[0], 'error');
            return;
        }
        setStep(2);
    };

    const validateStepTwo = () => {
        if (!form.transaction_mode) {
            setErrors({ transaction_mode: 'Please choose a payment method.' });
            return;
        }
        setErrors({});
        setStep(3);
    };

    const paymongoCheckoutMutation = useMutation({
        mutationFn: () => {
            requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
            const note = [
                selectedMember ? 'Processed by officer' : '',
                `Loan payment for ${selectedLoan?.loan_number ?? ''}`,
                'PayMongo Checkout',
                form.note,
            ].filter(Boolean).join(' | ');
            return createPayMongoCheckout({
                service_type: 'loan_payment',
                amount: form.amount,
                loan_id: Number(form.loan_id),
                note,
                client_request_id: requestIdRef.current!,
                ...(selectedMember ? { user_id: selectedMember.id } : {}),
            });
        },
        onSuccess: (data) => {
            setCheckoutSession(data);
            setStep(4);
            if (data.checkout_url) {
                window.open(data.checkout_url, '_blank', 'noopener,noreferrer');
            }
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to initialize PayMongo checkout.', 'error');
        },
    });

    const verifyMutation = useMutation({
        mutationFn: (txnNumber: string) => verifyPayMongoPayment(txnNumber),
        onSuccess: (res) => {
            if (res.is_paid || res.status === 'completed') {
                addToast('Payment verified successfully!', 'success');
                setReceipt(res.transaction);
                setStep(5);
            } else {
                addToast(res.message || 'Payment has not been completed yet.', 'warning');
            }
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to verify payment status.', 'error');
        },
    });

    const submitMutation = useMutation({
        mutationFn: () => {
            const note = [
                selectedMember ? 'Processed by officer' : '',
                `Loan payment for ${selectedLoan?.loan_number ?? ''}`,
                form.transaction_mode === 'counter' ? 'Over the counter' : 'Online payment',
                form.note,
            ].filter(Boolean).join(' | ');
            return api.post('/api/services/loan-payment', {
                loan_id: Number(form.loan_id),
                amount: Number(form.amount),
                transaction_mode: form.transaction_mode,
                note,
                client_request_id: requestIdRef.current!,
                ...(selectedMember ? { user_id: selectedMember.id } : {}),
            });
        },
        onSuccess: (res: any) => {
            requestIdRef.current = null;
            addToast('Loan payment submitted', 'success');
            setReceipt(res.data?.data || { message: res.data?.message });
            setStep(form.transaction_mode === 'counter' ? 99 : 4);
        },
        onError: (err: any) => {
            requestIdRef.current = null;
            addToast(err.response?.data?.message || 'Failed', 'error');
        },
    });

    const handleSubmit = () => {
        if (form.transaction_mode === 'paymongo') {
            paymongoCheckoutMutation.mutate();
        } else {
            requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
            submitMutation.mutate();
        }
    };

    const stepCount = (form.transaction_mode === 'paymongo' || form.transaction_mode === 'online') ? 4 : 3;

    return (
        <ServiceModal
            gradient="from-blue-500 to-cyan-600"
            title="Make Loan Payment"
            onClose={onRequestClose}
            stepIndicator={
                step < 99 && step < 5 && activeLoans.length > 0 ? (
                    <StepCircles step={step} steps={stepCount} colors={{ done: 'bg-white text-blue-600', upcoming: 'bg-blue-400 text-white', connector: 'bg-blue-400' }} />
                ) : undefined
            }
        >
            {loansLoading ? (
                <div className="p-6 space-y-4">
                    {[...Array(3)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-100" />)}
                </div>
            ) : activeLoans.length === 0 ? (
                <div className="p-6 text-center">
                    <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i className="fas fa-file-invoice-dollar text-gray-400 text-2xl"></i>
                    </div>
                    <h4 className="text-lg font-bold text-gray-900 mb-2">No Active Loans</h4>
                    <p className="text-gray-600 mb-6">
                        {selectedMember
                            ? "This member doesn't have any active loans to make payments for."
                            : "You don't have any active loans to make payments for."}
                    </p>
                    <button type="button" onClick={onComplete} className="px-6 py-3 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                        Close
                    </button>
                </div>
            ) : step === 1 ? (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Enter Payment Details</h4>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Select Loan *</label>
                        <select
                            value={form.loan_id}
                            onChange={e => selectLoan(e.target.value)}
                            className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${errors.loan_id ? 'border-red-400' : 'border-gray-300'}`}
                        >
                            <option value="">-- Choose a loan --</option>
                            {activeLoans.map(l => (
                                <option key={l.id} value={l.id}>
                                    {l.loan_number} - {capitalize(l.loan_type)} (Balance: {peso(l.remaining_balance)})
                                </option>
                            ))}
                        </select>
                        {errors.loan_id && <span className="text-red-500 text-xs">{errors.loan_id}</span>}
                    </div>

                    {selectedLoan && (
                        <div className="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                            <h5 className="font-bold text-gray-900 mb-2">Loan Details</h5>
                            <div className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Original Amount:</span>
                                    <span className="font-semibold">{peso(selectedLoan.loan_amount)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Remaining Balance:</span>
                                    <span className="font-semibold text-blue-700">{peso(selectedLoan.remaining_balance)}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Payment Schedule *</label>
                        <select
                            value={form.schedule_key}
                            onChange={e => selectSchedule(e.target.value)}
                            disabled={!form.loan_id || availableSchedules.length === 0}
                            className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${errors.schedule_key ? 'border-red-400' : 'border-gray-300'} ${!form.loan_id ? 'opacity-50' : ''}`}
                        >
                            <option value="">-- Choose a scheduled payment --</option>
                            {availableSchedules.map(s => (
                                <option key={s.period} value={s.period}>
                                    Due {s.due_date ?? '—'} - {peso(s.amount_due)}
                                </option>
                            ))}
                        </select>
                        {form.loan_id && availableSchedules.length === 0 && (
                            <p className="mt-2 text-xs text-amber-700">No unpaid scheduled installments remain for this loan.</p>
                        )}
                        {errors.schedule_key && <span className="text-red-500 text-xs">{errors.schedule_key}</span>}
                    </div>

                    {selectedSchedule && (
                        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm space-y-1">
                            <div className="flex justify-between">
                                <span className="text-gray-600">Selected Due Date:</span>
                                <span className="font-semibold text-gray-900">{selectedSchedule.due_date ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Scheduled Amount:</span>
                                <span className="font-semibold text-gray-900">{peso(selectedSchedule.payment_amount)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">Amount Due:</span>
                                <span className="font-semibold text-blue-700">{peso(selectedSchedule.amount_due)}</span>
                            </div>
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Note (Optional)</label>
                        <textarea
                            rows={2}
                            value={form.note}
                            onChange={e => setField('note', e.target.value)}
                            placeholder="Any additional notes..."
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={onRequestClose} className="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" onClick={validateStepOne} className="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Next <i className="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            ) : step === 2 ? (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Choose Payment Method</h4>

                    <div className="space-y-3">
                        {/* Option 1: PayMongo Secure Gateway (if enabled) */}
                        {isPayMongoAvailable && (
                            <button
                                type="button"
                                onClick={() => { setField('transaction_mode', 'paymongo'); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                                disabled={!isModeAvailable('paymongo')}
                                className={`w-full p-4 border-2 rounded-lg text-left transition-all ${form.transaction_mode === 'paymongo' ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-300'} ${!isModeAvailable('paymongo') ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`w-12 h-12 rounded-full flex items-center justify-center ${form.transaction_mode === 'paymongo' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                        <i className="fas fa-shield-alt text-xl"></i>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-gray-900">PayMongo Online Payment</p>
                                            <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-100 text-blue-800">Automated</span>
                                        </div>
                                        <p className="text-sm text-gray-600 truncate">GCash, Maya, GrabPay, Cards, QR Ph & Direct Online Banking</p>
                                    </div>
                                    {form.transaction_mode === 'paymongo' && <i className="fas fa-check-circle text-blue-600 text-xl"></i>}
                                </div>
                            </button>
                        )}

                        {/* Option 2: Over the Counter & Manual Online */}
                        {(['counter', 'online'] as const).map(mode => {
                            const ml = modeLabels[mode];
                            const selected = form.transaction_mode === mode;
                            const available = isModeAvailable(mode);
                            return (
                                <button
                                    key={mode}
                                    type="button"
                                    onClick={() => { setField('transaction_mode', mode); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                                    disabled={!available}
                                    className={`w-full p-4 border-2 rounded-lg text-left transition-all ${selected ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-300'} ${!available ? 'opacity-50 cursor-not-allowed' : ''}`}
                                >
                                    <div className="flex items-center gap-3">
                                        <div className={`w-12 h-12 rounded-full flex items-center justify-center ${selected ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                            <i className={`fas ${ml.icon} text-xl`}></i>
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-semibold text-gray-900">{ml.title}</p>
                                            <p className="text-sm text-gray-600">{ml.sub}</p>
                                        </div>
                                        {!available && <p className="text-xs text-amber-600"><i className="fas fa-tools mr-1"></i>Under maintenance</p>}
                                        {available && selected && <i className="fas fa-check-circle text-blue-500 text-xl"></i>}
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    {errors.transaction_mode && <span className="text-red-500 text-xs">{errors.transaction_mode}</span>}

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(1)} className="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onClick={validateStepTwo} className="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Next <i className="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            ) : step === 3 ? (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Confirm Your Payment</h4>

                    <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                        {selectedLoan && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Loan:</span>
                                <span className="font-semibold text-gray-900">{selectedLoan.loan_number}</span>
                            </div>
                        )}
                        {selectedSchedule && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Scheduled Due Date:</span>
                                <span className="font-semibold text-gray-900">{selectedSchedule.due_date ?? '—'}</span>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <span className="text-gray-600">Payment Amount:</span>
                            <span className="font-semibold text-gray-900">{peso(form.amount)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Payment Method:</span>
                            <span className="font-semibold text-gray-900">
                                {form.transaction_mode === 'paymongo'
                                    ? 'PayMongo Online Payment'
                                    : form.transaction_mode === 'counter'
                                        ? 'Over the Counter'
                                        : 'Online/Digital Wallet'}
                            </span>
                        </div>
                        {form.note && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Note:</span>
                                <span className="font-semibold text-gray-900">{form.note}</span>
                            </div>
                        )}
                    </div>

                    {form.transaction_mode === 'paymongo' ? (
                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p className="text-sm text-blue-700">
                                <i className="fas fa-info-circle mr-2"></i>
                                You will be redirected to PayMongo's secure payment portal to complete your payment.
                            </p>
                        </div>
                    ) : form.transaction_mode === 'counter' ? (
                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p className="text-sm text-blue-700">
                                <i className="fas fa-info-circle mr-2"></i>
                                Please visit our office with your payment to complete this transaction.
                            </p>
                        </div>
                    ) : (
                        <div className="bg-green-50 border-l-4 border-green-400 p-4">
                            <p className="text-sm text-green-700">
                                <i className="fas fa-info-circle mr-2"></i>
                                Your payment will be processed through our secure online payment portal.
                            </p>
                        </div>
                    )}

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(2)} className="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={submitMutation.isPending || paymongoCheckoutMutation.isPending}
                            className="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
                        >
                            {(submitMutation.isPending || paymongoCheckoutMutation.isPending) ? 'Processing...' : (<><i className="fas fa-check mr-2"></i> Confirm</>)}
                        </button>
                    </div>
                </div>
            ) : step === 4 && checkoutSession ? (
                <div className="p-6 space-y-5 text-center">
                    <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto text-blue-600 text-2xl">
                        <i className="fas fa-credit-card"></i>
                    </div>

                    <div>
                        <h4 className="text-xl font-bold text-gray-900">PayMongo Checkout Session</h4>
                        <p className="text-sm text-gray-600 mt-1">Complete your payment in PayMongo's secure payment portal</p>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-4 border border-gray-200 text-left space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Transaction Reference:</span>
                            <span className="font-mono font-bold text-gray-900">{checkoutSession.transaction_number}</span>
                        </div>
                        {selectedLoan && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Loan:</span>
                                <span className="font-semibold text-gray-900">{selectedLoan.loan_number}</span>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <span className="text-gray-600">Total Amount:</span>
                            <span className="font-bold text-blue-600">{peso(form.amount)}</span>
                        </div>
                    </div>

                    <div className="space-y-3 pt-2">
                        <a
                            href={checkoutSession.checkout_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-colors"
                        >
                            <span>Open Checkout Page</span>
                            <i className="fas fa-external-link-alt text-xs"></i>
                        </a>

                        <button
                            type="button"
                            onClick={() => verifyMutation.mutate(checkoutSession.transaction_number)}
                            disabled={verifyMutation.isPending}
                            className="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium rounded-lg shadow-sm disabled:opacity-50"
                        >
                            <i className={`fas fa-sync-alt ${verifyMutation.isPending ? 'animate-spin' : ''}`}></i>
                            <span>{verifyMutation.isPending ? 'Verifying with PayMongo...' : "I've Paid / Check Status"}</span>
                        </button>
                    </div>

                    <p className="text-xs text-gray-500 pt-2">
                        <i className="fas fa-info-circle mr-1"></i>
                        After finishing the transaction on PayMongo, click <strong>"I've Paid / Check Status"</strong> above to verify your payment.
                    </p>
                </div>
            ) : step === 4 ? (
                <div className="p-6 space-y-4">
                    <div className="text-center py-8">
                        <div className="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i className="fas fa-credit-card text-blue-600 text-3xl"></i>
                        </div>
                        <h4 className="text-lg font-semibold text-gray-900 mb-2">Online Payment Portal</h4>
                        <p className="text-gray-600 mb-6">Complete your payment through your preferred digital wallet or bank transfer</p>

                        <div className="bg-gray-50 rounded-lg p-6 mb-6">
                            <p className="text-sm text-gray-600 mb-4">Payment integration coming soon!</p>
                            <p className="text-2xl font-bold text-gray-900">{peso(form.amount)}</p>
                        </div>
                    </div>

                    <button type="button" onClick={() => setStep(5)} className="w-full px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                        Complete Payment <i className="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            ) : step === 5 && receipt ? (
                <div className="p-6">
                    <div className="text-center mb-6">
                        <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i className="fas fa-receipt text-green-600 text-3xl"></i>
                        </div>
                        <h4 className="text-xl font-bold text-gray-900 mb-2">Transaction Successful!</h4>
                        <p className="text-gray-600">Your transaction has been completed</p>
                    </div>

                    <div className="bg-gray-50 rounded-lg p-6 mb-4 space-y-3 border-2 border-dashed border-gray-300">
                        <div className="text-center pb-3 border-b border-gray-300">
                            <h5 className="font-bold text-gray-900">Settlers Credit Cooperative</h5>
                            <p className="text-sm text-gray-600">Official Receipt</p>
                        </div>

                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Transaction #:</span>
                            <span className="font-mono font-semibold text-gray-900">{padId(receipt.transaction_number ?? receipt.id ?? '')}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Date:</span>
                            <span className="font-semibold text-gray-900">{formatDateTime(receipt.transaction_date)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Type:</span>
                            <span className="font-semibold text-gray-900">LOAN PAYMENT</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Account:</span>
                            <span className="font-semibold text-gray-900">Savings</span>
                        </div>
                        <div className="flex justify-between text-sm pt-3 border-t border-gray-300">
                            <span className="text-gray-600">Amount:</span>
                            <span className="text-2xl font-bold text-green-600">{peso(Number(receipt.amount ?? form.amount))}</span>
                        </div>
                        {receipt.note && (
                            <div className="text-sm pt-3 border-t border-gray-300">
                                <span className="text-gray-600">Note:</span>
                                <p className="text-gray-900 mt-1">{receipt.note}</p>
                            </div>
                        )}
                        <div className="flex justify-between text-sm pt-3 border-t border-gray-300">
                            <span className="text-gray-600">Payment Method:</span>
                            <span className="font-semibold text-gray-900">
                                {receipt.payment_gateway === 'paymongo'
                                    ? `PayMongo (${capitalize(receipt.payment_method_used || 'online')})`
                                    : receipt.transaction_mode === 'counter'
                                        ? 'Over the Counter'
                                        : 'Online/Digital Wallet'}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Status:</span>
                            <span className={`px-2 py-1 text-xs font-semibold rounded ${
                                receipt.status === 'completed' || receipt.status === 'approved'
                                    ? 'bg-green-100 text-green-800'
                                    : receipt.status === 'rejected'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-yellow-100 text-yellow-800'
                            }`}>
                                {String(receipt.status ?? 'pending').toUpperCase()}
                            </span>
                        </div>

                        <div className="pt-4 border-t border-gray-300">
                            <div className="bg-white rounded p-4 text-center">
                                <div className="h-24 flex items-center justify-center border-2 border-dashed border-gray-300 rounded mb-2">
                                    <ReceiptQR value={JSON.stringify({ type: 'loan_payment', ref: receipt.transaction_number ?? receipt.id ?? '', amount: form.amount, date: receipt.transaction_date ?? new Date().toISOString() })} />
                                </div>
                                <p className="text-xs text-gray-500">Scan for verification</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                        <p className="text-sm text-blue-700">
                            <i className="fas fa-info-circle mr-2"></i>
                            Please save or print this receipt for your records.
                        </p>
                    </div>

                    <button type="button" onClick={onComplete} className="w-full px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        <i className="fas fa-check mr-2"></i> Done
                    </button>
                </div>
            ) : null}

            {step === 99 && (
                <div className="p-6 text-center">
                    <div className="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i className="fas fa-check text-blue-600 text-4xl"></i>
                    </div>
                    <h4 className="text-xl font-bold text-gray-900 mb-2">Payment Submitted!</h4>
                    <p className="text-gray-600 mb-6">
                        {selectedMember && form.transaction_mode === 'counter'
                            ? 'The loan payment has been processed successfully.'
                            : 'Please visit our office to complete your loan payment.'}
                    </p>

                    {receipt?.transaction_number && (
                        <div className="bg-gray-50 rounded-lg p-4 mb-6">
                            <p className="text-sm text-gray-600 mb-1">Transaction Reference</p>
                            <p className="text-2xl font-mono font-bold text-gray-900">{receipt.transaction_number}</p>
                        </div>
                    )}

                    <button type="button" onClick={onComplete} className="w-full px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                        Close
                    </button>
                </div>
            )}
        </ServiceModal>
    );
}