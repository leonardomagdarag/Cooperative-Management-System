import { useState } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { resetPassword } from '../../api/auth';
import { useToast } from '../../contexts/ToastContext';

const schema = z.object({
    token: z.string().min(1, 'Token is required'),
    email: z.string().email('Please enter a valid email'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
}).refine(data => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ['password_confirmation'],
});

type Form = z.infer<typeof schema>;

export default function ResetPasswordPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [success, setSuccess] = useState(false);
    const { addToast } = useToast();

    const { register, handleSubmit, formState: { errors } } = useForm<Form>({
        resolver: zodResolver(schema),
        defaultValues: {
            token: searchParams.get('token') || '',
            email: searchParams.get('email') || '',
        },
    });

    const onSubmit = async (data: Form) => {
        setIsSubmitting(true);
        try {
            await resetPassword(data);
            setSuccess(true);
            addToast('Password reset successfully!', 'success');
        } catch (err: any) {
            addToast(err.response?.data?.message || 'Failed to reset password.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (success) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-lg">
                    <div className="mb-4 flex justify-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100">
                            <i className="fas fa-check text-2xl text-emerald-600"></i>
                        </div>
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900">Password Reset!</h1>
                    <p className="mt-2 text-sm text-slate-600">Your password has been reset successfully.</p>
                    <Link to="/login" className="mt-6 inline-block rounded-lg bg-emerald-700 px-6 py-2.5 font-semibold text-white hover:bg-emerald-800">
                        Go to Login
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
                            <i className="fas fa-lock text-2xl text-emerald-600"></i>
                        </div>
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900">Reset Password</h1>
                    <p className="mt-2 text-sm text-slate-600">Enter your new password below.</p>
                </div>

                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    <input type="hidden" {...register('token')} />

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

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">New Password</label>
                        <input
                            type="password"
                            {...register('password')}
                            className="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-emerald-500"
                            placeholder="Minimum 8 characters"
                        />
                        {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password.message}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">Confirm Password</label>
                        <input
                            type="password"
                            {...register('password_confirmation')}
                            className="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-emerald-500"
                            placeholder="Re-enter password"
                        />
                        {errors.password_confirmation && <p className="mt-1 text-xs text-red-500">{errors.password_confirmation.message}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="w-full rounded-lg bg-emerald-700 px-6 py-3 font-semibold text-white shadow-md transition-all hover:bg-emerald-800 disabled:opacity-50"
                    >
                        {isSubmitting ? 'Resetting...' : 'Reset Password'}
                    </button>
                </form>
            </div>
        </div>
    );
}
