import { useRef, useState } from 'react';
import { Link } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { forgotPassword } from '../../api/auth';
import TurnstileWidget from '../../components/TurnstileWidget';

const schema = z.object({
    email: z.string().email('Please enter a valid email'),
});

type Form = z.infer<typeof schema>;

export default function ForgotPasswordPage() {
    const turnstileRef = useRef<any>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [sent, setSent] = useState(false);
    const [error, setError] = useState('');

    const { register, handleSubmit, formState: { errors } } = useForm<Form>({
        resolver: zodResolver(schema),
    });

    const onSubmit = async (data: Form) => {
        setIsSubmitting(true);
        setError('');
        try {
            await forgotPassword(data.email, turnstileRef.current?.getToken?.());
            setSent(true);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to send reset code. Please try again.');
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
                            <i className="fas fa-envelope text-2xl text-slate-700"></i>
                        </div>
                        <h1 className="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Forgot Password?</h1>
                        <p className="text-sm text-slate-600">
                            Enter your email to receive a verification code
                        </p>
                    </div>

                    <div className="mb-5">
                        <div className="flex items-center justify-center gap-3">
                            <div className="flex flex-col items-center">
                                <div className="w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center text-sm font-bold mb-2">1</div>
                                <span className="text-xs font-medium text-emerald-700">Email</span>
                            </div>
                            <div className="w-16 h-1 bg-gray-200"></div>
                            <div className="flex flex-col items-center">
                                <div className="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold mb-2">2</div>
                                <span className="text-xs text-gray-500">Verify PIN</span>
                            </div>
                            <div className="w-16 h-1 bg-gray-200"></div>
                            <div className="flex flex-col items-center">
                                <div className="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold mb-2">3</div>
                                <span className="text-xs text-gray-500">New Password</span>
                            </div>
                        </div>
                    </div>

                    {sent ? (
                        <div className="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                            <div className="flex items-center gap-3">
                                <i className="fas fa-check-circle text-emerald-500 text-xl shrink-0"></i>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-emerald-800">
                                        A verification code has been sent to your email address.
                                    </p>
                                </div>
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
                                        <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        </div>
                                        <input
                                            type="email"
                                            id="email"
                                            {...register('email')}
                                            className="public-input pl-12"
                                            placeholder="Enter your registered email"
                                        />
                                    </div>
                                    {errors.email && <p className="mt-2 text-sm text-red-600">{errors.email.message}</p>}
                                </div>

                                <div className="p-4 bg-blue-50 rounded-xl border border-blue-100">
                                    <div className="flex gap-3">
                                        <i className="fas fa-info-circle text-blue-500 text-lg shrink-0"></i>
                                        <p className="text-sm text-blue-800 leading-relaxed">
                                            A 6-digit verification code will be sent to your email. The code expires in 30 minutes.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex justify-center mt-4">
                                    <TurnstileWidget ref={turnstileRef} />
                                </div>

                                <button type="submit" disabled={isSubmitting} className="public-btn public-btn-primary w-full mt-2">
                                    <span className="flex items-center justify-center gap-2">
                                        <i className={`fas ${isSubmitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'}`}></i>
                                        {isSubmitting ? 'Sending...' : 'Send Verification Code'}
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
