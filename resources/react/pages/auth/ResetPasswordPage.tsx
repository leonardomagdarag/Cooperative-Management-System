import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { resetPassword } from '../../api/auth';
import { passwordPolicy } from '../../lib/validation';

export default function ResetPasswordPage() {
    const [params] = useSearchParams();
    const email = params.get('email') || '';
    const token = params.get('token') || '';
    const [done, setDone] = useState(false);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const { register, handleSubmit, formState: { errors } } = useForm({
        resolver: zodResolver(z.object({
            password: passwordPolicy,
            password_confirmation: z.string(),
        }).refine(data => data.password === data.password_confirmation, {
            message: "Passwords don't match", path: ['password_confirmation'],
        })),
    });

    const submit = async (data: { password: string; password_confirmation: string }) => {
        setSubmitting(true);
        setError('');
        try {
            await resetPassword({ email, token, ...data });
            setDone(true);
        } catch (err: any) {
            setError(err.response?.data?.message || 'This reset link is invalid or expired. Request a new link and try again.');
        } finally {
            setSubmitting(false);
        }
    };

    return <div className="public-page min-h-screen"><section className="public-auth-panel flex items-center justify-center px-6 py-6">
        <div className="public-card max-w-xl w-full p-6 md:p-8">
            <div className="text-center mb-6">
                <div className="inline-flex items-center justify-center w-14 h-14 bg-emerald-50 rounded-2xl mb-3"><i className="fas fa-lock text-2xl text-emerald-700" /></div>
                <h1 className="text-2xl font-medium text-slate-800">{done ? 'Password Reset' : 'Choose a New Password'}</h1>
                <p className="text-sm text-slate-600 mt-2">{done ? 'Your password has been updated.' : 'Reset links expire after 30 minutes.'}</p>
            </div>
            {done ? <Link to="/login" className="public-btn public-btn-primary w-full flex justify-center">Go to Login</Link>
                : !token || !email ? <div className="space-y-4"><p role="alert" className="text-sm text-red-700">This reset link is incomplete or invalid. Request a new one.</p><Link to="/forgot-password" className="public-btn public-btn-primary w-full flex justify-center">Request a New Link</Link></div>
                    : <form onSubmit={handleSubmit(submit)} className="space-y-4">
                        {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
                        <label className="block text-sm font-medium text-slate-700">New password<input type="password" autoComplete="new-password" {...register('password')} className="public-input mt-2" />{errors.password && <span className="text-red-600">{String(errors.password.message)}</span>}</label>
                        <label className="block text-sm font-medium text-slate-700">Confirm new password<input type="password" autoComplete="new-password" {...register('password_confirmation')} className="public-input mt-2" />{errors.password_confirmation && <span className="text-red-600">{String(errors.password_confirmation.message)}</span>}</label>
                        <p className="text-xs text-slate-500">At least 8 characters with uppercase, lowercase, and a number.</p>
                        <button disabled={submitting} className="public-btn public-btn-primary w-full">{submitting ? 'Updating…' : 'Reset Password'}</button>
                    </form>}
        </div>
    </section></div>;
}
