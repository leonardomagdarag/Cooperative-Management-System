import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, withdrawalSchema, type FieldErrors } from '../../lib/validation';
import ReceiptQR from '../../components/ReceiptQR';

export default function WithdrawalWizard({ onComplete, selectedMember, memberDetail, onDirtyChange }: { onComplete: () => void; selectedMember?: any; memberDetail?: any; onDirtyChange?: (dirty: boolean) => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({
        amount: '', account_id: '', reason: '', transaction_mode: '',
        bank_name: '', account_number: '', account_holder: '',
    });
    const [receipt, setReceipt] = useState<any>(null);
    const { addToast } = useToast();

    useEffect(() => {
        onDirtyChange?.(form.amount !== '' || form.reason !== '' || form.transaction_mode !== '' || form.bank_name !== '' || form.account_number !== '' || form.account_holder !== '');
    }, [form, onDirtyChange]);

    const { data: dashboard } = useQuery({
        queryKey: ['dashboard-withdrawal', selectedMember?.id],
        queryFn: () => api.get('/api/dashboard'),
        enabled: !selectedMember,
    });
    const savingsAccount = memberDetail?.accounts?.find((a: any) => a.account_type === 'savings') ?? dashboard?.data?.savings_account;
    const availableBalance = savingsAccount?.balance ?? 0;
    const exceedsBalance = Number(form.amount) > availableBalance && availableBalance > 0;
    const [errors, setErrors] = useState<FieldErrors>({});
    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    const validateAndNext = (stepTo: number) => {
        const errs = validate(withdrawalSchema(availableBalance), form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(stepTo);
    };

    const handleSubmit = () => {
        const errs = validate(withdrawalSchema(availableBalance), form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) {
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        submitMutation.mutate();
    };

    const submitMutation = useMutation({
        mutationFn: () => {
            const payload: any = {
                amount: form.amount,
                account_id: savingsAccount?.id,
                reason: form.reason,
                transaction_mode: form.transaction_mode,
            };
            if (form.transaction_mode === 'bank_transfer') {
                payload.note = `Bank: ${form.bank_name}, Acct: ${form.account_number}, Holder: ${form.account_holder}`;
                if (form.reason) payload.note = `${form.reason} | ${payload.note}`;
            }
            if (selectedMember) payload.user_id = selectedMember.id;
            return api.post('/api/services/withdrawal', payload);
        },
        onSuccess: (res) => {
            addToast('Withdrawal submitted for approval', 'success');
            setReceipt(res.data.data || { message: res.data.message });
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    if (receipt) {
        return (
            <div className="space-y-4">
                <div className="text-center">
                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                        <i className="fas fa-clock text-2xl text-amber-600"></i>
                    </div>
                    <h3 className="font-medium text-gray-900">Withdrawal Submitted</h3>
                    <p className="text-xs text-gray-500">Pending approval</p>
                </div>
                <div className="rounded-lg bg-gray-50 p-4">
                    <div className="space-y-2">
                        {receipt.transaction_number && (
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Transaction #</span><span className="text-sm font-medium text-gray-900">{receipt.transaction_number}</span></div>
                        )}
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Mode</span><span className="text-sm capitalize text-gray-900">{form.transaction_mode}</span></div>
                        {form.reason && <div className="flex justify-between"><span className="text-sm text-gray-500">Reason</span><span className="text-sm text-gray-900">{form.reason}</span></div>}
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Date</span><span className="text-sm text-gray-900">{new Date().toLocaleString()}</span></div>
                    </div>
                    <div className="mt-4 border-t border-gray-200 pt-4">
                        <ReceiptQR value={JSON.stringify({ type: 'withdrawal', ref: receipt.transaction_number || '', amount: form.amount, date: new Date().toISOString() })} />
                    </div>
                </div>
                <div className="flex gap-2">
                    <button onClick={() => window.print()} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i className="fas fa-print mr-1"></i> Print
                    </button>
                    <button onClick={onComplete} className="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Close</button>
                </div>
            </div>
        );
    }

    return (
        <div>
            <div className="mb-4 flex gap-1">{[1, 2, 3].map(s => <div key={s} className={`h-1.5 flex-1 rounded-full ${s <= step ? 'bg-red-500' : 'bg-gray-200'}`} />)}</div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Amount &amp; Reason</h3>
                    {availableBalance > 0 && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Available Balance</span><span className="text-sm font-bold text-gray-900">&#8369;{availableBalance.toLocaleString()}</span></div>
                        </div>
                    )}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" step="0.01" value={form.amount} onChange={e => setField('amount', e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm ${exceedsBalance ? 'border-red-300 bg-red-50' : errors.amount ? 'border-red-400' : 'border-gray-200'}`} placeholder="Enter amount" />
                        {exceedsBalance && <p className="mt-1 text-xs text-red-600">Amount exceeds available balance of &#8369;{availableBalance.toLocaleString()}</p>}
                        {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Reason</label>
                        <input type="text" value={form.reason} onChange={e => setForm(f => ({ ...f, reason: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Reason for withdrawal" />
                    </div>
                    <button onClick={() => validateAndNext(2)} disabled={exceedsBalance} className="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Transaction Mode</h3>
                    <div className="grid grid-cols-3 gap-3">
                        {[
                            { key: 'counter', label: 'Counter' },
                            { key: 'online', label: 'Online' },
                            { key: 'bank_transfer', label: 'Bank Transfer' },
                        ].map(m => (
                            <button key={m.key} onClick={() => setForm(f => ({ ...f, transaction_mode: m.key }))} className={`rounded-lg border p-4 text-left ${form.transaction_mode === m.key ? 'border-red-500 bg-red-50' : 'border-gray-200'}`}>
                                <p className="font-medium text-gray-900 text-sm">{m.label}</p>
                            </button>
                        ))}
                    </div>
                    {form.transaction_mode === 'bank_transfer' && (
                        <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Bank Name</label>
                                <input type="text" value={form.bank_name} onChange={e => setField('bank_name', e.target.value)} className={`w-full rounded-lg border px-3 py-1.5 text-sm ${errors.bank_name ? 'border-red-400' : 'border-gray-200'}`} placeholder="e.g. BDO, BPI" />
                                {errors.bank_name && <p className="mt-1 text-xs text-red-600">{errors.bank_name}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Account Number</label>
                                <input type="text" value={form.account_number} onChange={e => setField('account_number', e.target.value)} className={`w-full rounded-lg border px-3 py-1.5 text-sm ${errors.account_number ? 'border-red-400' : 'border-gray-200'}`} placeholder="Bank account number" />
                                {errors.account_number && <p className="mt-1 text-xs text-red-600">{errors.account_number}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-700">Account Holder</label>
                                <input type="text" value={form.account_holder} onChange={e => setField('account_holder', e.target.value)} className={`w-full rounded-lg border px-3 py-1.5 text-sm ${errors.account_holder ? 'border-red-400' : 'border-gray-200'}`} placeholder="Full name of account holder" />
                                {errors.account_holder && <p className="mt-1 text-xs text-red-600">{errors.account_holder}</p>}
                            </div>
                        </div>
                    )}
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => validateAndNext(3)} className="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Confirm Withdrawal</h3>
                    <div className="rounded-lg bg-gray-50 p-4">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Mode</span><span className="text-sm capitalize text-gray-900">{form.transaction_mode.replace('_', ' ')}</span></div>
                            {form.reason && <div className="flex justify-between"><span className="text-sm text-gray-500">Reason</span><span className="text-sm text-gray-900">{form.reason}</span></div>}
                            {form.transaction_mode === 'bank_transfer' && (
                                <>
                                    <div className="flex justify-between"><span className="text-sm text-gray-500">Bank</span><span className="text-sm text-gray-900">{form.bank_name}</span></div>
                                    <div className="flex justify-between"><span className="text-sm text-gray-500">Account #</span><span className="text-sm text-gray-900">{form.account_number}</span></div>
                                    <div className="flex justify-between"><span className="text-sm text-gray-500">Holder</span><span className="text-sm text-gray-900">{form.account_holder}</span></div>
                                </>
                            )}
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Available Balance</span><span className="text-sm font-bold text-gray-900">&#8369;{availableBalance.toLocaleString()}</span></div>
                        </div>
                    </div>
                    <p className="text-xs text-amber-600">Note: All withdrawals require approval from a treasurer.</p>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Confirm Withdrawal'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
