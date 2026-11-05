import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import ServiceModal from './ServiceModal';
import HelpTip from '../../components/ui/HelpTip';
import { useLanguage } from '../../contexts/LanguageContext';
import StepCircles from './StepCircles';
import ReceiptQR from '../../components/ReceiptQR';
import {
    getXenditStatus,
    createXenditCheckout,
    verifyXenditPayment,
    type CheckoutResponse,
} from '../../api/xendit';

interface AvailabilityFlags {
    global?: boolean;
    member?: boolean;
    officer?: boolean;
    counter?: boolean;
    online?: boolean;
    bank_transfer?: boolean;
    fee?: { mode: 'fixed' | 'percent'; value: number };
}

interface LoanOption {
    id: number;
    loan_number: string;
    loan_type: string;
    loan_type_label: string;
    loan_amount: number;
    loan_status: string;
    remaining_balance: number;
    accrued_penalty: number;
    total_paid: number;
    schedule_paid: number;
    payment_schedule: Array<{
        period: number;
        due_date: string | null;
        payment: number;
        paid: number | boolean;
        amount_due: number;
        principal: number;
        interest: number;
        penalty: number;
        balance: number;
    }>;
}

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const padId = (n: number | string) => String(n).padStart(8, '0');
const formatDateTime = (iso?: string) => new Date(iso ?? new Date().toISOString()).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.replace(/_/g, ' ');

const modeLabels: Record<string, { title: string; sub: string; icon: string }> = {
    counter: { title: 'Over the Counter', sub: 'Visit our office to make payment', icon: 'fa-cash-register' },
    bank_transfer: { title: 'Bank Transfer', sub: 'Offline bank transfer with proof of payment', icon: 'fa-landmark' },
};

function isDueDateOnOrBeforeToday(value: string | null) {
    if (!value) return false;
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return false;
    const due = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due <= today;
}

function overdueSchedules(loan?: LoanOption) {
    if (!loan?.payment_schedule) return [];
    return loan.payment_schedule
        .map(installment => ({
            period: installment.period,
            due_date: installment.due_date,
            payment_amount: Number(installment.payment),
            paid: installment.paid,
            amount_due: Math.round(Number(installment.amount_due) * 100) / 100,
            penalty_amount: Math.round(Number(installment.penalty ?? 0) * 100) / 100,
        }))
        .filter(installment => isDueDateOnOrBeforeToday(installment.due_date) && installment.amount_due > 0.009);
}

export default function LoanPaymentWizard({ onComplete, onRequestClose, selectedMember, onDirtyChange, availability }: {
    onComplete: () => void;
    onRequestClose: () => void;
    selectedMember?: any;
    onDirtyChange?: (dirty: boolean) => void;
    availability?: AvailabilityFlags;
}) {
    const { t } = useLanguage();
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ loan_id: '', transaction_mode: '', note: '' });
    const [selectedPeriods, setSelectedPeriods] = useState<number[]>([]);
    const [allocationAmounts, setAllocationAmounts] = useState<Record<number, string>>({});
    const [receipt, setReceipt] = useState<any>(null);
    const [checkoutSession, setCheckoutSession] = useState<CheckoutResponse | null>(null);
    const [proof, setProof] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const { addToast } = useToast();
    const requestIdRef = useRef<string | null>(null);

    // Fetch Xendit enabled status and active methods
    const { data: xenditData } = useQuery({
        queryKey: ['xendit-methods'],
        queryFn: getXenditStatus,
    });

    const isXenditAvailable = Boolean(xenditData?.enabled);

    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    useEffect(() => {
        onDirtyChange?.(!receipt && (form.loan_id !== '' || selectedPeriods.length > 0 || form.transaction_mode !== '' || form.note !== '' || !!proof));
    }, [form, receipt, onDirtyChange, proof, selectedPeriods]);

    const isModeAvailable = (mode: 'counter' | 'online' | 'bank_transfer' | 'xendit'): boolean => {
        if (mode === 'xendit') {
            if (!isXenditAvailable) return false;
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
    const activeLoans = loans.filter(l => l.loan_status === 'active');
    const selectedLoan = activeLoans.find(l => l.id === Number(form.loan_id));

    const availableSchedules = useMemo(() => overdueSchedules(selectedLoan), [selectedLoan]);
    const selectedSchedules = availableSchedules.filter(s => selectedPeriods.includes(s.period));
    const installmentAmount = selectedSchedules.reduce((sum, schedule) => sum + (Number(allocationAmounts[schedule.period]) || 0), 0);
    const penaltyAmount = Math.round(selectedSchedules.reduce((sum, schedule) => sum + schedule.penalty_amount, 0) * 100) / 100;
    const totalAmount = Math.round((installmentAmount + penaltyAmount) * 100) / 100;
    const feeAmount = availability?.fee?.mode === 'percent'
        ? Math.round(totalAmount * Number(availability.fee.value) / 100 * 100) / 100
        : Number(availability?.fee?.value ?? 0);

    const selectLoan = (id: string) => {
        setForm(f => ({ ...f, loan_id: id }));
        setSelectedPeriods([]);
        setAllocationAmounts({});
        setErrors({});
    };

    const toggleSchedule = (schedule: typeof availableSchedules[number], checked: boolean) => {
        setErrors(prev => ({ ...prev, installments: '', amount: '' }));
        if (checked) {
            setSelectedPeriods(periods => periods.includes(schedule.period) ? periods : [...periods, schedule.period]);
            setAllocationAmounts(amounts => ({ ...amounts, [schedule.period]: schedule.amount_due.toFixed(2) }));
        } else {
            setSelectedPeriods(periods => periods.filter(period => period !== schedule.period));
            setAllocationAmounts(amounts => {
                const next = { ...amounts };
                delete next[schedule.period];
                return next;
            });
        }
    };

    const updateAllocationAmount = (period: number, value: string, max: number) => {
        setErrors(prev => ({ ...prev, amount: '' }));
        if (value === '') {
            setAllocationAmounts(amounts => ({ ...amounts, [period]: '' }));
            return;
        }
        const numeric = Number(value);
        const capped = Number.isFinite(numeric) ? Math.min(Math.max(numeric, 0), max) : 0;
        setAllocationAmounts(amounts => ({ ...amounts, [period]: String(Math.round(capped * 100) / 100) }));
    };

    const validateStepOne = () => {
        const next: Record<string, string> = {};
        if (!form.loan_id) next.loan_id = t('Please select a loan.');
        if (selectedPeriods.length === 0) next.installments = t('Select at least one overdue installment.');
        else if (selectedSchedules.length !== selectedPeriods.length) next.installments = t('The loan schedule changed. Review the overdue installments and select them again.');
        if (!next.installments && selectedSchedules.some(schedule => {
            const amount = Number(allocationAmounts[schedule.period]);
            return !Number.isFinite(amount) || amount <= 0 || amount > schedule.amount_due;
        })) next.amount = t('Enter a positive amount no greater than the unpaid amount for each selected installment.');
        if (!next.installments && !next.amount && totalAmount > Number(selectedLoan?.remaining_balance ?? 0)) {
            next.amount = t('Payment exceeds remaining loan balance ({amount})').replace('{amount}', peso(selectedLoan?.remaining_balance ?? 0));
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
            setErrors({ transaction_mode: t('Please choose a payment method.') });
            return;
        }
        setErrors({});
        setStep(3);
    };

    const xenditCheckoutMutation = useMutation({
        mutationFn: () => {
            requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
            const note = [
                selectedMember ? 'Processed by officer' : '',
                `Loan payment for ${selectedLoan?.loan_number ?? ''}`,
                'Xendit Checkout',
                form.note,
            ].filter(Boolean).join(' | ');
            return createXenditCheckout({
                service_type: 'loan_payment',
                amount: totalAmount.toFixed(2),
                loan_id: Number(form.loan_id),
                installment_allocations: selectedSchedules.map(schedule => ({ period: schedule.period, amount: Number(allocationAmounts[schedule.period]) })),
                penalty_amount: penaltyAmount.toFixed(2),
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
            addToast(t(err.response?.data?.message || 'Failed to initialize Xendit checkout.'), 'error');
        },
    });

    const verifyMutation = useMutation({
        mutationFn: (txnNumber: string) => verifyXenditPayment(txnNumber),
        onSuccess: (res) => {
            if (res.is_paid || res.status === 'completed') {
                addToast(t('Payment verified successfully!'), 'success');
                setReceipt(res.transaction);
                setStep(5);
            } else {
                addToast(t(res.message || 'Payment has not been completed yet.'), 'warning');
            }
        },
        onError: (err: any) => {
            addToast(t(err.response?.data?.message || 'Failed to verify payment status.'), 'error');
        },
    });

    const submitMutation = useMutation({
        mutationFn: () => {
            const note = [
                selectedMember ? 'Processed by officer' : '',
                `Loan payment for ${selectedLoan?.loan_number ?? ''}`,
                form.transaction_mode === 'counter' ? 'Over the counter' : 'Bank transfer',
                form.note,
            ].filter(Boolean).join(' | ');
            const fd = new FormData();
            fd.append('loan_id', String(form.loan_id));
            fd.append('amount', totalAmount.toFixed(2));
            fd.append('penalty_amount', penaltyAmount.toFixed(2));
            selectedSchedules.forEach((schedule, index) => {
                fd.append(`installment_allocations[${index}][period]`, String(schedule.period));
                fd.append(`installment_allocations[${index}][amount]`, Number(allocationAmounts[schedule.period]).toFixed(2));
            });
            fd.append('transaction_mode', form.transaction_mode);
            fd.append('note', note || '');
            fd.append('client_request_id', requestIdRef.current!);
            if (selectedMember) fd.append('user_id', String(selectedMember.id));
            if (proof) fd.append('proof', proof);
            return api.post('/api/services/loan-payment', fd);
        },
        onSuccess: (res: any) => {
            requestIdRef.current = null;
            addToast(t(res.data?.data?.status === 'completed' ? 'Counter loan payment recorded' : 'Loan payment submitted for manager or chairman approval'), 'success');
            setReceipt(res.data?.data || { message: res.data?.message });
            setStep(form.transaction_mode === 'counter' ? 99 : form.transaction_mode === 'bank_transfer' ? 5 : 4);
        },
        onError: (err: any) => {
            requestIdRef.current = null;
            addToast(t(err.response?.data?.message || 'Failed'), 'error');
        },
    });

    const handleSubmit = () => {
        if (form.transaction_mode === 'xendit') {
            xenditCheckoutMutation.mutate();
            return;
        }
        if (form.transaction_mode === 'bank_transfer' && !proof) {
            setErrors({ proof: t('Payment proof is required for bank transfer.') });
            addToast(t('Payment proof is required for bank transfer.'), 'error');
            return;
        }
        requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
        submitMutation.mutate();
    };

    const stepCount = form.transaction_mode === 'xendit' ? 4 : 3;

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
                        <i className="fas fa-file-invoice-dollar text-gray-600 text-2xl"></i>
                    </div>
                    <h4 className="text-lg font-bold text-gray-900 mb-2">{t('No Active Loans')}</h4>
                    <p className="text-gray-600 mb-6">
                        {selectedMember
                            ? t("This member doesn't have any active loans to make payments for.")
                            : t("You don't have any active loans to make payments for.")}
                    </p>
                    <button type="button" onClick={onComplete} className="px-6 py-3 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                        Close
                    </button>
                </div>
            ) : step === 1 ? (
                <div className="p-6 space-y-4">
                    <h4 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900">{t('Enter Payment Details')} <HelpTip text="Ang loan payment ay bayad na galing sa labas ng savings account mo. Hindi ito ibabawas sa savings balance mo." /></h4>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">{t('Select Loan')} *</label>
                        <select
                            value={form.loan_id}
                            onChange={e => selectLoan(e.target.value)}
                            className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${errors.loan_id ? 'border-red-400' : 'border-gray-300'}`}
                        >
                            <option value="">-- {t('Choose a loan')} --</option>
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
                            <h5 className="font-bold text-gray-900 mb-2">{t('Loan Details')}</h5>
                            <div className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">{t('Original Amount:')}</span>
                                    <span className="font-semibold">{peso(selectedLoan.loan_amount)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">{t('Remaining Balance:')}</span>
                                    <span className="font-semibold text-blue-700">{peso(selectedLoan.remaining_balance)}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    <div>
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <div className="flex items-center gap-1.5"><label className="text-sm font-medium text-gray-700">{t('Overdue installments *')}</label><HelpTip text="Ito ang mga hulog na lampas na sa due date. Piliin ang babayaran at tingnan kung may penalty bago mag-confirm." /></div>
                            {availableSchedules.length > 0 && <span className="text-xs text-gray-700">{t('Select one or more')}</span>}
                        </div>
                        {availableSchedules.length === 0 ? (
                            <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                {t(form.loan_id ? 'No unpaid installments are due today or overdue for this loan.' : 'Select a loan to see overdue installments.')}
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {availableSchedules.map(schedule => {
                                    const checked = selectedPeriods.includes(schedule.period);
                                    return (
                                        <div key={schedule.period} className={`rounded-xl border p-4 ${checked ? 'border-blue-300 bg-blue-50/50' : 'border-gray-200'}`}>
                                            <label className="flex cursor-pointer items-start gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={event => toggleSchedule(schedule, event.target.checked)}
                                                    className="mt-1 h-4 w-4 accent-blue-600"
                                                    aria-label={`Select installment ${schedule.period}, due ${schedule.due_date}`}
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                        <span className="font-semibold text-gray-900">Installment {schedule.period}</span>
                                                        <span className="text-red-700">Due {schedule.due_date}</span>
                                                    </span>
                                                    <span className="mt-1 block text-xs text-gray-600">{t('Unpaid installment:')} {peso(schedule.amount_due)}</span>
                                                    {schedule.penalty_amount > 0 && <span className="mt-1 block text-xs font-medium text-amber-700">{t('Accrued penalty:')} {peso(schedule.penalty_amount)}</span>}
                                                </span>
                                            </label>
                                            {checked && (
                                                <div className="mt-3 pl-7">
                                                    <label htmlFor={`installment-amount-${schedule.period}`} className="mb-1 block text-xs font-medium text-gray-700">{t('Amount to pay on this installment')}</label>
                                                    <input
                                                        id={`installment-amount-${schedule.period}`}
                                                        type="number"
                                                        inputMode="decimal"
                                                        min="0.01"
                                                        max={schedule.amount_due}
                                                        step="0.01"
                                                        value={allocationAmounts[schedule.period] ?? ''}
                                                        onChange={event => updateAllocationAmount(schedule.period, event.target.value, schedule.amount_due)}
                                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                                                    />
                                                    <p className="mt-1 text-xs text-gray-700">{t('Partial payments are allowed, up to {amount}.').replace('{amount}', peso(schedule.amount_due))}</p>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        {errors.installments && <p className="mt-2 text-xs text-red-600">{errors.installments}</p>}
                        {errors.amount && <p className="mt-2 text-xs text-red-600">{errors.amount}</p>}
                    </div>

                    {selectedSchedules.length > 0 && (
                        <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm space-y-2">
                            <div className="flex justify-between"><span className="text-gray-600">{t('Selected installment amounts:')}</span><span className="font-semibold text-gray-900">{peso(installmentAmount)}</span></div>
                            <div className="flex justify-between"><span className="text-gray-600">{t('Selected accrued penalties:')}</span><span className="font-semibold text-amber-700">{peso(penaltyAmount)}</span></div>
                            <div className="flex justify-between border-t border-gray-200 pt-2"><span className="font-medium text-gray-700">{t('Total payment:')}</span><span className="font-bold text-blue-700">{peso(totalAmount)}</span></div>
                            {feeAmount > 0 && <div className="flex justify-between"><span className="text-gray-600">Fee:</span><span className="font-semibold text-amber-700">{peso(feeAmount)}</span></div>}
                            {feeAmount > 0 && <div className="flex justify-between border-t border-gray-200 pt-2"><span className="font-medium text-gray-700">Total due:</span><span className="font-bold text-blue-700">{peso(totalAmount + feeAmount)}</span></div>}
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Note (Optional)</label>
                        <textarea
                            rows={2}
                            value={form.note}
                            onChange={e => setField('note', e.target.value)}
                            placeholder={t('Any additional notes...')}
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
                    <h4 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900">{t('Choose Payment Method')} <HelpTip text="Piliin kung cash sa cooperative, online checkout, o bank transfer ang pambayad. Kailangan pa ng verification at approval ang online at bank transfer payments." /></h4>

                    <div className="space-y-3">
                        {/* Option 1: Xendit Secure Gateway (only when online payment is enabled) */}
                        {isModeAvailable('xendit') && (
                            <button
                                type="button"
                                onClick={() => { setField('transaction_mode', 'xendit'); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                                className={`w-full p-4 border-2 rounded-lg text-left transition-all ${form.transaction_mode === 'xendit' ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-300'}`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`w-12 h-12 rounded-full flex items-center justify-center ${form.transaction_mode === 'xendit' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                        <i className="fas fa-shield-alt text-xl"></i>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-gray-900">Xendit Online Payment</p>
                                            <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-100 text-blue-800">Automated</span>
                                        </div>
                                <p className="text-sm text-gray-600">{t('GCash, Maya, GrabPay, Cards, QR Ph & Direct Online Banking; approval required after verification')}</p>
                                    </div>
                                    {form.transaction_mode === 'xendit' && <i className="fas fa-check-circle text-blue-600 text-xl"></i>}
                                </div>
                            </button>
                        )}

                        {/* Option 2: Over the Counter & Offline Bank Transfer */}
                        {(['counter', 'bank_transfer'] as const).map(mode => {
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
                                            <p className="font-semibold text-gray-900">{t(ml.title)}</p>
                                            <p className="text-sm text-gray-600">{t(ml.sub)}</p>
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
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">{t('Confirm Your Payment')}</h4>

                    <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                        {selectedLoan && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Loan:</span>
                                <span className="font-semibold text-gray-900">{selectedLoan.loan_number}</span>
                            </div>
                        )}
                        {selectedSchedules.map(schedule => (
                            <div key={schedule.period} className="flex justify-between gap-4">
                                <span className="text-gray-600">Installment {schedule.period} · {schedule.due_date}:</span>
                                <span className="font-semibold text-gray-900">{peso(allocationAmounts[schedule.period] || 0)}</span>
                            </div>
                        ))}
                        {penaltyAmount > 0 && <div className="flex justify-between"><span className="text-gray-600">Penalties:</span><span className="font-semibold text-amber-700">{peso(penaltyAmount)}</span></div>}
                        <div className="flex justify-between">
                            <span className="text-gray-600">{t('Payment Amount:')}</span>
                            <span className="font-semibold text-gray-900">{peso(totalAmount)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">{t('Payment Method:')}</span>
                            <span className="font-semibold text-gray-900">
                                {form.transaction_mode === 'xendit'
                                    ? 'Xendit Online Payment'
                                    : form.transaction_mode === 'counter'
                                        ? 'Over the Counter'
                                        : t('Bank Transfer (Requires Proof)')}
                            </span>
                        </div>
                        {form.note && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Note:</span>
                                <span className="font-semibold text-gray-900">{form.note}</span>
                            </div>
                        )}
                    </div>

                    {form.transaction_mode === 'xendit' ? (
                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p className="text-sm text-blue-700">
                                <i className="fas fa-info-circle mr-2"></i>
                                {t("You will be redirected to Xendit's secure payment portal to complete your payment.")}
                            </p>
                        </div>
                    ) : form.transaction_mode === 'counter' ? (
                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p className="text-sm text-blue-700">
                                <i className="fas fa-info-circle mr-2"></i>
                                {t('Bring your payment to the office. A staff member will record the cash; member-submitted counter requests stay pending for manager or chairman approval.')}
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <label className="block text-sm font-medium text-gray-700">{t('Proof of Payment')}</label>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                onChange={e => setProof(e.target.files?.[0] || null)}
                                className={`block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 ${errors.proof ? 'border-red-400' : ''}`}
                            />
                            <p className="text-xs text-gray-700">{t('Upload a screenshot, receipt, or PDF proof of your bank transfer before submitting.')}</p>
                            <p className="text-xs text-amber-700">{t('Online and bank-transfer payments stay pending until a manager or chairman approves them. Xendit payments also wait for approval after provider verification.')}</p>
                            {proof && <p className="text-xs font-medium text-gray-700">{t('Selected:')} {proof.name}</p>}
                            {errors.proof && <span className="text-red-500 text-xs">{errors.proof}</span>}
                        </div>
                    )}

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(2)} className="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={submitMutation.isPending || xenditCheckoutMutation.isPending}
                            className="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
                        >
                            {(submitMutation.isPending || xenditCheckoutMutation.isPending) ? 'Processing...' : (<><i className="fas fa-check mr-2"></i> Confirm</>)}
                        </button>
                    </div>
                </div>
            ) : step === 4 && checkoutSession ? (
                <div className="p-6 space-y-5 text-center">
                    <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto text-blue-600 text-2xl">
                        <i className="fas fa-credit-card"></i>
                    </div>

                    <div>
                        <h4 className="text-xl font-bold text-gray-900">Xendit Checkout Session</h4>
                        <p className="text-sm text-gray-600 mt-1">{t("Complete your payment in Xendit's secure payment portal")}</p>
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
                            <span className="font-bold text-blue-600">{peso(totalAmount)}</span>
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
                            <span>{verifyMutation.isPending ? 'Verifying with Xendit...' : "I've Paid / Check Status"}</span>
                        </button>
                    </div>

                    <p className="text-xs text-gray-700 pt-2">
                        <i className="fas fa-info-circle mr-1"></i>
                        {t('After finishing the transaction on Xendit, click "I\'ve Paid / Check Status" above to verify your payment.')}
                    </p>
                </div>
            ) : step === 4 ? (
                <div className="p-6 space-y-4">
                    <div className="text-center py-8">
                        <div className="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i className="fas fa-credit-card text-blue-600 text-3xl"></i>
                        </div>
                        <h4 className="text-lg font-semibold text-gray-900 mb-2">Online Payment Portal</h4>
                        <p className="text-gray-600 mb-6">{t('Complete your external payment through online checkout or bank transfer.')}</p>

                        <div className="bg-gray-50 rounded-lg p-6 mb-6">
                            <p className="text-sm text-gray-600 mb-4">{t('Payment integration coming soon!')}</p>
                            <p className="text-2xl font-bold text-gray-900">{peso(totalAmount)}</p>
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
                        <h4 className="text-xl font-bold text-gray-900 mb-2">{t(receipt.status === 'completed' ? 'Payment Completed' : 'Payment Submitted')}</h4>
                        <p className="text-gray-600">{t(receipt.status === 'completed' ? 'Your payment was approved and applied to your loan.' : 'Your payment is pending manager or chairman approval.')}</p>
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
                            <span className="text-gray-600">Loan:</span>
                            <span className="font-semibold text-gray-900">{selectedLoan?.loan_number ?? '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm pt-3 border-t border-gray-300">
                            <span className="text-gray-600">Amount:</span>
                            <span className="text-2xl font-bold text-green-600">{peso(Number(receipt.amount ?? totalAmount))}</span>
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
                                {receipt.payment_gateway === 'xendit'
                                    ? `Xendit (${capitalize(receipt.payment_method_used || 'online')})`
                                    : receipt.transaction_mode === 'counter'
                                        ? 'Over the Counter'
                                        : receipt.transaction_mode === 'bank_transfer'
                                            ? 'Bank Transfer'
                                            : 'Online payment'}
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
                                    <ReceiptQR value={JSON.stringify({ type: 'loan_payment', ref: receipt.transaction_number ?? receipt.id ?? '', amount: totalAmount, date: receipt.transaction_date ?? new Date().toISOString() })} />
                                </div>
                                <p className="text-xs text-gray-700">{t('Scan for verification')}</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                        <p className="text-sm text-blue-700">
                            <i className="fas fa-info-circle mr-2"></i>
                            {t('Please save or print this receipt for your records.')}
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
                    <h4 className="text-xl font-bold text-gray-900 mb-2">{t('Payment Submitted!')}</h4>
                    <p className="text-gray-600 mb-6">
                        {selectedMember && form.transaction_mode === 'counter'
                            ? t('The loan payment has been processed successfully.')
                            : t('Please visit our office to complete your loan payment.')}
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
