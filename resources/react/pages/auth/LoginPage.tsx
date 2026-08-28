import { useState, useRef } from 'react';
import { Link, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import TurnstileWidget from '../../components/TurnstileWidget';

const loginSchema = z.object({
    email: z.string().email('Please enter a valid email'),
    password: z.string().min(1, 'Password is required'),
    remember: z.boolean().optional(),
});

type LoginForm = z.infer<typeof loginSchema>;

export default function LoginPage() {
    const [showPassword, setShowPassword] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [loginError, setLoginError] = useState('');
    const turnstileRef = useRef<TurnstileWidget>(null);
    const { login } = useAuth();
    const { addToast } = useToast();
    const navigate = useNavigate();

    const { data: pc } = useQuery({
        queryKey: ['public-content'],
        queryFn: () => api.get('/api/public-content'),
    });
    const registrationEnabled = pc?.data?.form?.registration?.online_member_registration_enabled !== false;

    const { register, handleSubmit, formState: { errors } } = useForm<LoginForm>({
        resolver: zodResolver(loginSchema),
    });

    const onSubmit = async (data: LoginForm) => {
        setIsSubmitting(true);
        setLoginError('');
        try {
            const turnstileToken = turnstileRef.current?.getToken?.();
            const result = await login(data.email, data.password, data.remember, turnstileToken);
            if (result.requiresOtp) {
                window.location.assign(result.redirect || '/security/verify-otp');
                return;
            }
            navigate(result.redirect || '/dashboard');
        } catch (err: any) {
            const message = err.response?.data?.message || 'Login failed. Please try again.';
            setLoginError(message);
            addToast(message, 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="public-page min-h-screen flex items-center justify-center px-4 py-6">
            <div className="public-card w-full max-w-md">
                <div className="p-6">
                    <div className="text-center mb-6">
                        <div className="mb-3 flex justify-center">
                            <img src="/images/Logo.png" alt="Settlers Logo" className="h-12 w-12 rounded-full object-cover shadow-sm" />
                        </div>
                        <h1 className="text-2xl font-extrabold text-slate-900 mb-1">Welcome back</h1>
                        <p className="text-sm text-slate-600">
                            Enter your credentials to access your account
                        </p>
                    </div>

                    <form className="space-y-3" onSubmit={handleSubmit(onSubmit)}>
                        <div>
                            <label htmlFor="email" className="block text-xs font-medium text-slate-500 mb-1.5">Email</label>
                            <input
                                type="email"
                                id="email"
                                {...register('email')}
                                className="public-input text-sm"
                                placeholder="Enter your email"
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email.message}</p>}
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-xs font-medium text-slate-500 mb-1.5">Password</label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    id="password"
                                    {...register('password')}
                                    className="public-input pr-12 text-sm"
                                    placeholder="Enter your password"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                                >
                                    <i className={`fas fa-eye${showPassword ? '-slash' : ''}`}></i>
                                </button>
                            </div>
                            {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password.message}</p>}
                        </div>

                        {loginError && (
                            <div className="p-3 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                                <div className="flex items-center gap-2">
                                    <i className="fas fa-exclamation-circle text-red-500 text-lg shrink-0"></i>
                                    <p className="text-sm font-medium text-red-800">{loginError}</p>
                                    <button type="button" onClick={() => setLoginError('')} className="shrink-0 text-red-400 hover:text-red-600 transition-colors ml-auto">
                                        <i className="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        )}

                        <div className="flex items-center justify-between flex-wrap gap-2 pt-1">
                            <label className="flex items-center">
                                <input type="checkbox" {...register('remember')} className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer" />
                                <span className="ml-2 text-sm text-slate-600 cursor-pointer select-none">Remember me</span>
                            </label>
                            <Link to="/forgot-password" className="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                                Forgot password?
                            </Link>
                        </div>

                        <div className="flex justify-center my-2">
                            <TurnstileWidget ref={turnstileRef} />
                        </div>

                        <button type="submit" disabled={isSubmitting} className="public-btn public-btn-primary w-full">
                            {isSubmitting ? 'Signing in...' : 'Sign in'}
                        </button>

                        {registrationEnabled && (
                            <div className="text-center pt-4 mt-4 border-t border-gray-200">
                                <p className="text-sm text-slate-600 mb-2">Don't have an account?</p>
                                <Link to="/register" className="inline-flex items-center gap-2 text-emerald-700 hover:text-emerald-800 font-semibold transition-colors">
                                    <i className="fas fa-user-plus"></i>
                                    <span>Create an Account</span>
                                    <i className="fas fa-arrow-right text-sm"></i>
                                </Link>
                            </div>
                        )}

                        <div className="text-center pt-3">
                            <Link to="/" className="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors group">
                                <i className="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                                <span className="font-medium">Back to Home</span>
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
