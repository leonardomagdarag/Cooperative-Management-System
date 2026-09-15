import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, depositSchema, type FieldErrors } from '../../lib/validation';
import ServiceModal from './ServiceModal';
import StepCircles from './StepCircles';
import {
    getPayMongoMethods,
    createPayMongoCheckout,
    verifyPayMongoPayment,
    type CheckoutResponse,
} from '../../api/paymongo';

interface AvailabilityFlags {
    global?: boolean;
    member?: boolean;
    officer?: boolean;
    counter?: boolean;
    online?: boolean;
}

const detailsSchema = depositSchema.pick({ amount: true, account_type: true, note: true });

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
    const [checkoutSession, setCheckoutSession] = useState<CheckoutResponse | null>(null);
    const [errors, setErrors] = useState<FieldErrors>({});
    const { addToast } = useToast();
    const requestIdRef = useRef<string | null>(null);

    // Fetch PayMongo enabled status and active methods
    const { data: paymongoData } = useQuery({
        queryKey: ['paymongo-methods'],
        queryFn: getPayMongoMethods,
    });

    const isPayMongoAvailable = Boolean(paymongoData?.enabled && paymongoData.methods.length > 0);

    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    useEffect(() => {
        onDirtyChange?.(!receipt && (form.amount !== '' || form.note !== '' || form.transaction_mode !== '' || !!proof));
    }, [form, proof, receipt, onDirtyChange]);

    const isModeAvailable = (mode: 'counter' | 'online' | 'paymongo'): boolean => {
        if (mode === 'paymongo') {
            if (!isPayMongoAvailable) return false;
            if (availability && (!availability.global || availability.online === false)) return false;
            return true;
        }
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
        if (!form.transaction_mode) {
            setErrors({ transaction_mode: 'Please select a payment method.' });
            return;
        }
        setErrors({});
        setStep(3);
    };

    const paymongoCheckoutMutation = useMutation({
        mutationFn: () => {
            requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
            return createPayMongoCheckout({
                service_type: 'deposit',
                amount: form.amount,
                account_type: form.account_type as 'savings' | 'share-capital',
                note: form.note,
                client_request_id: requestIdRef.current!,
                ...(selectedMember ? { user_id: selectedMember.id } : {}),
            });
        },
        onSuccess: (data) => {
            setCheckoutSession(data);
            setStep(4);
            // Open checkout page in new window/tab safely
            if (data.checkout_url) {
                window.open(data.checkout_url, '_blank', 'noopener,noreferrer');
            }
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to initialize PayMongo checkout.', 'error');
        },
    });

    const verifyMutation = useMutation({
        mutationFn: (txnNumber: string) => verifyPayMongoPayment(txnNumber),
        onSuccess: (res) => {
            if (res.is_paid || res.status === 'completed') {
                addToast('Payment verified successfully!', 'success');
                setReceipt(res.transaction);
                setStep(99);
            } else {
                addToast(res.message || 'Payment has not been completed yet.', 'warning');
            }
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to verify payment status.', 'error');
        },
    });

    const handleSubmitManual = () => {
        const errs = validate(depositSchema, form);
        if (form.transaction_mode === 'online' && !proof) errs.proof = 'Payment proof is required for online deposits';
        if (proof && proof.size > 10240 * 1024) errs.proof = 'Proof must be 10MB or smaller';
        setErrors(errs);
        if (Object.keys(errs).length > 0) {
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        requestIdRef.current = requestIdRef.current ?? crypto.randomUUID();
        submitManualMutation.mutate();
    };

    const submitManualMutation = useMutation({
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

    const totalSteps = form.transaction_mode === 'paymongo' ? 4 : 3;

    return (
        <ServiceModal
            gradient="from-green-500 to-emerald-600"
            title="Deposit Money"
            onClose={onRequestClose}
            stepIndicator={
                step < 99 && <StepCircles step={step} steps={totalSteps} colors={{ done: 'bg-white text-green-600', upcoming: 'bg-green-400 text-white', connector: 'bg-green-400' }} />
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
                        {/* Option 1: PayMongo Secure Gateway (if enabled) */}
                        {isPayMongoAvailable && (
                            <button
                                type="button"
                                onClick={() => { setField('transaction_mode', 'paymongo'); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                                disabled={!isModeAvailable('paymongo')}
                                className={`w-full p-4 border-2 rounded-lg text-left transition-all ${form.transaction_mode === 'paymongo' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 hover:border-emerald-300'} ${!isModeAvailable('paymongo') ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`w-12 h-12 rounded-full flex items-center justify-center ${form.transaction_mode === 'paymongo' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                        <i className="fas fa-shield-alt text-xl"></i>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-gray-900">PayMongo Online Payment</p>
                                            <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Automated</span>
                                        </div>
                                        <p className="text-sm text-gray-600 truncate">GCash, Maya, GrabPay, Cards, QR Ph & Direct Online Banking</p>
                                    </div>
                                    {form.transaction_mode === 'paymongo' && <i className="fas fa-check-circle text-emerald-600 text-xl"></i>}
                                </div>
                            </button>
                        )}

                        {/* Option 2: Over the Counter */}
                        <button
                            type="button"
                            onClick={() => { setField('transaction_mode', 'counter'); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                            disabled={!isModeAvailable('counter')}
                            className={`w-full p-4 border-2 rounded-lg text-left transition-all ${form.transaction_mode === 'counter' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300'} ${!isModeAvailable('counter') ? 'opacity-50 cursor-not-allowed' : ''}`}
                        >
                            <div className="flex items-center gap-3">
                                <div className={`w-12 h-12 rounded-full flex items-center justify-center ${form.transaction_mode === 'counter' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                    <i className="fas fa-cash-register text-xl"></i>
                                </div>
                                <div className="flex-1">
                                    <p className="font-semibold text-gray-900">Over the Counter</p>
                                    <p className="text-sm text-gray-600">Visit our cooperative office to make payment in cash</p>
                                </div>
                                {!isModeAvailable('counter') && <p className="text-xs text-amber-600"><i className="fas fa-tools mr-1"></i>Under maintenance</p>}
                                {isModeAvailable('counter') && form.transaction_mode === 'counter' && <i className="fas fa-check-circle text-green-500 text-xl"></i>}
                            </div>
                        </button>

                        {/* Option 3: Manual Bank Transfer / Proof Upload */}
                        <button
                            type="button"
                            onClick={() => { setField('transaction_mode', 'online'); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                            disabled={!isModeAvailable('online')}
                            className={`w-full p-4 border-2 rounded-lg text-left transition-all ${form.transaction_mode === 'online' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300'} ${!isModeAvailable('online') ? 'opacity-50 cursor-not-allowed' : ''}`}
                        >
                            <div className="flex items-center gap-3">
                                <div className={`w-12 h-12 rounded-full flex items-center justify-center ${form.transaction_mode === 'online' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                    <i className="fas fa-file-upload text-xl"></i>
                                </div>
                                <div className="flex-1">
                                    <p className="font-semibold text-gray-900">Manual Transfer & Proof Upload</p>
                                    <p className="text-sm text-gray-600">Upload deposit slip or transfer screenshot for officer verification</p>
                                </div>
                                {!isModeAvailable('online') && <p className="text-xs text-amber-600"><i className="fas fa-tools mr-1"></i>Under maintenance</p>}
                                {isModeAvailable('online') && form.transaction_mode === 'online' && <i className="fas fa-check-circle text-green-500 text-xl"></i>}
                            </div>
                        </button>
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
                            <span className="text-gray-600">Payment Channel:</span>
                            <span className="font-semibold text-gray-900">
                                {form.transaction_mode === 'paymongo' ? 'PayMongo Secure Online Checkout' :
                                 form.transaction_mode === 'counter' ? 'Over the Counter' : 'Manual Proof Upload'}
                            </span>
                        </div>
                        {form.note && (
                            <div className="flex justify-between">
                                <span className="text-gray-600">Note:</span>
                                <span className="font-semibold text-gray-900">{form.note}</span>
                            </div>
                        )}
                    </div>

                    {form.transaction_mode === 'paymongo' ? (
                        <div className="bg-emerald-50 border-l-4 border-emerald-500 p-4">
                            <p className="text-sm text-emerald-800">
                                <i className="fas fa-shield-alt mr-2"></i>
                                You will be redirected to PayMongo's secure hosted payment portal to complete your deposit using GCash, Maya, Card, QR Ph, or Online Banking.
                            </p>
                        </div>
                    ) : form.transaction_mode === 'counter' ? (
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
                        {form.transaction_mode === 'paymongo' ? (
                            <button
                                type="button"
                                onClick={() => paymongoCheckoutMutation.mutate()}
                                disabled={paymongoCheckoutMutation.isPending}
                                className="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                {paymongoCheckoutMutation.isPending ? 'Connecting...' : (<><i className="fas fa-lock mr-1"></i> Proceed to PayMongo</>)}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={handleSubmitManual}
                                disabled={submitManualMutation.isPending}
                                className="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50"
                            >
                                {submitManualMutation.isPending ? 'Submitting...' : (<><i className="fas fa-check mr-2"></i> Confirm</>)}
                            </button>
                        )}
                    </div>
                </div>
            )}

            {/* Step 4: PayMongo Portal & Verification */}
            {step === 4 && checkoutSession && (
                <div className="p-6 space-y-5 text-center">
                    <div className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto text-emerald-600 text-2xl">
                        <i className="fas fa-credit-card"></i>
                    </div>

                    <div>
                        <h4 className="text-xl font-bold text-gray-900">PayMongo Checkout Session</h4>
                        <p className="text-sm text-gray-600 mt-1">Complete your payment in PayMongo's secure payment portal</p>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-4 border border-gray-200 text-left space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Transaction Reference:</span>
                            <span className="font-mono font-bold text-gray-900">{checkoutSession.transaction_number}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Total Amount:</span>
                            <span className="font-bold text-emerald-600">₱{Number(form.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>
                    </div>

                    <div className="space-y-3 pt-2">
                        <a
                            href={checkoutSession.checkout_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg shadow-sm transition-colors"
                        >
                            <span>Open Checkout Page</span>
                            <i className="fas fa-external-link-alt text-xs"></i>
                        </a>

                        <button
                            type="button"
                            onClick={() => verifyMutation.mutate(checkoutSession.transaction_number)}
                            disabled={verifyMutation.isPending}
                            className="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium rounded-lg shadow-sm disabled:opacity-50"
                        >
                            <i className={`fas fa-sync-alt ${verifyMutation.isPending ? 'animate-spin' : ''}`}></i>
                            <span>{verifyMutation.isPending ? 'Verifying with PayMongo...' : "I've Paid / Check Status"}</span>
                        </button>
                    </div>

                    <p className="text-xs text-gray-500 pt-2">
                        <i className="fas fa-info-circle mr-1"></i>
                        After finishing the transaction on PayMongo, click <strong>"I've Paid / Check Status"</strong> above to verify your payment.
                    </p>
                </div>
            )}

            {step === 99 && (
                <div className="p-6 text-center">
                    <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i className="fas fa-check text-green-600 text-4xl"></i>
                    </div>
                    <h4 className="text-xl font-bold text-gray-900 mb-2">Deposit Successful!</h4>
                    <p className="text-gray-600 mb-6">
                        {receipt?.status === 'completed'
                            ? 'Your deposit has been verified and your account balance has been updated.'
                            : form.transaction_mode === 'online'
                                ? 'Your online deposit proof has been uploaded and is pending officer verification.'
                                : 'Please visit our office to complete your counter deposit.'}
                    </p>

                    {receipt?.transaction_number && (
                        <div className="bg-gray-50 rounded-lg p-4 mb-6 text-left space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-gray-600">Transaction Reference:</span>
                                <span className="font-mono font-bold text-gray-900">{receipt.transaction_number}</span>
                            </div>
                            {receipt.amount && (
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Amount:</span>
                                    <span className="font-bold text-green-600">₱{Number(receipt.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-gray-600">Status:</span>
                                <span className="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                    {String(receipt.status || 'COMPLETED').toUpperCase()}
                                </span>
                            </div>
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