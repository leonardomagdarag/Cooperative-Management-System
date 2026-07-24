import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function LoanPaymentWizard({ onComplete }: { onComplete: () => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ loan_id: '', amount: '', account_id: '', transaction_mode: '' });
    const { addToast } = useToast();

    const submitMutation = useMutation({
        mutationFn: () => api.post('/api/services/loan-payment', { ...form, amount: Number(form.amount) }),
        onSuccess: () => { addToast('Payment submitted', 'success'); onComplete(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Loan Payment</h2>
            <div className="flex gap-1">{[1, 2, 3].map(s => <div key={s} className={`h-1 flex-1 rounded-full ${s <= step ? 'bg-purple-500' : 'bg-slate-200 dark:bg-slate-700'}`} />)}</div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Select Loan & Amount</h3>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Loan ID</label>
                        <input type="number" value={form.loan_id} onChange={e => setForm(f => ({ ...f, loan_id: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="Enter loan ID" />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Payment Amount</label>
                        <input type="number" step="0.01" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="Enter amount" />
                    </div>
                    <button onClick={() => setStep(2)} disabled={!form.loan_id || !form.amount} className="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Transaction Mode</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {[{ key: 'counter', label: 'Counter' }, { key: 'online', label: 'Online' }].map(m => (
                            <button key={m.key} onClick={() => setForm(f => ({ ...f, transaction_mode: m.key }))} className={`rounded-lg border p-4 text-left ${form.transaction_mode === m.key ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-slate-200 dark:border-slate-600'}`}>
                                <p className="font-medium text-slate-900 dark:text-white">{m.label}</p>
                            </button>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => setStep(3)} disabled={!form.transaction_mode} className="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Confirm Payment</h3>
                    <div className="rounded-lg bg-slate-50 p-4 dark:bg-slate-700">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Loan ID</span><span className="text-sm text-slate-900 dark:text-white">#{form.loan_id}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Amount</span><span className="font-medium text-slate-900 dark:text-white">₱{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Mode</span><span className="text-sm capitalize text-slate-900 dark:text-white">{form.transaction_mode}</span></div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending} className="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Confirm Payment'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
