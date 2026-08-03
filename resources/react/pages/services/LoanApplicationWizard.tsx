import { useEffect, useState, useCallback, useRef } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, loanApplicationSchema, type FieldErrors } from '../../lib/validation';

interface CoMaker { id: number; name: string; user_number: string }

export default function LoanApplicationWizard({ onComplete, selectedMember, onDirtyChange }: { onComplete: () => void; selectedMember?: any; onDirtyChange?: (dirty: boolean) => void }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({
        loan_product_code: '', amount: '', term_months: '6', payment_frequency: 'monthly', purpose: '',
    });
    const [coMakers, setCoMakers] = useState<CoMaker[]>([]);
    const [coMakerSearch, setCoMakerSearch] = useState('');
    const [coMakerResults, setCoMakerResults] = useState<CoMaker[]>([]);
    const { addToast } = useToast();
    const [errors, setErrors] = useState<FieldErrors>({});

    useEffect(() => {
        onDirtyChange?.(form.loan_product_code !== '' || form.amount !== '' || form.purpose !== '' || coMakers.length > 0);
    }, [form, coMakers, onDirtyChange]);

    const { data: productsData } = useQuery({
        queryKey: ['loan-products-full'],
        queryFn: () => api.get('/api/loan-products'),
    });
    const products: any[] = productsData?.data?.data ?? [];

    const selectedProduct = products.find((p: any) => p.code === form.loan_product_code);

    const submitMutation = useMutation({
        mutationFn: () => api.post('/api/services/loan-application', {
            loan_product_code: form.loan_product_code,
            amount: Number(form.amount),
            term_months: Number(form.term_months),
            payment_frequency: form.payment_frequency,
            purpose: form.purpose,
            co_makers: coMakers.map(cm => cm.id),
            ...(selectedMember ? { user_id: selectedMember.id } : {}),
        }),
        onSuccess: () => { addToast('Loan application submitted', 'success'); onComplete(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const selectProduct = (code: string) => {
        const p = products.find((x: any) => x.code === code);
        if (p) {
            setForm(f => ({ ...f, loan_product_code: code, amount: '', term_months: String(p.max_term || 6) }));
            setCoMakers([]);
            setErrors({});
        }
    };

    const validateAndNext = (stepTo: number) => {
        const errs = validate(loanApplicationSchema, form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(stepTo);
    };

    const handleSubmit = () => {
        const errs = validate(loanApplicationSchema, form);
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        submitMutation.mutate();
    };

    const coMakersRef = useRef(coMakers);
    coMakersRef.current = coMakers;

    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const searchCoMakers = useCallback((q: string) => {
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(async () => {
            if (!q.trim()) { setCoMakerResults([]); return; }
            const res = await api.get(`/api/loans/search-members?q=${encodeURIComponent(q)}`);
            const results = (res.data?.data || []).filter((m: CoMaker) => !coMakersRef.current.some(cm => cm.id === m.id));
            setCoMakerResults(results);
        }, 300);
    }, []);

    useEffect(() => () => { if (searchTimer.current) clearTimeout(searchTimer.current); }, []);

    const handleCoMakerSearch = (val: string) => {
        setCoMakerSearch(val);
        searchCoMakers(val);
    };

    const addCoMaker = (m: CoMaker) => {
        setCoMakers(prev => [...prev, m]);
        setCoMakerSearch('');
        setCoMakerResults([]);
    };

    const removeCoMaker = (id: number) => setCoMakers(prev => prev.filter(cm => cm.id !== id));

    const eligibilityWarnings: string[] = [];
    if (selectedProduct) {
        if (selectedProduct.requires_no_existing_loan) eligibilityWarnings.push('Requires no existing active loan');
        if (selectedProduct.minimum_share_capital > 0) eligibilityWarnings.push(`Minimum share capital: \u20B1${Number(selectedProduct.minimum_share_capital).toLocaleString()}`);
        if (selectedProduct.minimum_membership_years > 0) eligibilityWarnings.push(`Minimum ${selectedProduct.minimum_membership_years} year(s) membership required`);
        if (selectedProduct.required_co_makers > 0) eligibilityWarnings.push(`Requires ${selectedProduct.required_co_makers} co-maker(s)`);
        if (selectedProduct.requires_interview) eligibilityWarnings.push('Requires an interview');
        if (selectedProduct.requires_special_eligibility) eligibilityWarnings.push('Has special eligibility requirements');
    }

    return (
        <div>
            <div className="mb-4 flex gap-1">
                {[1, 2, 3, 4].map(s => (
                    <div key={s} className={`h-1.5 flex-1 rounded-full ${s <= step ? 'bg-blue-500' : 'bg-gray-200'}`} />
                ))}
            </div>

            {step === 1 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Select Loan Product</h3>
                    <div className="space-y-2">
                        {products.map((p: any) => (
                            <button
                                key={p.code}
                                onClick={() => selectProduct(p.code)}
                                className={`w-full rounded-lg border p-4 text-left ${form.loan_product_code === p.code ? 'border-blue-500 bg-blue-50' : 'border-gray-200'}`}
                            >
                                <p className="font-medium text-gray-900">{p.name}</p>
                                <p className="text-xs text-gray-500">
                                    {p.interest_rate}% interest &middot; Max &#8369;{Number(p.max_amount || 0).toLocaleString()} &middot; {p.max_term}mo
                                </p>
                                {p.description && <p className="mt-1 text-xs text-gray-400">{p.description}</p>}
                            </button>
                        ))}
                    </div>
                    {eligibilityWarnings.length > 0 && (
                        <div className="rounded-lg border border-yellow-300 bg-yellow-50 p-3">
                            <p className="mb-1 text-xs font-medium text-yellow-800">Eligibility Requirements</p>
                            <ul className="list-inside list-disc space-y-0.5">
                                {eligibilityWarnings.map((w, i) => (
                                    <li key={i} className="text-xs text-yellow-700">{w}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <button onClick={() => validateAndNext(2)} disabled={!form.loan_product_code} className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Next</button>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Loan Details</h3>
                    {selectedProduct && (
                        <div className="rounded-lg bg-blue-50 p-2 text-xs text-blue-700">
                            Max amount: &#8369;{Number(selectedProduct.max_amount || 0).toLocaleString()} &middot; Max term: {selectedProduct.max_term} months
                        </div>
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                            <input type="number" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.amount ? 'border-red-400' : 'border-gray-200'}`} placeholder="0" />
                            {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Term (months)</label>
                            <input type="number" value={form.term_months} onChange={e => setForm(f => ({ ...f, term_months: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.term_months ? 'border-red-400' : 'border-gray-200'}`} />
                            {errors.term_months && <p className="mt-1 text-xs text-red-600">{errors.term_months}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Payment Frequency</label>
                            <select value={form.payment_frequency} onChange={e => setForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Co-makers</label>
                        <div className="flex gap-2">
                            <input type="text" value={coMakerSearch} onChange={e => handleCoMakerSearch(e.target.value)} className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Search members..." />
                        </div>
                        {coMakerResults.length > 0 && (
                            <div className="mt-1 rounded-lg border border-gray-200 bg-white shadow-sm">
                                {coMakerResults.map(m => (
                                    <button key={m.id} onClick={() => addCoMaker(m)} className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 border-b border-gray-100 last:border-none">
                                        <span className="font-medium">{m.name}</span>
                                        <span className="ml-2 text-xs text-gray-400">#{m.user_number}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                        {coMakers.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {coMakers.map(cm => (
                                    <span key={cm.id} className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700">
                                        {cm.name}
                                        <button onClick={() => removeCoMaker(cm.id)} className="ml-0.5 rounded-full hover:bg-blue-200 p-0.5">&times;</button>
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(1)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => validateAndNext(3)} className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Loan Purpose</h3>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Purpose of Loan</label>
                        <textarea value={form.purpose} onChange={e => setForm(f => ({ ...f, purpose: e.target.value }))} rows={4} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.purpose ? 'border-red-400' : 'border-gray-200'}`} placeholder="Describe the purpose of this loan..." />
                        {errors.purpose && <p className="mt-1 text-xs text-red-600">{errors.purpose}</p>}
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(2)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={() => validateAndNext(4)} className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Next</button>
                    </div>
                </div>
            )}

            {step === 4 && (
                <div className="space-y-4">
                    <h3 className="font-medium text-gray-700">Review Application</h3>
                    <div className="rounded-lg bg-gray-50 p-4">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Product</span><span className="text-sm text-gray-900">{selectedProduct?.name}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Amount</span><span className="font-medium text-gray-900">&#8369;{Number(form.amount).toLocaleString()}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Term</span><span className="text-sm text-gray-900">{form.term_months} months</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Frequency</span><span className="text-sm capitalize text-gray-900">{form.payment_frequency}</span></div>
                            <div className="flex justify-between"><span className="text-sm text-gray-500">Purpose</span><span className="text-sm text-gray-900">{form.purpose || 'N/A'}</span></div>
                            {coMakers.length > 0 && (
                                <div className="flex justify-between"><span className="text-sm text-gray-500">Co-makers</span><span className="text-sm text-gray-900">{coMakers.map(cm => cm.name).join(', ')}</span></div>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => setStep(3)} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm">Back</button>
                        <button onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : 'Submit Application'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
