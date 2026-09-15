import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { validate, withdrawalSchema, withdrawalBaseSchema, type FieldErrors } from '../../lib/validation';
import ServiceModal from './ServiceModal';
import StepCircles from './StepCircles';
import ReceiptQR from '../../components/ReceiptQR';

interface AvailabilityFlags {
    global?: boolean;
    member?: boolean;
    officer?: boolean;
    counter?: boolean;
    online?: boolean;
    bank_transfer?: boolean;
}

const peso = (n: number | string) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
const padId = (n: number | string) => String(n).padStart(8, '0');
const formatDateTime = (iso?: string) => new Date(iso ?? new Date().toISOString()).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

export default function WithdrawalWizard({ onComplete, onRequestClose, selectedMember, memberDetail, onDirtyChange, availability }: {
    onComplete: () => void;
    onRequestClose: () => void;
    selectedMember?: any;
    memberDetail?: any;
    onDirtyChange?: (dirty: boolean) => void;
    availability?: AvailabilityFlags;
}) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({
        amount: '', account_id: '', reason: '', transaction_mode: '',
        bank_name: '', account_number: '', account_holder: '',
    });
    const [receipt, setReceipt] = useState<any>(null);
    const [errors, setErrors] = useState<FieldErrors>({});
    const { addToast } = useToast();
    const requestIdRef = useRef<string | null>(null);

    const setField = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    useEffect(() => {
        onDirtyChange?.(!receipt && (form.amount !== '' || form.reason !== '' || form.transaction_mode !== '' || form.bank_name !== '' || form.account_number !== '' || form.account_holder !== ''));
    }, [form, receipt, onDirtyChange]);

    const { data: dashboard } = useQuery({
        queryKey: ['dashboard-withdrawal', selectedMember?.id],
        queryFn: () => api.get('/api/dashboard'),
        enabled: !selectedMember,
    });

    const { data: pendingWithdrawalsData } = useQuery({
        queryKey: ['withdrawal-pending-sum', selectedMember?.id],
        queryFn: () => api.get('/api/history', { params: { status: 'pending', per_page: 50 } }),
        enabled: !selectedMember,
    });

    const pendingWithdrawalSum = (pendingWithdrawalsData?.data?.data ?? [])
        .filter((t: any) => t.transaction_type === 'withdrawal')
        .reduce((sum: number, t: any) => sum + Number(t.amount), 0);

    const savingsAccount = memberDetail?.accounts?.find((a: any) => a.account_type === 'savings') ?? dashboard?.data?.savings_account;
    const balance = Number(savingsAccount?.balance ?? 0);
    const availableBalance = Math.max(0, balance - (selectedMember ? 0 : pendingWithdrawalSum));

    const isModeAvailable = (mode: 'counter' | 'online' | 'bank_transfer'): boolean => {
        if (!availability) return true;
        if (!availability.global) return false;
        return availability[mode] !== false;
    };

    const schema = withdrawalSchema(availableBalance);
    const baseSchema = withdrawalBaseSchema(availableBalance);

    const validateStep = (toStep: number) => {
        let stepErrors: FieldErrors = {};

        if (toStep === 2) {
            // Step 1 → 2: only validate amount; reason is optional but shown
            const step1Schema = baseSchema.pick({ amount: true });
            stepErrors = validate(step1Schema, form);
            if (!form.reason?.trim()) stepErrors.reason = 'Reason is required';
        } else if (toStep === 3) {
            // Step 2 → 3: only validate transaction_mode selection
            const step2Schema = baseSchema.pick({ transaction_mode: true });
            stepErrors = validate(step2Schema, form);
        } else {
            // Final step: full validation including bank transfer fields
            stepErrors = validate(schema, form);
        }

        setErrors(stepErrors);
        if (Object.keys(stepErrors).length > 0) return;
        setStep(toStep);
    };

    const handleSubmit = () => {
        const errs = validate(schema, form);
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
            const payload: any = {
                amount: form.amount,
                reason: form.reason,
                transaction_mode: form.transaction_mode,
                client_request_id: requestIdRef.current!,
            };
            if (form.transaction_mode === 'bank_transfer') {
                payload.note = [form.reason, `Bank: ${form.bank_name}`, `Acct: ${form.account_number}`, `Holder: ${form.account_holder}`].filter(Boolean).join(' | ');
                payload.bank_name = form.bank_name;
                payload.account_number = form.account_number;
                payload.account_holder = form.account_holder;
            }
            if (selectedMember) payload.user_id = selectedMember.id;
            return api.post('/api/services/withdrawal', payload);
        },
        onSuccess: (res) => {
            requestIdRef.current = null;
            addToast('Withdrawal submitted for approval', 'success');
            setReceipt(res.data.data || { message: res.data.message });
            setStep(form.transaction_mode === 'counter' ? 99 : 4);
        },
        onError: (err: any) => {
            requestIdRef.current = null;
            addToast(err.response?.data?.message || 'Failed', 'error');
        },
    });

    const modeLabels: Record<string, { title: string; sub: string; icon: string }> = {
        counter: { title: 'Over the Counter', sub: 'Visit our office to collect cash', icon: 'fa-cash-register' },
        online: { title: 'Mobile Wallet/Online', sub: 'GCash, PayMaya, or online banking', icon: 'fa-mobile-alt' },
        bank_transfer: { title: 'Bank Transfer', sub: 'Receive funds via online bank transfer', icon: 'fa-university' },
    };

    const isOnlineFlow = form.transaction_mode !== 'counter';
    const stepCount = isOnlineFlow ? 4 : 3;

    return (
        <ServiceModal
            gradient="from-orange-500 to-red-600"
            title="Withdraw Money"
            onClose={onRequestClose}
            stepIndicator={
                step < 99 && step < 5 && <StepCircles step={step} steps={stepCount} colors={{ done: 'bg-white text-orange-600', upcoming: 'bg-orange-400 text-white', connector: 'bg-orange-400' }} />
            }
        >
            {step === 1 && (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Enter Withdrawal Details</h4>

                    <div className="bg-green-50 border-l-4 border-green-400 p-4">
                        <p className="text-sm text-green-700">
                            <strong>Current Balance:</strong> {peso(balance)}
                        </p>
                        {availableBalance < balance && (
                            <p className="text-sm text-orange-600 mt-1">
                                <strong>Available Balance:</strong> {peso(availableBalance)}
                                <span className="text-xs"> (excluding pending withdrawals)</span>
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                        <div className="relative">
                            <span className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                            <input
                                type="number"
                                step="0.01"
                                min="100"
                                max={availableBalance}
                                value={form.amount}
                                onChange={e => setField('amount', e.target.value)}
                                placeholder="0.00"
                                className={`w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent ${errors.amount ? 'border-red-400' : 'border-gray-300'}`}
                            />
                        </div>
                        {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                        <textarea
                            rows={2}
                            value={form.reason}
                            onChange={e => setField('reason', e.target.value)}
                            placeholder="Purpose of withdrawal..."
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent ${errors.reason ? 'border-red-400' : 'border-gray-300'}`}
                        />
                        {errors.reason && <p className="mt-1 text-xs text-red-500">{errors.reason}</p>}
                    </div>

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={onComplete} className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" onClick={() => validateStep(2)} className="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                            Next <i className="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Choose Collection Method</h4>

                    <div className="space-y-3">
                        {(['counter', 'online', 'bank_transfer'] as const).map(mode => {
                            const ml = modeLabels[mode];
                            const selected = form.transaction_mode === mode;
                            const available = isModeAvailable(mode);
                            return (
                                <button
                                    key={mode}
                                    type="button"
                                    onClick={() => { setField('transaction_mode', mode); setErrors(prev => ({ ...prev, transaction_mode: '' })); }}
                                    disabled={!available}
                                    className={`w-full p-4 border-2 rounded-lg text-left transition-all ${selected ? 'border-orange-500 bg-orange-50' : 'border-gray-300 hover:border-orange-300'} ${!available ? 'opacity-50 cursor-not-allowed' : ''}`}
                                >
                                    <div className="flex items-center gap-3">
                                        <div className={`w-12 h-12 rounded-full flex items-center justify-center ${selected ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
                                            <i className={`fas ${ml.icon} text-xl`}></i>
                                        </div>
                                        <div className="flex-1">
                                            <p className="font-semibold text-gray-900">{ml.title}</p>
                                            <p className="text-sm text-gray-600">{ml.sub}</p>
                                        </div>
                                        {!available && <p className="text-xs text-amber-600"><i className="fas fa-tools mr-1"></i>Under maintenance</p>}
                                        {available && selected && <i className="fas fa-check-circle text-orange-500 text-xl"></i>}
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    {errors.transaction_mode && <span className="text-red-500 text-xs">{errors.transaction_mode}</span>}

                    {form.transaction_mode === 'bank_transfer' && (
                        <div className="space-y-2">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Bank Name</label>
                                <input type="text" value={form.bank_name} onChange={e => setField('bank_name', e.target.value)} placeholder="e.g. BDO, BPI" className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent ${errors.bank_name ? 'border-red-400' : 'border-gray-300'}`} />
                                {errors.bank_name && <p className="mt-1 text-xs text-red-500">{errors.bank_name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Account Number</label>
                                <input type="text" value={form.account_number} onChange={e => setField('account_number', e.target.value)} placeholder="Bank account number" className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent ${errors.account_number ? 'border-red-400' : 'border-gray-300'}`} />
                                {errors.account_number && <p className="mt-1 text-xs text-red-500">{errors.account_number}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Account Holder</label>
                                <input type="text" value={form.account_holder} onChange={e => setField('account_holder', e.target.value)} placeholder="Full name of account holder" className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent ${errors.account_holder ? 'border-red-400' : 'border-gray-300'}`} />
                                {errors.account_holder && <p className="mt-1 text-xs text-red-500">{errors.account_holder}</p>}
                            </div>
                        </div>
                    )}

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(1)} className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onClick={() => validateStep(3)} className="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                            Next <i className="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <div className="p-6 space-y-4">
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Confirm Your Withdrawal</h4>

                    <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Amount:</span>
                            <span className="font-semibold text-gray-900">{peso(form.amount)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Reason:</span>
                            <span className="font-semibold text-gray-900">{form.reason}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Collection Method:</span>
                            <span className="font-semibold text-gray-900">{modeLabels[form.transaction_mode]?.title}</span>
                        </div>
                    </div>

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                        <p className="text-sm text-blue-700">
                            <i className="fas fa-info-circle mr-2"></i>
                            Your withdrawal request will be reviewed and approved by authorized personnel.
                            {form.transaction_mode === 'counter' ? ' You will receive a notification once approved.' : ' Bank transfers may take 1-2 business days after approval.'}
                        </p>
                    </div>

                    <div className="flex gap-3 pt-4">
                        <button type="button" onClick={() => setStep(2)} className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" onClick={handleSubmit} disabled={submitMutation.isPending} className="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700 disabled:opacity-50">
                            {submitMutation.isPending ? 'Submitting...' : (<><i className="fas fa-check mr-2"></i> Confirm</>)}
                        </button>
                    </div>
                </div>
            )}

            {step === 4 && (
                <div className="p-6 space-y-4">
                    <div className="text-center py-8">
                        <div className="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i className="fas fa-university text-orange-600 text-3xl"></i>
                        </div>
                        <h4 className="text-lg font-semibold text-gray-900 mb-2">
                            {form.transaction_mode === 'bank_transfer' ? 'Processing Bank Transfer' : 'Processing Online Transfer'}
                        </h4>
                        <p className="text-gray-600 mb-6">We'll process your transfer request</p>

                        <div className="bg-gray-50 rounded-lg p-6 mb-6">
                            <p className="text-sm text-gray-600 mb-4">
                                {form.transaction_mode === 'bank_transfer'
                                    ? 'Funds will be transferred to your registered bank account'
                                    : 'Funds will be transferred to your registered mobile wallet'}
                            </p>
                            <p className="text-2xl font-bold text-gray-900">{peso(form.amount)}</p>
                        </div>
                    </div>

                    <button type="button" onClick={() => setStep(5)} className="w-full px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                        Complete <i className="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            )}

            {step === 5 && receipt && (
                <div className="p-6">
                    <div className="text-center mb-6">
                        <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i className="fas fa-receipt text-green-600 text-3xl"></i>
                        </div>
                        <h4 className="text-xl font-bold text-gray-900 mb-2">Transaction Successful!</h4>
                        <p className="text-gray-600">Your transaction has been completed</p>
                    </div>

                    <div className="bg-gray-50 rounded-lg p-6 mb-4 space-y-3 border-2 border-dashed border-gray-300">
                        <div className="text-center pb-3 border-b border-gray-300">
                            <h5 className="font-bold text-gray-900">Settlers Credit Cooperative</h5>
                            <p className="text-sm text-gray-600">Official Receipt</p>
                        </div>

                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Transaction #:</span>
                            <span className="font-mono font-semibold text-gray-900">{padId(receipt.transaction_number ?? receipt.id ?? '')}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Date:</span>
                            <span className="font-semibold text-gray-900">{formatDateTime(receipt.transaction_date)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Type:</span>
                            <span className="font-semibold text-gray-900">WITHDRAWAL</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Account:</span>
                            <span className="font-semibold text-gray-900">Savings</span>
                        </div>
                        <div className="flex justify-between text-sm pt-3 border-t border-gray-300">
                            <span className="text-gray-600">Amount:</span>
                            <span className="text-2xl font-bold text-green-600">{peso(Number(receipt.amount ?? form.amount))}</span>
                        </div>
                        {form.reason && (
                            <div className="text-sm pt-3 border-t border-gray-300">
                                <span className="text-gray-600">Note:</span>
                                <p className="text-gray-900 mt-1">{form.reason}</p>
                            </div>
                        )}
                        <div className="flex justify-between text-sm pt-3 border-t border-gray-300">
                            <span className="text-gray-600">Payment Method:</span>
                            <span className="font-semibold text-gray-900">
                                {form.transaction_mode === 'counter' ? 'Over the Counter' : form.transaction_mode === 'bank_transfer' ? 'Bank Transfer' : 'Online/Digital Wallet'}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Status:</span>
                            <span className="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded">PENDING</span>
                        </div>

                        <div className="pt-4 border-t border-gray-300">
                            <div className="bg-white rounded p-4 text-center">
                                <div className="h-24 flex items-center justify-center border-2 border-dashed border-gray-300 rounded mb-2">
                                    <ReceiptQR value={JSON.stringify({ type: 'withdrawal', ref: receipt.transaction_number ?? receipt.id ?? '', amount: form.amount, date: receipt.transaction_date ?? new Date().toISOString() })} />
                                </div>
                                <p className="text-xs text-gray-500">Scan for verification</p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                        <p className="text-sm text-blue-700">
                            <i className="fas fa-info-circle mr-2"></i>
                            Please save or print this receipt for your records.
                        </p>
                    </div>

                    <button type="button" onClick={onComplete} className="w-full px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        <i className="fas fa-check mr-2"></i> Done
                    </button>
                </div>
            )}

            {step === 99 && (
                <div className="p-6 text-center">
                    <div className="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i className="fas fa-clock text-orange-600 text-4xl"></i>
                    </div>
                    <h4 className="text-xl font-bold text-gray-900 mb-2">Withdrawal Request Submitted!</h4>
                    <p className="text-gray-600 mb-6">
                        Your withdrawal request has been submitted for approval. You will receive a notification once it has been reviewed by authorized personnel.
                    </p>

                    {receipt?.transaction_number && (
                        <div className="bg-gray-50 rounded-lg p-4 mb-6">
                            <p className="text-sm text-gray-600 mb-1">Transaction Reference</p>
                            <p className="text-2xl font-mono font-bold text-gray-900">{receipt.transaction_number}</p>
                        </div>
                    )}

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <p className="text-sm text-blue-700">
                            <i className="fas fa-info-circle mr-2"></i>
                            {form.transaction_mode === 'counter'
                                ? 'Once approved, you can visit our office with valid ID to collect your withdrawal.'
                                : 'Once approved, funds will be transferred to your registered bank account within 1-2 business days.'}
                        </p>
                    </div>

                    <button type="button" onClick={onComplete} className="w-full px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                        Close
                    </button>
                </div>
            )}
        </ServiceModal>
    );
}