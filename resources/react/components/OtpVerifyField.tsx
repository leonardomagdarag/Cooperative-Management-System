import { useState, useEffect } from 'react';
import { useMutation } from '@tanstack/react-query';

interface OtpVerifyFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    type?: 'email' | 'tel' | 'text';
    error?: string;
    onSendOtp: (value: string) => Promise<{ expires_at?: string; message?: string }>;
    onVerifyOtp: (value: string, otp: string) => Promise<{ success: boolean; message?: string }>;
    isVerified?: boolean;
    className?: string;
}

export default function OtpVerifyField({
    label,
    value,
    onChange,
    placeholder = '',
    type = 'text',
    error,
    onSendOtp,
    onVerifyOtp,
    isVerified = false,
    className = ''
}: OtpVerifyFieldProps) {
    const [otpSent, setOtpSent] = useState(false);
    const [otpCode, setOtpCode] = useState('');
    const [localError, setLocalError] = useState('');
    const [infoMessage, setInfoMessage] = useState('');
    const [countdown, setCountdown] = useState(0);
    const [verifiedState, setVerifiedState] = useState(isVerified);

    useEffect(() => {
        setVerifiedState(isVerified);
    }, [isVerified]);

    useEffect(() => {
        if (countdown > 0) {
            const timer = setTimeout(() => setCountdown(c => c - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [countdown]);

    const sendOtpMutation = useMutation({
        mutationFn: () => onSendOtp(value),
        onSuccess: (data) => {
            setOtpSent(true);
            setLocalError('');
            setCountdown(60); // 1 minute cooldown
            if (data?.message) {
                setInfoMessage(data.message);
            }
        },
        onError: (err: any) => {
            setLocalError(err.response?.data?.message || err.message || 'Failed to send OTP');
            setInfoMessage('');
        }
    });

    const verifyOtpMutation = useMutation({
        mutationFn: () => onVerifyOtp(value, otpCode),
        onSuccess: (data) => {
            if (data.success) {
                setVerifiedState(true);
                setLocalError('');
                setInfoMessage('');
            } else {
                setLocalError(data.message || 'Verification failed');
            }
        },
        onError: (err: any) => {
            setLocalError(err.response?.data?.message || err.message || 'Verification failed');
        }
    });

    const displayError = error || localError;

    return (
        <div className={className}>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <div className="flex flex-col gap-2">
                <div className="flex gap-2 relative">
                    <input
                        type={type}
                        value={value}
                        onChange={(e) => {
                            onChange(e.target.value);
                            setVerifiedState(false);
                            setOtpSent(false);
                            setLocalError('');
                            setInfoMessage('');
                        }}
                        disabled={verifiedState}
                        placeholder={placeholder}
                        className={`w-full rounded-lg border px-3 py-2 text-sm disabled:bg-gray-50 disabled:text-gray-500 ${displayError ? 'border-red-400' : 'border-gray-200'}`}
                    />
                    
                    {verifiedState ? (
                        <div className="absolute right-3 top-2.5 text-emerald-600">
                            <i className="fas fa-circle-check"></i>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => sendOtpMutation.mutate()}
                            disabled={!value || sendOtpMutation.isPending || countdown > 0}
                            className="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                        >
                            {sendOtpMutation.isPending ? 'Sending...' : countdown > 0 ? `Wait ${countdown}s` : (otpSent ? 'Resend' : 'Send Code')}
                        </button>
                    )}
                </div>

                {otpSent && !verifiedState && (
                    <div className="flex gap-2">
                        <input
                            type="text"
                            inputMode="numeric"
                            maxLength={6}
                            placeholder="6-digit OTP"
                            value={otpCode}
                            onChange={e => {
                                setOtpCode(e.target.value.replace(/\D/g, ''));
                                setLocalError('');
                            }}
                            className="w-full max-w-[150px] rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm tracking-widest text-center focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                        <button
                            type="button"
                            onClick={() => verifyOtpMutation.mutate()}
                            disabled={otpCode.length !== 6 || verifyOtpMutation.isPending}
                            className="shrink-0 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 transition-colors"
                        >
                            {verifyOtpMutation.isPending ? 'Verifying...' : 'Verify'}
                        </button>
                    </div>
                )}
            </div>
            {displayError && <p className="mt-1 text-xs text-red-600">{displayError}</p>}
            {!displayError && infoMessage && (
                <p className="mt-1 text-xs text-blue-600 flex items-center gap-1.5">
                    <i className="fas fa-info-circle shrink-0"></i>
                    <span>{infoMessage}</span>
                </p>
            )}
        </div>
    );
}
