import { useRef, useState } from 'react';
import { Link } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { forgotPassword } from '../../api/auth';
import TurnstileWidget, { TurnstileWidgetRef } from '../../components/TurnstileWidget';

const schema = z.object({
    email: z.string().email('Please enter a valid email'),
});

type Form = z.infer<typeof schema>;

export default function ForgotPasswordPage() {
    const turnstileRef = useRef<TurnstileWidgetRef>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [sent, setSent] = useState(false);
    const [submittedEmail, setSubmittedEmail] = useState('');
    const [error, setError] = useState('');

    const { register, handleSubmit, formState: { errors } } = useForm<Form>({
        resolver: zodResolver(schema),
    });

    const onSubmit = async (data: Form) => {
        const token = turnstileRef.current?.getToken() ?? '';

        // The widget mints its token asynchronously, so an impatient submit would
        // otherwise send an empty value and bounce off the server's `required` rule.
        if (!token) {
            setError('Please complete the captcha verification before requesting a reset link.');
            return;
        }

        setIsSubmitting(true);
        setError('');
        try {
            await forgotPassword(data.email, token);
            setSubmittedEmail(data.email);
            setSent(true);
        } catch (err: any) {
            // Turnstile tokens are single-use, so a spent or rejected one must be
            // cleared or every subsequent attempt fails identically.
            turnstileRef.current?.reset();
            setError(
                err.response?.data?.errors?.['cf-turnstile-response']
                    || err.response?.data?.message
                    || 'Failed to request a reset link. Please try again.'
            );
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="public-page min-h-screen">
            <section className="public-auth-panel flex items-center justify-center px-6 py-6">
                <div className="public-card overflow-hidden max-w-2xl w-full p-6 md:p-8">
                    <div className="text-center mb-5">
                        <div className="inline-flex items-center justify-center w-14 h-14 bg-emerald-50 rounded-2xl mb-3">
                            <i className="fas fa-envelope text-2xl text-emerald-700"></i>
                        </div>
                        <h1 className="mb-2 text-2xl font-medium text-slate-800 md:text-3xl">Forgot Password?</h1>
                        <p className="text-sm text-slate-600">
                            Enter your email to receive a password reset link
                        </p>
                    </div>

                    <div className="mb-5">
                        <div className="flex items-center justify-center gap-3">
                            <div className="flex flex-col items-center">
                                <div className="w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center text-sm font-bold mb-2 shadow-xs">1</div>
                                <span className="text-xs font-semibold text-emerald-700">Email</span>
                            </div>
                            <div className={`w-16 h-1 rounded ${sent ? 'bg-emerald-500' : 'bg-gray-200'}`}></div>
                            <div className="flex flex-col items-center">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold mb-2 ${sent ? 'bg-emerald-700 text-white shadow-xs' : 'bg-gray-200 text-gray-500'}`}>2</div>
                                <span className={`text-xs font-medium ${sent ? 'text-emerald-700' : 'text-gray-500'}`}>Open Link</span>
                            </div>
                            <div className="w-16 h-1 bg-gray-200 rounded"></div>
                            <div className="flex flex-col items-center">
                                <div className="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold mb-2">3</div>
                                <span className="text-xs text-gray-500">New Password</span>
                            </div>
                        </div>
                    </div>

                    {sent ? (
                        <div className="space-y-4">
                            <div className="p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-xl">
                                <div className="flex items-start gap-3">
                                    <i className="fas fa-check-circle text-emerald-600 text-xl shrink-0 mt-0.5"></i>
                                    <div className="flex-1">
                                        <p className="text-sm font-semibold text-emerald-900">
                                            Check your email
                                        </p>
                                        <p className="text-xs text-emerald-700 mt-1">
                                            If an active account exists for <span className="font-semibold">{submittedEmail}</span>, we sent a reset link. Check your inbox and spam folder. The link expires in 30 minutes.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Link
                                to="/login"
                                className="public-btn public-btn-primary w-full flex items-center justify-center gap-2"
                            >
                                <span>Back to Login</span>
                                <i className="fas fa-arrow-right text-sm"></i>
                            </Link>

                            <div className="text-center pt-2">
                                <button
                                    type="button"
                                    onClick={() => setSent(false)}
                                    className="text-xs text-emerald-600 hover:text-emerald-700 font-medium cursor-pointer"
                                >
                                            Didn't receive a link? Try another email
                                </button>
                            </div>

                            <div className="text-center pt-1">
                                <Link to="/login" className="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors text-sm font-medium">
                                    <i className="fas fa-arrow-left text-sm"></i>
                                    Back to Sign In
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <>
                            {error && (
                                <div className="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                                    <div className="flex items-center gap-3">
                                        <i className="fas fa-exclamation-circle text-red-500 text-xl shrink-0"></i>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-red-800">{error}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                                <div>
                                    <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-2">Email Address</label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                            <i className="fas fa-envelope"></i>
                                        </div>
                                        <input
                                            type="email"
                                            id="email"
                                            {...register('email')}
                                            className="public-input public-input-with-icon"
                                            placeholder="Enter your registered email"
                                        />
                                    </div>
                                    {errors.email && <p className="mt-2 text-sm text-red-600">{errors.email.message}</p>}
                                </div>

                                <div className="p-4 bg-emerald-50/50 rounded-xl border border-emerald-100">
                                    <div className="flex gap-3">
                                        <i className="fas fa-info-circle text-emerald-600 text-lg shrink-0"></i>
                                        <p className="text-sm text-emerald-800 leading-relaxed">
                                            If your account exists, we’ll email you a reset link that expires in 30 minutes.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex justify-center mt-4">
                                    <TurnstileWidget ref={turnstileRef} />
                                </div>

                                <button type="submit" disabled={isSubmitting} className="public-btn public-btn-primary w-full mt-2">
                                    <span className="flex items-center justify-center gap-2">
                                        <i className={`fas ${isSubmitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'}`}></i>
                                    {isSubmitting ? 'Sending...' : 'Send Reset Link'}
                                    </span>
                                </button>

                                <div className="text-center pt-1">
                                    <Link to="/login" className="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors group text-sm font-medium">
                                        <i className="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                                        Back to Sign In
                                    </Link>
                                </div>
                            </form>
                        </>
                    )}
                </div>
            </section>
        </div>
    );
}
