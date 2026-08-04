import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, loanPaymentSchema, type FieldErrors } from '../../lib/validation';
import ReceiptQR from '../../components/ReceiptQR';

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
    loan_type_label: string;
    loan_amount: number;
    remaining_balance: number;
    due_date: string | null;
    payment_schedule: Array<{
        period: number;
        due_date: string | null;
        payment: number;
        principal: number;
        interest: number;
        balance: number;
    }>;
}

export default function LoanPaymentWizard({ onComplete, selectedMember, onDirtyChange, availability }: { onComplete: () => void; selectedMember?: any; onDirtyChange?: (dirty: boolean) => void; availability?: AvailabilityFlags }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ loan_id: '', amount: '', account_id: '', transaction_mode: '', note: '', period_index: '' });
    const [receipt, setReceipt] = useState<any>(null);
    const { addToast } = useToast();
    const requestIdRef = useRef<string | null>(null);
    const [errors, setErrors] = useState<FieldErrors>({});

    // Check if transaction mode is available
    const isModeAvailable = (mode: 'counter' | 'online'): boolean => {
        if (!availability) return true;
        if (!availability.global) return false;
        return availability[mode] !== false;
    };

    useEffect(() => {
        onDirtyChange?.(form.loan_id !== '' || form.amount !== '' || form.account_id !== '' || form.transaction_mode !== '' || form.note !== '');
    }, [form, onDirtyChange]);

    const { data: loansData, isLoading: loansLoading } = useQuery({
        queryKey: ['member-loans', selectedMember?.id],
        queryFn: () => api.get('/api/member-loans', { params: selectedMember ? { user_id: selectedMember.id } : {} }),
    });

    const loans: LoanOption[] = loansData?.data?.data ?? [];
    const selectedLoan = loans.find(l => l.id === Number(form.loan_id));
    const selectedPeriod = selectedLoan?.payment_schedule?.find(s => s.period === Number(form.period_index));

    const submitMutation = useMutation({
        mutationFn: () => api.post('/api/services/loan-payment', {
            loan_id: Number(form.loan_id),
            amount: Number(form.amount),
            account_id: Number(form.account_id),
            transaction_mode: form.transaction_mode,
            note: form.note || undefined,
            client_request_id: requestIdRef.current!,
            ...(selectedMember ? { user_id: selectedMember.id } : {}),
        }),
        onSuccess: (res) => { requestIdRef.current = null; addToast('Payment submitted', 'success'); setReceipt(res.data.data || { message: res.data.message }); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const selectLoan = (id: string) => {
        const loan = loans.find(l => l.id === Number(id));
        setForm(f => ({
            ...f,
            loan_id: id,
            amount: loan ? String(loan.remaining_balance) : '',
            period_index: '',
        }));
    };

    const selectPeriod = (periodIdx: string) => {
        const loan = loans.find(l => l.id === Number(form.loan_id));
        const period = loan?.payment_schedule?.find(s => s.period === Number(periodIdx));
        setForm(f => ({ ...f, period_index: periodIdx, amount: period ? String(period.payment) : f.amount }));
    };

    const validateAndNext = (stepTo: number) => {
        const errs = validate(loanPaymentSchema, form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(stepTo);
    };

    const handleSubmit = () => {
        const errs = validate(loanPaymentSchema, form);
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
        submitMutation.mutate();
    };

    if (receipt) {
        return (
            <div className="space-y-4">
                <div className="text-center">
                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-purple-100">
                        <i className="fas fa-check-circle text-2xl text-purple-600"></i>
                    </div>
                    <h3 className="font-medium text-gray-900">Payment Submitted</h3>
                    <p className="text-xs text-gray-500">Transaction receipt</p>
                </div>
                <div className="rounded-lg bg-gray-50 p-4">
                    <div className="space-y-2">
                        {receipt.transaction_number && (
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Transaction #</span><span className="text-sm font-medium text-gray-900">{receipt.transaction_number}</span></div>
                        )}
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Loan</span><span className="text-sm font-medium text-gray-900">{selectedLoan?.loan_number || form.loan_id}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Mode</span><span className="text-sm capitalize text-gray-900">{form.transaction_mode}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Date</span><span className="text-sm text-gray-900">{new Date().toLocaleString()}</span></div>
                    </div>
                    <div className="mt-4 border-t border-gray-200 pt-4">
                        <ReceiptQR value={JSON.stringify({ type: 'loan_payment', ref: receipt.transaction_number || '', amount: form.amount, date: new Date().toISOString() })} />
                    </div>
                </div>
                <div className="flex gap-2">
                    <button onClick={() => window.print()} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i className="fas fa-print mr-1"></i> Print
                    </button>
                    <button onClick={onComplete} className="flex-1 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">Close</button>
                </div>
            </div>
        );
    }

    return (
        <div>
            <div className="mb-4 flex gap-1">
                {[1, 2, 3, 4].map(s => (
                    <div key={s} className={`h-1.5 flex-1 rounded-full ${s <= step ? 'bg-purple-500' : 'bg-gray-200'}`} />
                ))}
            </div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Select Loan</h3>
                    {loansLoading ? (
                        <p className="text-sm text-gray-400">Loading loans...</p>
                    ) : loans.length === 0 ? (
                        <p className="text-sm text-gray-400">No loans found.</p>
                    ) : (
                        <div className="space-y-2">
                            {loans.map(l => (
                                <button
                                    key={l.id}
                                    onClick={() => selectLoan(String(l.id))}
                                    className={`w-full rounded-lg border p-4 text-left ${form.loan_id === String(l.id) ? 'border-purple-500 bg-purple-50' : 'border-gray-200'}`}
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium text-gray-900">{l.loan_number} &middot; {l.loan_type_label}</p>
                                        <span className="text-xs font-medium text-purple-600">&#8369;{Number(l.loan_amount).toLocaleString()}</span>
                                    </div>
                                    <div className="mt-1 flex gap-4 text-xs text-gray-500">
                                        <span>Balance: &#8369;{Number(l.remaining_balance).toLocaleString()}</span>
                                        {l.due_date && <span>Due: {l.due_date}</span>}
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                    {selectedLoan && (
                        <div className="rounded-lg bg-purple-50 p-3 text-sm">
                            <p className="text-purple-700">
                                <strong>Remaining balance:</strong> &#8369;{Number(selectedLoan.remaining_balance).toLocaleString()}
                            </p>
                            {selectedLoan.due_date && <p className="text-purple-600 text-xs mt-0.5">Due date: {selectedLoan.due_date}</p>}
                        </div>
                    )}
                    <button onClick={() => validateAndNext(2)} disabled={!form.loan_id} className="w-full rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Payment Schedule</h3>
                    {selectedLoan && selectedLoan.payment_schedule && selectedLoan.payment_schedule.length > 0 ? (
                        <div className="space-y-1.5 max-h-48 overflow-y-auto">
                            {selectedLoan.payment_schedule.map(s => (
                                <button
                                    key={s.period}
                                    onClick={() => selectPeriod(String(s.period))}
                                    className={`w-full rounded-lg border p-3 text-left text-sm ${form.period_index === String(s.period) ? 'border-purple-500 bg-purple-50' : 'border-gray-200'}`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium text-gray-900">Period {s.period}</span>
                                        <span className="text-purple-600 font-medium">&#8369;{s.payment.toLocaleString()}</span>
                                    </div>
                                    <div className="mt-0.5 flex gap-3 text-xs text-gray-500">
                                        <span>Principal: &#8369;{s.principal.toLocaleString()}</span>
                                        <span>Interest: &#8369;{s.interest.toLocaleString()}</span>
                                        {s.due_date && <span>Due: {s.due_date}</span>}
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-400">No payment schedule available for this loan.</p>
                    )}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Payment Amount</label>
                        <input type="number" step="0.01" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.amount ? 'border-red-400' : 'border-gray-200'}`} placeholder="Enter amount" />
                        {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => validateAndNext(3)} className="flex-1 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Transaction Mode</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {[{ key: 'counter', label: 'Counter' }, { key: 'online', label: 'Online' }].map(m => {
                            const disabled = !isModeAvailable(m.key as 'counter' | 'online');
                            return (
                                <button
                                    key={m.key}
                                    onClick={() => !disabled && setForm(f => ({ ...f, transaction_mode: m.key }))}
                                    disabled={disabled}
                                    className={`rounded-lg border p-4 text-left transition-colors ${form.transaction_mode === m.key ? 'border-purple-500 bg-purple-50' : disabled ? 'border-gray-200 bg-gray-50 opacity-50 cursor-not-allowed' : 'border-gray-200'}`}
                                >
                                    <p className="font-medium text-gray-900">{m.label}</p>
                                    {disabled && <p className="mt-1 text-xs text-amber-600"><i className="fas fa-tools mr-1"></i>Under maintenance</p>}
                                </button>
                            );
                        })}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Account ID</label>
                        <input type="number" value={form.account_id} onChange={e => setForm(f => ({ ...f, account_id: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.account_id ? 'border-red-400' : 'border-gray-200'}`} placeholder="Your account ID" />
                        {errors.account_id && <p className="mt-1 text-xs text-red-600">{errors.account_id}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Note (optional)</label>
                        <input type="text" value={form.note} onChange={e => setForm(f => ({ ...f, note: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => validateAndNext(4)} className="flex-1 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 4 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Confirm Payment</h3>
                    <div className="rounded-lg bg-gray-50 p-4">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Loan</span><span className="text-sm text-gray-900">{selectedLoan?.loan_number} ({selectedLoan?.loan_type_label})</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Period</span><span className="text-sm text-gray-900">{form.period_index ? `Period ${form.period_index}` : 'Custom'}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Mode</span><span className="text-sm capitalize text-gray-900">{form.transaction_mode}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Account ID</span><span className="text-sm text-gray-900">#{form.account_id}</span></div>
                            {form.note && <div className="flex justify-between"><span className="text-sm text-gray-500">Note</span><span className="text-sm text-gray-900">{form.note}</span></div>}
                        </div>
                    </div>
                    {selectedLoan && (
                        <div className="rounded-lg bg-purple-50 p-2 text-xs text-purple-700">
                            Remaining balance after payment: &#8369;{Math.max(0, selectedLoan.remaining_balance - Number(form.amount || 0)).toLocaleString()}
                        </div>
                    )}
                    <div className="flex gap-2">
                        <button onClick={() => setStep(3)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Confirm Payment'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
