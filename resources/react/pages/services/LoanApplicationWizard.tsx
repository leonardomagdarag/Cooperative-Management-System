import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import { validate, loanApplicationSchema, loanApplicationStep2Schema, type FieldErrors } from '../../lib/validation';
import ServiceModal from './ServiceModal';

interface CoMaker { id: number; name: string; user_number: string }

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const iconClass = (p: any): string => {
    if (p?.icon) return p.icon.startsWith('fa-') ? p.icon : `fa-${p.icon}`;
    const fallbacks: Record<string, string> = { group: 'fa-users', business: 'fa-briefcase', special: 'fa-star' };
    return fallbacks[p?.base_type] ?? 'fa-handshake';
};

const minInterviewDate = () => {
    const d = new Date(Date.now() + 24 * 3600 * 1000);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const formatInterviewDate = (iso: string) => new Date(iso).toLocaleString(undefined, { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

export default function LoanApplicationWizard({ onComplete, onRequestClose, selectedMember, onDirtyChange }: {
    onComplete: () => void;
    onRequestClose: () => void;
    selectedMember?: any;
    onDirtyChange?: (dirty: boolean) => void;
}) {
    const { user } = useAuth();
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({
        loan_product_code: '', amount: '', term_months: '', payment_frequency: 'monthly', purpose: '', interview_schedule: '',
    });
    const [coMakers, setCoMakers] = useState<CoMaker[]>([]);
    const [coMakerSearch, setCoMakerSearch] = useState('');
    const [coMakerResults, setCoMakerResults] = useState<CoMaker[]>([]);
    const [errors, setErrors] = useState<FieldErrors>({});
    const { addToast } = useToast();

    const coMakersRef = useRef(coMakers);
    coMakersRef.current = coMakers;
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        onDirtyChange?.(form.loan_product_code !== '' || form.amount !== '' || form.purpose !== '' || form.interview_schedule !== '' || coMakers.length > 0);
    }, [form, coMakers, onDirtyChange]);

    useEffect(() => () => { if (searchTimer.current) clearTimeout(searchTimer.current); }, []);

    const { data: productsData } = useQuery({
        queryKey: ['loan-products-full'],
        queryFn: () => api.get('/api/loan-products'),
    });
    const products: any[] = productsData?.data?.data ?? [];
    const selectedProduct = products.find((p: any) => p.code === form.loan_product_code);

    const { data: profileData } = useQuery({
        queryKey: ['profile'],
        queryFn: () => api.get('/api/profile'),
        enabled: !selectedMember,
    });
    const employment = selectedMember ? selectedMember.employment : profileData?.data?.employment;

    const selectProduct = (code: string) => {
        const p = products.find((x: any) => x.code === code);
        if (p) {
            setForm(f => ({ ...f, loan_product_code: code, amount: '', term_months: String(p.max_term || 6) }));
            setCoMakers([]);
            setErrors({});
            setStep(2);
        }
    };

    const eligibilityWarnings = (p: any): string[] => {
        const out: string[] = [];
        if (!p) return out;
        if (p.requires_no_existing_loan) out.push('Requires no existing active loan');
        if (Number(p.minimum_share_capital) > 0) out.push(`Minimum share capital: ${peso(p.minimum_share_capital)}`);
        if (Number(p.minimum_membership_years) > 0) out.push(`Minimum ${p.minimum_membership_years} year(s) membership required`);
        if (Number(p.required_co_makers) > 0) out.push(`Requires ${p.required_co_makers} co-maker(s)`);
        if (p.requires_interview) out.push('Requires an interview');
        if (p.requires_special_eligibility) out.push('Has special eligibility requirements');
        return out;
    };

    const enterDetails = () => {
        // Only validate step-2 fields — purpose is entered on step 3
        const errs = validate(loanApplicationStep2Schema, form);
        if (selectedProduct && Number(form.amount ?? 0) > Number(selectedProduct.max_amount)) {
            errs.amount = `Amount exceeds the maximum limit of ${peso(selectedProduct.max_amount)}`;
        }
        if (selectedProduct && Number(form.term_months ?? 0) > Number(selectedProduct.max_term)) {
            errs.term_months = `Term cannot exceed ${selectedProduct.max_term} months`;
        }
        if (selectedProduct && Number(selectedProduct.required_co_makers) > 0 && coMakers.length !== Number(selectedProduct.required_co_makers)) {
            errs.co_makers = `Add exactly ${selectedProduct.required_co_makers} co-members`;
        }
        if (selectedProduct?.requires_interview && !form.interview_schedule) {
            errs.interview_schedule = 'An interview schedule is required for this loan product';
        }
        setErrors(errs);
        if (Object.keys(errs).length > 0) {
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setStep(3);
    };

    const enterPurpose = () => {
        // Only validate purpose on step 3
        const errs: FieldErrors = {};
        if (!form.purpose?.trim()) {
            errs.purpose = 'Purpose is required';
        } else if (form.purpose.trim().length < 10) {
            errs.purpose = 'Purpose must be at least 10 characters';
        }
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(4);
    };

    const handleSubmit = () => {
        const errs = validate(loanApplicationSchema, form);
        if (selectedProduct && Number(selectedProduct.required_co_makers) > 0 && coMakers.length !== Number(selectedProduct.required_co_makers)) {
            errs.co_makers = `Add exactly ${selectedProduct.required_co_makers} co-members`;
        }
        if (selectedProduct?.requires_interview && !form.interview_schedule) {
            errs.interview_schedule = 'An interview schedule is required for this loan product';
        }
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        submitMutation.mutate();
    };

    const submitMutation = useMutation({
        mutationFn: () => api.post('/api/services/loan-application', {
            loan_product_code: form.loan_product_code,
            amount: Number(form.amount),
            term_months: Number(form.term_months),
            payment_frequency: form.payment_frequency,
            purpose: form.purpose,
            interview_schedule: form.interview_schedule || undefined,
            co_makers: coMakers.map(cm => cm.id),
            ...(selectedMember ? { user_id: selectedMember.id } : {}),
        }),
        onSuccess: () => { addToast('Loan application submitted', 'success'); onComplete(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const handleCoMakerSearch = (val: string) => {
        setCoMakerSearch(val);
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(async () => {
            if (!val.trim()) { setCoMakerResults([]); return; }
            const res = await api.get(`/api/loans/search-members?q=${encodeURIComponent(val.trim())}`);
            const selfId = selectedMember?.id ?? user?.id;
            const results = (res.data?.data ?? []).filter((m: CoMaker) => m.id !== selfId && !coMakersRef.current.some(cm => cm.id === m.id));
            setCoMakerResults(results);
        }, 300);
    };

    const addCoMaker = (m: CoMaker) => {
        const required = Number(selectedProduct?.required_co_makers ?? 0);
        if (coMakers.length >= required) { addToast(`This loan only requires ${required} co-maker(s)`, 'error'); return; }
        setCoMakers(prev => [...prev, m]);
        setCoMakerSearch('');
        setCoMakerResults([]);
    };

    const removeCoMaker = (id: number) => setCoMakers(prev => prev.filter(cm => cm.id !== id));

    const requiredCoMakers = Number(selectedProduct?.required_co_makers ?? 0);
    const needsInterview = !!selectedProduct?.requires_interview;

    return (
        <ServiceModal
            gradient="from-purple-500 to-pink-600"
            title="Apply for Loan"
            subtitle={`Step ${step} of 4`}
            subtitleClassName="text-purple-100 text-sm mt-1"
            maxWidth="max-w-4xl"
            onClose={onRequestClose}
            stepIndicator={
                <div className="mt-4 flex gap-2">
                    {[1, 2, 3, 4].map(i => (
                        <div key={i} className={`flex-1 h-2 rounded-full ${step >= i ? 'bg-white' : 'bg-purple-300'}`} />
                    ))}
                </div>
            }
        >
            <div className="p-6">
                {step === 1 && (
                    <div className="space-y-4">
                        <h4 className="text-xl font-bold text-gray-900 mb-4">Choose Loan Type</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {products.map((p: any) => (
                                <button
                                    key={p.code}
                                    onClick={() => selectProduct(p.code)}
                                    className="text-left p-5 border-2 border-gray-200 rounded-xl hover:border-purple-500 hover:bg-purple-50 transition-all group"
                                >
                                    <div className="flex items-start gap-4">
                                        <div className="w-12 h-12 bg-purple-100 group-hover:bg-purple-500 rounded-lg flex items-center justify-center transition-colors shrink-0">
                                            <i className={`fas ${iconClass(p)} text-purple-600 group-hover:text-white text-xl`}></i>
                                        </div>
                                        <div className="flex-1">
                                            <h5 className="font-bold text-gray-900 mb-1">{p.name}</h5>
                                            <p className="text-sm text-gray-600 mb-2">{p.description}</p>
                                            <div className="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                                <span>Rate: {p.interest_rate}%</span>
                                                <span>Max: {peso(p.max_amount)}</span>
                                                <span>Up to {p.max_term} months</span>
                                            </div>
                                            {eligibilityWarnings(p).length > 0 && (
                                                <div className="mt-2 text-xs text-red-600 bg-red-50 p-2 rounded">
                                                    ⚠️ {eligibilityWarnings(p).join(' · ')}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div className="space-y-6">
                        <h4 className="text-xl font-bold text-gray-900">Loan Details</h4>

                        <div className="bg-purple-50 border-l-4 border-purple-400 p-4">
                            <p className="text-sm text-purple-700">
                                <strong>{selectedProduct?.name}</strong> - Interest Rate: {selectedProduct?.interest_rate}%
                            </p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Loan Amount *</label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                                <input
                                    type="number"
                                    step="100"
                                    min="100"
                                    max={selectedProduct?.max_amount}
                                    value={form.amount}
                                    onChange={e => setForm(f => ({ ...f, amount: e.target.value }))}
                                    placeholder="0.00"
                                    className={`w-full pl-8 pr-4 py-3 border rounded-lg focus:ring-2 focus:ring-purple-500 ${errors.amount ? 'border-red-400' : 'border-gray-300'}`}
                                />
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Maximum: {peso(selectedProduct?.max_amount ?? 0)}</p>
                            {errors.amount && <span className="text-red-500 text-xs">{errors.amount}</span>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Loan Term (Months) *</label>
                                <input
                                    type="number"
                                    min="1"
                                    max={selectedProduct?.max_term}
                                    value={form.term_months}
                                    onChange={e => setForm(f => ({ ...f, term_months: e.target.value }))}
                                    className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-purple-500 ${errors.term_months ? 'border-red-400' : 'border-gray-300'}`}
                                />
                                <p className="text-xs text-gray-500 mt-1">Max: {selectedProduct?.max_term} months</p>
                                {errors.term_months && <span className="text-red-500 text-xs">{errors.term_months}</span>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Payment Frequency *</label>
                                <select
                                    value={form.payment_frequency}
                                    onChange={e => setForm(f => ({ ...f, payment_frequency: e.target.value }))}
                                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="weekly">Weekly</option>
                                    <option value="bi-weekly">Bi-weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="annually">Annually</option>
                                </select>
                            </div>
                        </div>

                        {requiredCoMakers > 0 && (
                            <div className="border-t pt-6">
                                <h5 className="font-bold text-gray-900 mb-2">Group Members (Co-makers)</h5>
                                <p className="text-sm text-gray-600 mb-4">
                                    Select {requiredCoMakers} members to form your loan group. Each member must approve the loan.
                                </p>

                                <div className="mb-4">
                                    <input
                                        type="text"
                                        value={coMakerSearch}
                                        onChange={e => handleCoMakerSearch(e.target.value)}
                                        placeholder="Search members by name..."
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                    />
                                    {coMakerSearch.length >= 2 && coMakerResults.length > 0 && (
                                        <div className="mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto">
                                            {coMakerResults.map(m => (
                                                <button
                                                    key={m.id}
                                                    type="button"
                                                    onClick={() => addCoMaker(m)}
                                                    className="w-full px-4 py-2 hover:bg-gray-50 text-left"
                                                >
                                                    <div className="font-medium text-gray-900">{m.name}</div>
                                                    <div className="text-sm text-gray-500">ID: {m.user_number}</div>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    {coMakers.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            <p>No members added yet</p>
                                            <p className="text-sm">Add {requiredCoMakers} members to continue</p>
                                        </div>
                                    ) : coMakers.map(cm => (
                                        <div key={cm.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                                            <div>
                                                <div className="font-medium text-gray-900">{cm.name}</div>
                                                <div className="text-sm text-gray-500">ID: {cm.user_number}</div>
                                            </div>
                                            <button type="button" onClick={() => removeCoMaker(cm.id)} className="text-red-600 hover:text-red-800">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>

                                <div className={`mt-2 text-sm ${coMakers.length === requiredCoMakers ? 'text-green-600' : 'text-gray-600'}`}>
                                    {coMakers.length} / {requiredCoMakers} members added
                                </div>
                                {errors.co_makers && <span className="text-red-500 text-xs">{errors.co_makers}</span>}
                            </div>
                        )}

                        {needsInterview && (
                            <div className="border-t pt-6">
                                <h5 className="font-bold text-gray-900 mb-2">Business Verification</h5>
                                <p className="text-sm text-gray-600 mb-4">Schedule an interview with a loan officer for business verification.</p>

                                {employment ? (
                                    <div className="bg-blue-50 p-4 rounded-lg mb-4">
                                        <h6 className="font-semibold text-gray-900 mb-2">Your Business/Employment Info:</h6>
                                        <div className="text-sm space-y-1">
                                            <p><strong>Type:</strong> {capitalize(employment.employment_type ?? 'N/A')}</p>
                                            <p><strong>Company:</strong> {employment.company ?? 'N/A'}</p>
                                            <p><strong>Position:</strong> {employment.position ?? 'N/A'}</p>
                                            <p><strong>Monthly Income:</strong> {peso(employment.monthly_income ?? 0)}</p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                        <p className="text-sm text-yellow-700">
                                            ⚠️ No employment information found. Please update your profile with business/employment details before applying.
                                        </p>
                                    </div>
                                )}

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Interview Schedule *</label>
                                    <input
                                        type="datetime-local"
                                        value={form.interview_schedule}
                                        min={minInterviewDate()}
                                        onChange={e => setForm(f => ({ ...f, interview_schedule: e.target.value }))}
                                        className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-purple-500 ${errors.interview_schedule ? 'border-red-400' : 'border-gray-300'}`}
                                    />
                                    <p className="text-xs text-gray-500 mt-1">Select a date and time for your interview (must be at least 24 hours from now)</p>
                                    {errors.interview_schedule && <span className="text-red-500 text-xs">{errors.interview_schedule}</span>}
                                </div>
                            </div>
                        )}

                        <div className="flex gap-3 pt-4">
                            <button type="button" onClick={() => setStep(1)} className="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" onClick={enterDetails} className="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Continue
                            </button>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-6">
                        <h4 className="text-xl font-bold text-gray-900">Loan Purpose</h4>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Purpose of Loan *</label>
                            <textarea
                                rows={4}
                                value={form.purpose}
                                onChange={e => setForm(f => ({ ...f, purpose: e.target.value }))}
                                placeholder="Describe how you will use the loan..."
                                className={`w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-purple-500 ${errors.purpose ? 'border-red-400' : 'border-gray-300'}`}
                            />
                            <p className="text-xs text-gray-500 mt-1">Minimum 10 characters</p>
                            {errors.purpose && <span className="text-red-500 text-xs">{errors.purpose}</span>}
                        </div>

                        <div className="flex gap-3 pt-4">
                            <button type="button" onClick={() => setStep(2)} className="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" onClick={enterPurpose} className="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Review Application
                            </button>
                        </div>
                    </div>
                )}

                {step === 4 && (
                    <div className="space-y-6">
                        <h4 className="text-xl font-bold text-gray-900">Review Your Application</h4>

                        <div className="bg-gray-50 rounded-xl p-6 space-y-4">
                            <div>
                                <p className="text-sm text-gray-600">Loan Type</p>
                                <p className="font-semibold text-gray-900">{selectedProduct?.name}</p>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-gray-600">Amount</p>
                                    <p className="font-semibold text-gray-900">{peso(form.amount)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600">Term</p>
                                    <p className="font-semibold text-gray-900">{form.term_months} months</p>
                                </div>
                            </div>
                            <div>
                                <p className="text-sm text-gray-600">Payment Frequency</p>
                                <p className="font-semibold text-gray-900">{capitalize(form.payment_frequency)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-600">Interest Rate</p>
                                <p className="font-semibold text-gray-900">{selectedProduct?.interest_rate}%</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-600">Purpose</p>
                                <p className="font-semibold text-gray-900">{form.purpose}</p>
                            </div>

                            {requiredCoMakers > 0 && (
                                <div>
                                    <p className="text-sm text-gray-600 mb-2">Group Members ({coMakers.length})</p>
                                    {coMakers.map(cm => (
                                        <p key={cm.id} className="text-sm font-medium text-gray-900">
                                            • {cm.name} ({cm.user_number})
                                        </p>
                                    ))}
                                </div>
                            )}

                            {needsInterview && form.interview_schedule && (
                                <div>
                                    <p className="text-sm text-gray-600">Interview Schedule</p>
                                    <p className="font-semibold text-gray-900">{formatInterviewDate(form.interview_schedule)}</p>
                                </div>
                            )}
                        </div>

                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p className="text-sm text-blue-700">
                                <strong>Important:</strong>{' '}
                                {requiredCoMakers > 0
                                    ? 'After submission, all group members must approve this loan. Once approved by all members, the application will be reviewed by loan officers.'
                                    : needsInterview
                                        ? 'After submission, you must attend the scheduled interview. Your application will be reviewed after the interview is completed.'
                                        : 'After submission, your application will be reviewed by loan officers based on your member profile and account standing.'}
                            </p>
                        </div>

                        <div className="flex gap-3 pt-4">
                            <button type="button" onClick={() => setStep(3)} className="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 disabled:opacity-50">
                                {submitMutation.isPending ? 'Submitting...' : 'Submit Application'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </ServiceModal>
    );
}