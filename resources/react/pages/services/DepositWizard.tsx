import { useEffect, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, depositSchema, type FieldErrors } from '../../lib/validation';
import ServiceModal from './ServiceModal';
import StepCircles from './StepCircles';

interface AvailabilityFlags {
    global?: boolean;
    member?: boolean;
    officer?: boolean;
    counter?: boolean;
    online?: boolean;
}

const detailsSchema = depositSchema.pick({ amount: true, account_type: true, note: true });
const methodSchema = depositSchema.pick({ transaction_mode: true });

export default function DepositWizard({ onComplete, onRequestClose, selectedMember, onDirtyChange, availability }: {
    onComplete: () => void;
    onRequestClose: () => void;
    selectedMember?: any;
    onDirtyChange?: (dirty: boolean) => void;
    availability?: AvailabilityFlags;
}) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ amount: '', account_type: 'savings', transaction_mode: '', note: '' });
    const [proof, setProof] = useState<File | null>(null);
    const [receipt, setReceipt] = useState<any>(null);
    const [errors, setErrors] = useState<FieldErrors>({});
    const { addToast } = useToast();
    const requestIdRef = useRef<string | null>(null);

    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    useEffect(() => {
        onDirtyChange?.(!receipt && (form.amount !== '' || form.note !== '' || form.transaction_mode !== '' || !!proof));
    }, [form, proof, receipt, onDirtyChange]);

    const isModeAvailable = (mode: 'counter' | 'online'): boolean => {
        if (!availability) return true;
        if (!availability.global) return false;
        return availability[mode] !== false;
    };

    const goNextFromDetails = () => {
        const errs = validate(detailsSchema, form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(2);
    };

    const goNextFromMethod = () => {
        const errs = validate(methodSchema, form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(3);
    };

    const handleSubmit = () => {
        const errs = validate(depositSchema, form);
        if (form.transaction_mode === 'online' && !proof) errs.proof = 'Payment proof is required for online deposits';
        if (proof && proof.size > 10240 * 1024) errs.proof = 'Proof must be 10MB or smaller';
        setErrors(errs);
        if (Object.keys(errs).length > 0) {
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
        submitMutation.mutate();
    };

    const submitMutation = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            fd.append('amount', form.amount);
            fd.append('account_type', form.account_type);
            fd.append('transaction_mode', form.transaction_mode);
            fd.append('client_request_id', requestIdRef.current!);
            if (form.note) fd.append('note', form.note);
            if (proof) fd.append('proof', proof);
            if (selectedMember) fd.append('user_id', selectedMember.id);
            return api.post('/api/services/deposit', fd);
        },
        onSuccess: (res) => {
            requestIdRef.current = null;
            addToast('Deposit submitted successfully', 'success');
            setReceipt(res.data.data || { message: res.data.message });
            setStep(99);
        },
        onError: (err: any) => {
            requestIdRef.current = null;
            addToast(err.response?.data?.message || 'Failed', 'error');
        },
    });

    return (
        <ServiceModal
            gradient="from-green-500 to-emerald-600"
            title="Deposit Money"
            onClose={onRequestClose}
            stepIndicator={
                step < 99 && <StepCircles step={step} steps={3} colors={{ done: 'bg-white text-green-600', upcoming: 'bg-green-400 text-white', connector: 'bg-green-400' }} />
            }
        >
            {step === 1 && (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Enter Deposit Details</h4>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <select
                            value={form.account_type}
                            onChange={e => setField('account_type', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        >
                            <option value="savings">Savings Account</option>
                            <option value="share-capital">Share Capital</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                        <div className="relative">
                            <span className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                            <input
                                type="number"
                                step="0.01"
                                min="100"
                                value={form.amount}
                                onChange={e => setField('amount', e.target.value)}
                                placeholder="0.00"
                                className={`w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent ${errors.amount ? 'border-red-400' : 'border-gray-300'}`}
                            />
                        </div>
                        {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Note (Optional)</label>
                        <textarea
                            rows={2}
                            value={form.note}
                            onChange={e => setField('note', e.target.value)}
                            placeholder="Add any notes..."
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        />
                    </div>

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={onComplete} className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" onClick={goNextFromDetails} className="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                            Next <i className="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Choose Payment Method</h4>

                    <div className="space-y-3">
                        {[
                            { key: 'counter' as const, icon: 'fa-cash-register', title: 'Over the Counter', sub: 'Visit our office to complete payment', available: isModeAvailable('counter') },
                            { key: 'online' as const, icon: 'fa-mobile-alt', title: 'Online/Digital Wallet', sub: 'GCash, Bank Transfer, or Online Banking', available: isModeAvailable('online') },
                        ].map(m => (
                            <button
                                key={m.key}
                                type="button"
                                onClick={() => { setField('transaction_mode', m.key); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                                disabled={!m.available}
                                className={`w-full p-4 border-2 rounded-lg text-left transition-all ${form.transaction_mode === m.key ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300'} ${!m.available ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`w-12 h-12 rounded-full flex items-center justify-center ${form.transaction_mode === m.key ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                        <i className={`fas ${m.icon} text-xl`}></i>
                                    </div>
                                    <div className="flex-1">
                                        <p className="font-semibold text-gray-900">{m.title}</p>
                                        <p className="text-sm text-gray-600">{m.sub}</p>
                                    </div>
                                    {!m.available && <p className="text-xs text-amber-600"><i className="fas fa-tools mr-1"></i>Under maintenance</p>}
                                    {m.available && form.transaction_mode === m.key && <i className="fas fa-check-circle text-green-500 text-xl"></i>}
                                </div>
                            </button>
                        ))}
                    </div>

                    {errors.transaction_mode && <span className="text-red-500 text-xs">{errors.transaction_mode}</span>}

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(1)} className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onClick={goNextFromMethod} className="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                            Next <i className="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Confirm Your Deposit</h4>

                    <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Account Type:</span>
                            <span className="font-semibold text-gray-900">{form.account_type === 'savings' ? 'Savings Account' : 'Share Capital'}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Amount:</span>
                            <span className="font-semibold text-gray-900">₱{Number(form.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Payment Method:</span>
                            <span className="font-semibold text-gray-900">{form.transaction_mode === 'counter' ? 'Over the Counter' : 'Online/Digital Wallet'}</span>
                        </div>
                        {form.note && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Note:</span>
                                <span className="font-semibold text-gray-900">{form.note}</span>
                            </div>
                        )}
                    </div>

                    {form.transaction_mode === 'counter' ? (
                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p className="text-sm text-blue-700">
                                <i className="fas fa-info-circle mr-2"></i>
                                Please visit our office with your payment to complete this transaction.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <label className="block text-sm font-medium text-gray-700">Proof of Payment</label>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                onChange={e => setProof(e.target.files?.[0] || null)}
                                className={`block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/30 ${errors.proof ? 'border-red-400' : ''}`}
                            />
                            <p className="text-xs text-gray-500">Upload a screenshot, receipt, or PDF proof before submitting.</p>
                            {proof && <p className="text-xs font-medium text-gray-700">Selected: {proof.name}</p>}
                            {errors.proof && <span className="text-red-500 text-xs">{errors.proof}</span>}
                        </div>
                    )}

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(2)} className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : (<><i className="fas fa-check mr-2"></i> Confirm</>)}
                        </button>
                    </div>
                </div>
            )}

            {step === 99 && (
                <div className="p-6 text-center">
                    <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i className="fas fa-check text-green-600 text-4xl"></i>
                    </div>
                    <h4 className="text-xl font-bold text-gray-900 mb-2">Request Submitted!</h4>
                    <p className="text-gray-600 mb-6">
                        {form.transaction_mode === 'online'
                            ? 'Your online deposit proof has been uploaded. The transaction will remain pending until an officer verifies the payment.'
                            : 'Please visit our office to complete your deposit. The request will remain pending until it is approved.'}
                    </p>

                    {receipt?.transaction_number && (
                        <div className="bg-gray-50 rounded-lg p-4 mb-6">
                            <p className="text-sm text-gray-600 mb-1">Transaction Reference</p>
                            <p className="text-2xl font-mono font-bold text-gray-900">{receipt.transaction_number}</p>
                        </div>
                    )}

                    <button type="button" onClick={onComplete} className="w-full px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        Close
                    </button>
                </div>
            )}
        </ServiceModal>
    );
}