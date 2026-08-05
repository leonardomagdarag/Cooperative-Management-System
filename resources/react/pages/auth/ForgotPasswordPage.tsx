import { useState } from 'react';
import { Link } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { forgotPassword } from '../../api/auth';
import { useToast } from '../../contexts/ToastContext';

const schema = z.object({
    email: z.string().email('Please enter a valid email'),
});

type Form = z.infer<typeof schema>;

export default function ForgotPasswordPage() {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [sent, setSent] = useState(false);
    const { addToast } = useToast();

    const { register, handleSubmit, formState: { errors } } = useForm<Form>({
        resolver: zodResolver(schema),
    });

    const onSubmit = async (data: Form) => {
        setIsSubmitting(true);
        try {
            await forgotPassword(data.email);
            setSent(true);
        } catch (err: any) {
            addToast(err.response?.data?.message || 'Failed to send reset link.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (sent) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-lg">
                    <div className="mb-4 flex justify-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
                            <i className="fas fa-envelope text-2xl text-emerald-600"></i>
                        </div>
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900">Check your email</h1>
                    <p className="mt-2 text-sm text-slate-600">We've sent a password reset link to your email address.</p>
                    <Link to="/login" className="mt-6 inline-block text-sm font-medium text-emerald-600 hover:text-emerald-700">
                        Back to login
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
                <div className="mb-6 text-center">
                    <div className="mb-4 flex justify-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
                            <i className="fas fa-key text-2xl text-emerald-600"></i>
                        </div>
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900">Forgot Password?</h1>
                    <p className="mt-2 text-sm text-slate-600">Enter your email and we'll send you a reset link.</p>
                </div>

                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                        <input
                            type="email"
                            {...register('email')}
                            className="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-emerald-500"
                            placeholder="Enter your email"
                        />
                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email.message}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="w-full rounded-lg bg-emerald-700 px-6 py-3 font-semibold text-white shadow-md transition-all hover:bg-emerald-800 disabled:opacity-50"
                    >
                        {isSubmitting ? 'Sending...' : 'Send Reset Link'}
                    </button>

                    <Link to="/login" className="block text-center text-sm font-medium text-emerald-600 hover:text-emerald-700">
                        Back to login
                    </Link>
                </form>
            </div>
        </div>
    );
}
