import { useState, useRef } from 'react';
import { Link, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import FallbackImage from '../../components/FallbackImage';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import TurnstileWidget from '../../components/TurnstileWidget';
import { useLanguage } from '../../contexts/LanguageContext';

const loginSchema = z.object({
    email: z.string().min(1, 'Please enter your email or phone number'),
    password: z.string().min(1, 'Password is required'),
    remember: z.boolean().optional(),
});

type LoginForm = z.infer<typeof loginSchema>;

export default function LoginPage() {
    const { t } = useLanguage();
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
    const loginImage = pc?.data?.form?.login?.login_image || '/images/hero_banner.jpg';
    const loginEnabled = pc?.data?.availability?.login?.global !== false;

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
            if (result.requiresApproval) {
                navigate('/login/approval');
                return;
            }
            navigate(result.redirect || '/dashboard');
        } catch (err: any) {
            const message = err.response?.data?.message || t('Login failed. Please try again.');
            setLoginError(message);
            addToast(message, 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="public-page relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-16 sm:py-12">
            <div className="absolute inset-0" aria-hidden="true">
                <FallbackImage src={loginImage} alt="" className="h-full w-full object-cover" />
                <div className="absolute inset-0 bg-gradient-to-br from-emerald-950/85 via-teal-900/65 to-slate-950/70" />
                <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-emerald-400/20 blur-3xl" />
                <div className="absolute -bottom-32 -right-20 h-[28rem] w-[28rem] rounded-full bg-cyan-400/20 blur-3xl" />
            </div>
            <Link to="/" className="absolute left-4 top-4 z-20 inline-flex items-center gap-2 rounded-full border border-white/30 bg-slate-950/25 px-4 py-2 text-sm font-medium text-white backdrop-blur transition-colors hover:bg-slate-950/45 sm:left-6 sm:top-6">
                <i className="fas fa-arrow-left text-xs" />
                <span>{t('Back to Home')}</span>
            </Link>
            <div className="public-card relative z-10 my-auto w-full max-w-md border border-white/50 bg-white/95 shadow-2xl shadow-slate-950/30 backdrop-blur-sm">
                <div className="p-5 sm:p-6">
                    <div className="text-center mb-4">
                        <div className="mb-3 flex justify-center">
                            <FallbackImage src="/images/Logo.png" alt="Settlers Logo" className="h-12 w-12 rounded-full object-cover shadow-sm" />
                        </div>
                        <h1 className="mb-1 text-2xl font-medium text-slate-900">{t('Welcome back')}</h1>
                        <p className="text-sm text-slate-600">
                            {t('Enter your credentials to access your account')}
                        </p>
                    </div>

                    {loginEnabled ? <form className="space-y-3" onSubmit={handleSubmit(onSubmit)}>
                        <div>
                            <label htmlFor="email" className="block text-xs font-medium text-slate-500 mb-1.5">{t('Email or Mobile Number')}</label>
                            <input
                                type="text"
                                id="email"
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                                autoComplete="username"
                                {...register('email')}
                                className="public-input text-sm"
                                placeholder={t('Enter your email or phone number')}
                            />
                            {errors.email && <p id="email-error" role="alert" className="mt-1 text-xs text-red-500">{t(errors.email.message || '')}</p>}
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-xs font-medium text-slate-500 mb-1.5">{t('Password')}</label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    id="password"
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={errors.password ? 'password-error' : undefined}
                                    autoComplete="current-password"
                                    {...register('password')}
                                    className="public-input pr-12 text-sm"
                                    placeholder={t('Enter your password')}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                                >
                                    <i className={`fas fa-eye${showPassword ? '-slash' : ''}`}></i>
                                </button>
                            </div>
                            {errors.password && <p id="password-error" role="alert" className="mt-1 text-xs text-red-500">{t(errors.password.message || '')}</p>}
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
                                <input type="checkbox" {...register('remember')} className="w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-2 focus:ring-emerald-500 cursor-pointer" />
                                <span className="ml-2 text-sm text-slate-600 cursor-pointer select-none">{t('Remember me')}</span>
                            </label>
                            <Link to="/forgot-password" className="text-sm font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                                {t('Forgot password?')}
                            </Link>
                        </div>

                        <div className="flex justify-center my-2">
                            <TurnstileWidget ref={turnstileRef} />
                        </div>

                        <button type="submit" disabled={isSubmitting} className="public-btn public-btn-primary w-full">
                            {t(isSubmitting ? 'Signing in...' : 'Sign in')}
                        </button>

                        <div className="text-center pt-4 mt-4 border-t border-gray-200">
                            <p className="text-sm text-slate-600 mb-2">{t('Interested in becoming a member? Apply at our main office or a branch.')}</p>
                            <Link to="/contact" className="inline-flex items-center gap-2 text-emerald-700 hover:text-emerald-800 font-semibold transition-colors">
                                <span>{t('Contact us about membership')}</span>
                                <i className="fas fa-arrow-right text-sm"></i>
                            </Link>
                        </div>

                    </form> : (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center">
                            <i className="fas fa-sign-in-alt text-3xl text-amber-600 mb-3"></i>
                            <p className="font-semibold text-amber-900">{t('Login is currently unavailable')}</p>
                            <p className="mt-1 text-sm text-amber-800">{t('Online access has been temporarily suspended. Please check back later.')}</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
