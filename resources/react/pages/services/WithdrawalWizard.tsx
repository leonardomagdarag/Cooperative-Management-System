import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function WithdrawalWizard({ onComplete }: { onComplete: () => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ amount: '', account_id: '', reason: '', transaction_mode: '' });
    const { addToast } = useToast();

    const submitMutation = useMutation({
        mutationFn: () => api.post('/api/services/withdrawal', form),
        onSuccess: () => { addToast('Withdrawal submitted for approval', 'success'); onComplete(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Withdrawal</h2>
            <div className="flex gap-1">{[1, 2, 3].map(s => <div key={s} className={`h-1 flex-1 rounded-full ${s <= step ? 'bg-red-500' : 'bg-slate-200 dark:bg-slate-700'}`} />)}</div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Amount & Reason</h3>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                        <input type="number" step="0.01" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="Enter amount" />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Reason</label>
                        <input type="text" value={form.reason} onChange={e => setForm(f => ({ ...f, reason: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="Reason for withdrawal" />
                    </div>
                    <button onClick={() => setStep(2)} disabled={!form.amount} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Transaction Mode</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {[{ key: 'counter', label: 'Counter' }, { key: 'online', label: 'Online' }].map(m => (
                            <button key={m.key} onClick={() => setForm(f => ({ ...f, transaction_mode: m.key }))} className={`rounded-lg border p-4 text-left ${form.transaction_mode === m.key ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-600'}`}>
                                <p className="font-medium text-slate-900 dark:text-white">{m.label}</p>
                            </button>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => setStep(3)} disabled={!form.transaction_mode} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Confirm Withdrawal</h3>
                    <div className="rounded-lg bg-slate-50 p-4 dark:bg-slate-700">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Amount</span><span className="font-medium text-slate-900 dark:text-white">₱{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Mode</span><span className="text-sm capitalize text-slate-900 dark:text-white">{form.transaction_mode}</span></div>
                            {form.reason && <div className="flex justify-between"><span className="text-sm text-slate-500">Reason</span><span className="text-sm text-slate-900 dark:text-white">{form.reason}</span></div>}
                        </div>
                    </div>
                    <p className="text-xs text-amber-600">Note: All withdrawals require approval from a treasurer.</p>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Confirm Withdrawal'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
