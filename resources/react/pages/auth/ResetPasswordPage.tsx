import { useState } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { verifyResetToken, resetPassword } from '../../api/auth';
import { useToast } from '../../contexts/ToastContext';
import { passwordPolicy } from '../../lib/validation';

type Step = 2 | 3 | 'done';

export default function ResetPasswordPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { addToast } = useToast();

    const email = searchParams.get('email') || '';

    const [step, setStep] = useState<Step>(2);
    const [verifying, setVerifying] = useState(false);
    const [tokenError, setTokenError] = useState('');
    const [pin, setPin] = useState('');

    const handleVerifyPin = async () => {
        if (pin.length !== 6) {
            setTokenError('Please enter a 6-digit code');
            return;
        }
        setVerifying(true);
        setTokenError('');
        try {
            const res = await verifyResetToken(email, pin);
            if (res.data?.valid) {
                setStep(3);
            } else {
                setTokenError(res.data?.message || 'Invalid verification code');
            }
        } catch (err: any) {
            setTokenError(err.response?.data?.message || 'An error occurred. Please try again.');
        } finally {
            setVerifying(false);
        }
    };

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [successError, setSuccessError] = useState('');

    const passwordForm = useForm({
        resolver: zodResolver(z.object({
            password: passwordPolicy,
            password_confirmation: z.string(),
        }).refine(d => d.password === d.password_confirmation, {
            message: "Passwords don't match",
            path: ['password_confirmation'],
        })),
    });

    const { register, handleSubmit, formState: { errors: pwdErrors } } = passwordForm;

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const handleReset = async (data: { password: string; password_confirmation: string }) => {
        setIsSubmitting(true);
        setSuccessError('');
        try {
            await resetPassword({
                email,
                token: pin,
                password: data.password,
                password_confirmation: data.password_confirmation,
            });
            setStep('done');
            addToast('Password reset successfully!', 'success');
        } catch (err: any) {
            setSuccessError(err.response?.data?.message || 'Failed to reset password. Please try again.');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (step === 'done') {
        return (
            <div className="public-page min-h-screen">
                <section className="public-auth-panel flex items-center justify-center px-6 py-6">
                    <div className="public-card overflow-hidden max-w-2xl w-full p-6 md:p-8">
                        <div className="text-center mb-5">
                            <div className="inline-flex items-center justify-center w-14 h-14 bg-emerald-50 rounded-2xl mb-3">
                                <i className="fas fa-check text-2xl text-slate-700"></i>
                            </div>
                            <h1 className="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Password Reset!</h1>
                            <p className="text-sm text-slate-600">Your password has been reset successfully.</p>
                        </div>
                        <div className="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                            <div className="flex items-center gap-3">
                                <i className="fas fa-check-circle text-emerald-500 text-xl shrink-0"></i>
                                <p className="text-sm font-medium text-emerald-800">Your new password has been set.</p>
                            </div>
                        </div>
                        <Link to="/login" className="public-btn public-btn-primary w-full flex items-center justify-center gap-2">
                            <i className="fas fa-sign-in-alt"></i>Go to Login
                        </Link>
                    </div>
                </section>
            </div>
        );
    }

    return (
        <div className="public-page min-h-screen">
            <section className="public-auth-panel flex items-center justify-center px-6 py-6">
                <div className="public-card overflow-hidden max-w-2xl w-full p-6 md:p-8">
                    <div className="text-center mb-5">
                        <div className="inline-flex items-center justify-center w-14 h-14 bg-emerald-50 rounded-2xl mb-3">
                            <i className="fas fa-key text-2xl text-slate-700"></i>
                        </div>
                        <h1 className="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Reset Password</h1>
                        <p className="text-sm text-slate-600">
                            {step === 2 ? 'Enter the 6-digit code sent to your email' : 'Create your new password'}
                        </p>
                    </div>

                    <div className="mb-5">
                        <div className="flex items-center justify-center gap-3">
                            <div className="flex flex-col items-center">
                                <div className="w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center text-sm font-bold mb-2">
                                    <i className="fas fa-check text-xs"></i>
                                </div>
                                <span className="text-xs font-medium text-emerald-700">Email</span>
                            </div>
                            <div className="w-16 h-1 bg-emerald-700"></div>
                            <div className="flex flex-col items-center">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold mb-2 ${step === 2 ? 'bg-emerald-700 text-white' : 'bg-emerald-700 text-white'}`}>
                                    {step === 2 ? '2' : <i className="fas fa-check text-xs"></i>}
                                </div>
                                <span className="text-xs font-medium text-emerald-700">Verify PIN</span>
                            </div>
                            <div className={`w-16 h-1 ${step === 3 ? 'bg-emerald-700' : 'bg-gray-200'}`}></div>
                            <div className="flex flex-col items-center">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold mb-2 ${step === 3 ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-500'}`}>3</div>
                                <span className={`text-xs ${step === 3 ? 'font-medium text-emerald-700' : 'text-gray-500'}`}>New Password</span>
                            </div>
                        </div>
                    </div>

                    {step === 2 && (
                        <>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-slate-700 mb-2">Email Address</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i className="fas fa-envelope text-slate-400"></i>
                                    </div>
                                    <div className="w-full pl-12 pr-4 py-3.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-700 font-medium">
                                        {email}
                                    </div>
                                </div>
                                <p className="mt-2 text-xs text-slate-500">
                                    <i className="fas fa-info-circle mr-1"></i>Need to change? Go back and re-enter your email
                                </p>
                            </div>

                            <div>
                                <label htmlFor="token" className="block text-sm font-medium text-slate-700 mb-2">
                                    6-Digit Verification Code
                                </label>
                                <input
                                    type="number"
                                    id="token"
                                    maxLength={6}
                                    value={pin}
                                    onChange={e => { setPin(e.target.value.slice(0, 6)); setTokenError(''); }}
                                    className={`public-input text-center font-bold tracking-[0.5rem] text-2xl ${tokenError ? 'ring-2 ring-red-500' : ''}`}
                                    placeholder="000000"
                                    onKeyDown={e => { if (e.key === 'Enter') handleVerifyPin(); }}
                                />
                                {tokenError && <p className="mt-2 text-sm text-red-600">{tokenError}</p>}
                                <p className="mt-2 text-xs text-slate-500">
                                    <i className="fas fa-clock mr-1"></i>Code expires in 30 minutes
                                </p>
                            </div>

                            <div className="p-3 bg-blue-50 rounded-xl border border-blue-100 mt-4">
                                <div className="flex gap-3">
                                    <i className="fas fa-info-circle text-blue-500 shrink-0"></i>
                                    <p className="text-sm text-blue-800 leading-relaxed">
                                        Enter the 6-digit code sent to your email to proceed to password reset.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                disabled={verifying}
                                onClick={handleVerifyPin}
                                className="public-btn public-btn-primary w-full mt-4"
                            >
                                <span className="flex items-center justify-center gap-2">
                                    {verifying && <i className="fas fa-spinner fa-spin"></i>}
                                    <span>{verifying ? 'Verifying Code...' : 'Continue to Password Reset'}</span>
                                    {!verifying && <i className="fas fa-arrow-right"></i>}
                                </span>
                            </button>

                            <div className="text-center pt-2">
                                <p className="text-sm text-slate-600">
                                    Didn't receive the code?{' '}
                                    <Link to="/forgot-password" className="font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                                        Resend Code
                                    </Link>
                                </p>
                            </div>
                        </>
                    )}

                    {step === 3 && (
                        <form onSubmit={handleSubmit(handleReset)} className="space-y-4">
                            {successError && (
                                <div className="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                                    <div className="flex items-center gap-3">
                                        <i className="fas fa-exclamation-circle text-red-500 text-xl shrink-0"></i>
                                        <p className="text-sm font-medium text-red-800">{successError}</p>
                                    </div>
                                </div>
                            )}

                            <div>
                                <label htmlFor="password" className="block text-sm font-medium text-slate-700 mb-2">New Password</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i className="fas fa-lock text-slate-400"></i>
                                    </div>
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        id="password"
                                        {...register('password')}
                                        className="public-input pl-12 pr-12"
                                        placeholder="Enter new password"
                                    />
                                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                        <i className={`fas fa-eye${showPassword ? '-slash' : ''}`}></i>
                                    </button>
                                </div>
                                {pwdErrors.password && <p className="mt-2 text-sm text-red-600">{pwdErrors.password.message}</p>}
                                <p className="mt-2 text-xs text-slate-500">At least 8 characters with uppercase, lowercase, and a number</p>
                            </div>

                            <div>
                                <label htmlFor="password_confirmation" className="block text-sm font-medium text-slate-700 mb-2">Confirm New Password</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i className="fas fa-lock text-slate-400"></i>
                                    </div>
                                    <input
                                        type={showConfirm ? 'text' : 'password'}
                                        id="password_confirmation"
                                        {...register('password_confirmation')}
                                        className="public-input pl-12 pr-12"
                                        placeholder="Re-enter new password"
                                    />
                                    <button type="button" onClick={() => setShowConfirm(!showConfirm)} className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                        <i className={`fas fa-eye${showConfirm ? '-slash' : ''}`}></i>
                                    </button>
                                </div>
                                {pwdErrors.password_confirmation && <p className="mt-2 text-sm text-red-600">{pwdErrors.password_confirmation.message}</p>}
                            </div>

                            <button type="submit" disabled={isSubmitting} className="public-btn public-btn-primary w-full mt-4">
                                <span className="flex items-center justify-center gap-2">
                                    <i className={`fas ${isSubmitting ? 'fa-spinner fa-spin' : 'fa-check'}`}></i>
                                    {isSubmitting ? 'Resetting...' : 'Reset Password'}
                                </span>
                            </button>

                            <div className="text-center pt-2">
                                <button type="button" onClick={() => setStep(2)} className="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors group text-sm font-medium">
                                    <i className="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                                    Back to PIN Entry
                                </button>
                            </div>
                        </form>
                    )}

                    <div className="text-center pt-2 border-t border-slate-100 mt-4">
                        <Link to="/login" className="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors group text-sm font-medium mt-3">
                            <i className="fas fa-sign-in-alt text-sm"></i>
                            Back to Sign In
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}
