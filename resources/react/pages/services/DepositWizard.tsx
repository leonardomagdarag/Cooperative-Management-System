import { useEffect, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, depositSchema, type FieldErrors } from '../../lib/validation';
import ReceiptQR from '../../components/ReceiptQR';

export default function DepositWizard({ onComplete, selectedMember, onDirtyChange }: { onComplete: () => void; selectedMember?: any; onDirtyChange?: (dirty: boolean) => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ amount: '', account_type: 'savings', transaction_mode: '', note: '' });
    const [proof, setProof] = useState<File | null>(null);
    const [receipt, setReceipt] = useState<any>(null);
    const { addToast } = useToast();

    useEffect(() => {
        onDirtyChange?.(form.amount !== '' || form.note !== '' || form.transaction_mode !== '' || !!proof);
    }, [form, proof, onDirtyChange]);

    const [errors, setErrors] = useState<FieldErrors>({});
    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    const validateAndNext = (stepTo: number) => {
        const errs = validate(depositSchema, form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(stepTo);
    };

    const handleSubmit = () => {
        const errs = validate(depositSchema, form);
        if (form.transaction_mode === 'online' && !proof) errs.proof = 'Payment proof is required for online deposits';
        if (proof && proof.size > 10240 * 1024) errs.proof = 'Proof must be 10MB or smaller';
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        submitMutation.mutate();
    };

    const submitMutation = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            fd.append('amount', form.amount);
            fd.append('account_type', form.account_type);
            fd.append('transaction_mode', form.transaction_mode);
            if (form.note) fd.append('note', form.note);
            if (proof) fd.append('proof', proof);
            if (selectedMember) fd.append('user_id', selectedMember.id);
            return api.post('/api/services/deposit', fd);
        },
        onSuccess: (res) => {
            addToast('Deposit submitted successfully', 'success');
            setReceipt(res.data.data || { message: res.data.message });
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    if (receipt) {
        return (
            <div className="space-y-4">
                <div className="text-center">
                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                        <i className="fas fa-check-circle text-2xl text-emerald-600"></i>
                    </div>
                    <h3 className="font-medium text-gray-900">Deposit Submitted</h3>
                    <p className="text-xs text-gray-500">Transaction receipt</p>
                </div>
                <div className="rounded-lg bg-gray-50 p-4">
                    <div className="space-y-2">
                        {receipt.transaction_number && (
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Transaction #</span><span className="text-sm font-medium text-gray-900">{receipt.transaction_number}</span></div>
                        )}
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Account</span><span className="text-sm capitalize text-gray-900">{form.account_type.replace('-', ' ')}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Mode</span><span className="text-sm capitalize text-gray-900">{form.transaction_mode}</span></div>
                        <div className="flex justify-between"><span className="text-sm text-gray-500">Date</span><span className="text-sm text-gray-900">{new Date().toLocaleString()}</span></div>
                    </div>
                    <div className="mt-4 border-t border-gray-200 pt-4">
                        <ReceiptQR value={JSON.stringify({ type: 'deposit', ref: receipt.transaction_number || '', amount: form.amount, date: new Date().toISOString() })} />
                    </div>
                </div>
                <div className="flex gap-2">
                    <button onClick={() => window.print()} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i className="fas fa-print mr-1"></i> Print
                    </button>
                    <button onClick={onComplete} className="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Close</button>
                </div>
            </div>
        );
    }

    return (
        <div>
            <div className="mb-4 flex gap-1">{[1, 2, 3].map(s => <div key={s} className={`h-1.5 flex-1 rounded-full ${s <= step ? 'bg-emerald-500' : 'bg-gray-200'}`} />)}</div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Amount &amp; Account</h3>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" step="0.01" value={form.amount} onChange={e => setField('amount', e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.amount ? 'border-red-400' : 'border-gray-200'}`} placeholder="Enter amount" />
                        {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Account Type</label>
                        <select value={form.account_type} onChange={e => setField('account_type', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <option value="savings">Savings</option>
                            <option value="share-capital">Share Capital</option>
                        </select>
                    </div>
                    <button onClick={() => validateAndNext(2)} className="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Transaction Mode</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {[{ key: 'counter', label: 'Counter', desc: 'In-person payment' }, { key: 'online', label: 'Online', desc: 'Online payment' }].map(m => (
                            <button key={m.key} onClick={() => setForm(f => ({ ...f, transaction_mode: m.key }))} className={`rounded-lg border p-4 text-left ${form.transaction_mode === m.key ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200'}`}>
                                <p className="font-medium text-gray-900">{m.label}</p>
                                <p className="text-xs text-gray-500">{m.desc}</p>
                            </button>
                        ))}
                    </div>
                    {form.transaction_mode === 'online' && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Payment Proof</label>
                            <input type="file" accept="image/*,.pdf" onChange={e => setProof(e.target.files?.[0] || null)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-emerald-700" />
                            <p className="mt-1 text-xs text-gray-400">Upload receipt or screenshot (JPG, PNG, PDF, max 10MB)</p>
                        </div>
                    )}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Note (optional)</label>
                        <input type="text" value={form.note} onChange={e => setForm(f => ({ ...f, note: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => validateAndNext(3)} className="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Confirm Deposit</h3>
                    <div className="rounded-lg bg-gray-50 p-4">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Account</span><span className="text-sm capitalize text-gray-900">{form.account_type.replace('-', ' ')}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Mode</span><span className="text-sm capitalize text-gray-900">{form.transaction_mode}</span></div>
                            {form.note && <div className="flex justify-between"><span className="text-sm text-gray-500">Note</span><span className="text-sm text-gray-900">{form.note}</span></div>}
                            {proof && <div className="flex justify-between"><span className="text-sm text-gray-500">Proof</span><span className="text-sm text-gray-900">{proof.name}</span></div>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Confirm Deposit'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
