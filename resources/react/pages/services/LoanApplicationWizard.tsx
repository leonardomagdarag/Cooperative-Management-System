import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function LoanApplicationWizard({ onComplete }: { onComplete: () => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ loan_product_code: '', amount: '', term_months: '6', payment_frequency: 'monthly', purpose: '' });
    const { addToast } = useToast();

    const { data: productsData } = useQuery({ queryKey: ['loan-products-options'], queryFn: () => api.get('/api/loans/loan-products') });
    const products = productsData?.data?.data || {};

    const submitMutation = useMutation({
        mutationFn: () => api.post('/api/services/loan-application', { ...form, amount: Number(form.amount), term_months: Number(form.term_months) }),
        onSuccess: () => { addToast('Loan application submitted', 'success'); onComplete(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const selectProduct = (code: string) => {
        const p = (products as any)[code];
        if (p) setForm(f => ({ ...f, loan_product_code: code, amount: '', term_months: String(p.max_term || 6) }));
    };

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Loan Application</h2>
            <div className="flex gap-1">{[1, 2, 3].map(s => <div key={s} className={`h-1 flex-1 rounded-full ${s <= step ? 'bg-blue-500' : 'bg-slate-200 dark:bg-slate-700'}`} />)}</div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Select Loan Product</h3>
                    <div className="space-y-2">
                        {Object.entries(products).map(([code, p]: [string, any]) => (
                            <button key={code} onClick={() => selectProduct(code)} className={`w-full rounded-lg border p-4 text-left ${form.loan_product_code === code ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-600'}`}>
                                <p className="font-medium text-slate-900 dark:text-white">{p.name}</p>
                                <p className="text-xs text-slate-500">{p.interest_rate}% interest · Max ₱{Number(p.max_amount || 0).toLocaleString()} · {p.max_term}mo</p>
                            </button>
                        ))}
                    </div>
                    <button onClick={() => setStep(2)} disabled={!form.loan_product_code} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Loan Details</h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                            <input type="number" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Term (months)</label>
                            <input type="number" value={form.term_months} onChange={e => setForm(f => ({ ...f, term_months: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Payment Frequency</label>
                            <select value={form.payment_frequency} onChange={e => setForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700">
                                <option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Purpose</label>
                        <input type="text" value={form.purpose} onChange={e => setForm(f => ({ ...f, purpose: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="What is the loan for?" />
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => setStep(3)} disabled={!form.amount} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-slate-700 dark:text-slate-300">Review Application</h3>
                    <div className="rounded-lg bg-slate-50 p-4 dark:bg-slate-700">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Product</span><span className="text-sm text-slate-900 dark:text-white">{(products as any)[form.loan_product_code]?.name}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Amount</span><span className="font-medium text-slate-900 dark:text-white">₱{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Term</span><span className="text-sm text-slate-900 dark:text-white">{form.term_months} months</span></div>
                            <div className="flex justify-between"><span className="text-sm text-slate-500">Frequency</span><span className="text-sm capitalize text-slate-900 dark:text-white">{form.payment_frequency}</span></div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Submit Application'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
