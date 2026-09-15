import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { sendEmailChangeOtp } from '../api/profile';
import { useToast } from '../contexts/ToastContext';

interface EmailChangeVerificationProps {
    originalEmail: string;
    email: string;
    otpCode: string;
    onOtpCodeChange: (code: string) => void;
    error?: string;
}

export default function EmailChangeVerification({
    originalEmail,
    email,
    otpCode,
    onOtpCodeChange,
    error,
}: EmailChangeVerificationProps) {
    const { addToast } = useToast();
    const [sent, setSent] = useState(false);
    const [sendError, setSendError] = useState('');

    const emailChanged =
        email && originalEmail && email.trim().toLowerCase() !== originalEmail.trim().toLowerCase();

    const sendMutation = useMutation({
        mutationFn: () => sendEmailChangeOtp(email.trim()),
        onSuccess: () => {
            setSent(true);
            setSendError('');
            addToast('Verification code sent to your current email address.', 'success');
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || 'Failed to send verification code.';
            setSendError(msg);
            addToast(msg, 'error');
        },
    });

    if (!emailChanged) {
        return null;
    }

    return (
        <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
            <div className="flex items-start gap-3">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                    <i className="fas fa-shield-halved text-sm"></i>
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold text-emerald-900">Email Change Verification</p>
                    <p className="mt-0.5 text-xs text-emerald-700">
                        You are changing your email from <span className="font-semibold">{originalEmail}</span> to{' '}
                        <span className="font-semibold">{email}</span>. A 6-digit code will be sent to your current
                        email to authorize this change.
                    </p>

                    <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            type="text"
                            inputMode="numeric"
                            maxLength={6}
                            placeholder="6-digit code"
                            value={otpCode}
                            onChange={e => {
                                onOtpCodeChange(e.target.value.replace(/\D/g, ''));
                                setSendError('');
                            }}
                            className={`w-full max-w-[180px] rounded-lg border bg-white px-3 py-2 text-sm tracking-[0.25em] ${
                                error ? 'border-red-400' : 'border-emerald-300'
                            }`}
                        />
                        <button
                            type="button"
                            onClick={() => sendMutation.mutate()}
                            disabled={sendMutation.isPending}
                            className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {sendMutation.isPending ? (
                                <>
                                    <i className="fas fa-spinner fa-spin text-xs"></i>
                                    <span>Sending...</span>
                                </>
                            ) : sent ? (
                                <>
                                    <i className="fas fa-rotate-right text-xs"></i>
                                    <span>Resend Code</span>
                                </>
                            ) : (
                                <>
                                    <i className="fas fa-paper-plane text-xs"></i>
                                    <span>Send Code</span>
                                </>
                            )}
                        </button>
                    </div>

                    {(sendError || error) && (
                        <p className="mt-2 text-xs font-medium text-red-600">{sendError || error}</p>
                    )}
                </div>
            </div>
        </div>
    );
}
