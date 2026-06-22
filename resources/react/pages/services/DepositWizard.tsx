import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function DepositWizard({ onComplete }: { onComplete: () => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ amount: '', account_type: 'savings', transaction_mode: '', note: '' });
    const { addToast } = useToast();

    const submitMutation = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            fd.append('amount', form.amount);
            fd.append('account_type', form.account_type);
            fd.append('transaction_mode', form.transaction_mode);
            if (form.note) fd.append('note', form.note);
            return api.post('/api/services/deposit', fd);
        },
        onSuccess: () => { addToast('Deposit submitted successfully', 'success'); onComplete(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Deposit</h2>
            <div className="flex gap-1">{[1, 2, 3].map(s => <div key={s} className={`h-1 flex-1 rounded-full ${s <= step ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-700'}`} />)}</div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Amount & Account</h3>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                        <input type="number" step="0.01" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="Enter amount" />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Account Type</label>
                        <select value={form.account_type} onChange={e => setForm(f => ({ ...f, account_type: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700">
                            <option value="savings">Savings</option>
                            <option value="share-capital">Share Capital</option>
                        </select>
                    </div>
                    <button onClick={() => setStep(2)} disabled={!form.amount} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Transaction Mode</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {[{ key: 'counter', label: 'Counter', desc: 'In-person payment' }, { key: 'online', label: 'Online', desc: 'Online payment' }].map(m => (
                            <button key={m.key} onClick={() => setForm(f => ({ ...f, transaction_mode: m.key }))} className={`rounded-lg border p-4 text-left ${form.transaction_mode === m.key ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-slate-200 dark:border-slate-600'}`}>
                                <p className="font-medium text-slate-900 dark:text-white">{m.label}</p>
                                <p className="text-xs text-slate-500">{m.desc}</p>
                            </button>
                        ))}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Note (optional)</label>
                        <input type="text" value={form.note} onChange={e => setForm(f => ({ ...f, note: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => setStep(3)} disabled={!form.transaction_mode} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Confirm Deposit</h3>
                    <div className="rounded-lg bg-slate-50 p-4 dark:bg-slate-700">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Amount</span><span className="font-medium text-slate-900 dark:text-white">₱{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Account</span><span className="text-sm text-slate-900 dark:text-white capitalize">{form.account_type.replace('-', ' ')}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Mode</span><span className="text-sm text-slate-900 dark:text-white capitalize">{form.transaction_mode}</span></div>
                            {form.note && <div className="flex justify-between"><span className="text-sm text-slate-500">Note</span><span className="text-sm text-slate-900 dark:text-white">{form.note}</span></div>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Confirm Deposit'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
