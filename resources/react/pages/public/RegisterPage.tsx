import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../../api/client';

const steps = [
    { title: 'Personal Information', icon: 'fas fa-user', subtitle: 'Please provide your basic personal details' },
    { title: 'Contact & Address', icon: 'fas fa-address-book', subtitle: 'How can we reach you?' },
    { title: 'Employment & Membership', icon: 'fas fa-briefcase', subtitle: 'Tell us about your current occupation' },
    { title: 'Account Credentials', icon: 'fas fa-lock', subtitle: 'Create a secure password for your account' },
];

export default function RegisterPage() {
    const [step, setStep] = useState(0);
    const [form, setForm] = useState({
        first_name: '', middle_name: '', last_name: '', gender: '', birthdate: '', age: '',
        phone: '', email: '', address: '',
        occupation: '', monthly_income: '', membership_type: 'Regular', referred_by: '',
        password: '', password_confirmation: '',
    });
    const [success, setSuccess] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const registerMutation = useMutation({
        mutationFn: () => api.post('/register', form),
        onSuccess: () => setSuccess(true),
    });

    const set = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

    const calcAge = (bd: string) => {
        const age = Math.floor((Date.now() - new Date(bd).getTime()) / 31557600000);
        return String(Math.max(0, age));
    };

    if (success) {
        return (
            <div className="public-page min-h-screen">
                <section className="min-h-screen py-12 px-4 flex items-center justify-center">
                    <div className="public-card p-8 max-w-md w-full text-center">
                        <div className="mb-4 flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                                <i className="fas fa-check text-3xl text-emerald-600"></i>
                            </div>
                        </div>
                        <h2 className="mb-2 text-2xl font-bold text-slate-900">Application Submitted!</h2>
                        <p className="mb-6 text-slate-500">Your registration is pending approval. You'll be notified once approved.</p>
                        <Link to="/login" className="public-btn public-btn-primary">
                            <i className="fas fa-sign-in-alt mr-2"></i>Go to Login
                        </Link>
                    </div>
                </section>
            </div>
        );
    }

    return (
        <div className="public-page">
            <section className="min-h-screen py-12 px-4">
                <div className="max-w-4xl mx-auto">
                    <div className="text-center mb-8">
                        <Link to="/" className="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors mb-6">
                            <i className="fas fa-arrow-left"></i>
                            <span className="font-medium">Back to Home</span>
                        </Link>
                        <div className="mb-4 flex justify-center">
                            <img src="/images/Logo.png" alt="Settlers Logo" className="h-16 w-16 rounded-full object-cover shadow-sm" />
                        </div>
                        <h1 className="text-3xl md:text-5xl font-extrabold text-slate-900 mb-2">Membership Application</h1>
                        <p className="text-base text-slate-600">
                            Join Settlers Credit Cooperative and become part of our growing community
                        </p>
                    </div>

                    <div className="public-card p-6 mb-8">
                        <div className="flex items-start gap-4 mb-4">
                            <div className="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center shrink-0">
                                <i className="fas fa-info-circle text-emerald-700 text-lg"></i>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-slate-800 mb-2">Membership Requirements</h3>
                                <div className="grid md:grid-cols-3 gap-4 text-sm text-slate-600">
                                    <div className="flex items-center gap-2"><i className="fas fa-check-circle text-emerald-600"></i><span>Minimum Share Capital: <strong>₱500</strong></span></div>
                                    <div className="flex items-center gap-2"><i className="fas fa-check-circle text-emerald-600"></i><span>Processing Fee: <strong>₱100</strong></span></div>
                                    <div className="flex items-center gap-2"><i className="fas fa-check-circle text-emerald-600"></i><span>Initial Savings: <strong>₱500</strong></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="public-card p-8">
                        <div className="mb-8 flex gap-2">
                            {steps.map((s, i) => (
                                <div key={s.title} className="flex flex-1 items-center gap-2">
                                    <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${i <= step ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                        {i < step ? <i className="fas fa-check text-xs"></i> : i + 1}
                                    </div>
                                    <span className={`hidden text-xs font-medium sm:block ${i <= step ? 'text-emerald-700' : 'text-gray-500'}`}>{s.title}</span>
                                    {i < steps.length - 1 && <div className={`hidden h-0.5 flex-1 sm:block ${i < step ? 'bg-emerald-700' : 'bg-gray-200'}`}></div>}
                                </div>
                            ))}
                        </div>

                        <div className="mb-10">
                            <h2 className="text-xl font-bold text-slate-800 mb-1 flex items-center gap-2">
                                <i className={`${steps[step].icon} text-emerald-700`}></i>
                                {steps[step].title}
                            </h2>
                            <p className="text-sm text-slate-500 mb-6">{steps[step].subtitle}</p>

                            <div className="space-y-5">
                                {step === 0 && (
                                    <>
                                        <div className="grid md:grid-cols-3 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">First Name <span className="text-red-500">*</span></label>
                                                <input value={form.first_name} onChange={e => set('first_name', e.target.value)} required className="public-input py-2.5 text-sm" placeholder="Juan" />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Middle Name</label>
                                                <input value={form.middle_name} onChange={e => set('middle_name', e.target.value)} className="public-input py-2.5 text-sm" placeholder="Santos" />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Last Name <span className="text-red-500">*</span></label>
                                                <input value={form.last_name} onChange={e => set('last_name', e.target.value)} required className="public-input py-2.5 text-sm" placeholder="Dela Cruz" />
                                            </div>
                                        </div>
                                        <div className="grid md:grid-cols-3 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Gender <span className="text-red-500">*</span></label>
                                                <select value={form.gender} onChange={e => set('gender', e.target.value)} required className="public-input py-2.5 text-sm">
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                    <option value="Other">Prefer not to say</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Birthday <span className="text-red-500">*</span></label>
                                                <input type="date" value={form.birthdate} onChange={e => { set('birthdate', e.target.value); set('age', calcAge(e.target.value)); }} required className="public-input py-2.5 text-sm" />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Age <span className="text-red-500">*</span></label>
                                                <input value={form.age} readOnly className="public-input py-2.5 bg-slate-100 text-sm cursor-not-allowed" placeholder="Auto-calculated" />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {step === 1 && (
                                    <>
                                        <div className="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Phone Number <span className="text-red-500">*</span></label>
                                                <input value={form.phone} onChange={e => set('phone', e.target.value)} required className="public-input py-2.5 text-sm" placeholder="09171234567" />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Email Address <span className="text-red-500">*</span></label>
                                                <input type="email" value={form.email} onChange={e => set('email', e.target.value)} required className="public-input py-2.5 text-sm" placeholder="juan.delacruz@email.com" />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-2">Complete Address <span className="text-red-500">*</span></label>
                                            <textarea rows={3} value={form.address} onChange={e => set('address', e.target.value)} required className="public-input py-2.5 text-sm" placeholder="House No., Street, Barangay, City/Municipality, Province" />
                                        </div>
                                    </>
                                )}

                                {step === 2 && (
                                    <>
                                        <div className="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Occupation <span className="text-red-500">*</span></label>
                                                <input value={form.occupation} onChange={e => set('occupation', e.target.value)} required className="public-input py-2.5 text-sm" placeholder="e.g., Teacher, Engineer, Business Owner" />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Monthly Income <span className="text-red-500">*</span></label>
                                                <select value={form.monthly_income} onChange={e => set('monthly_income', e.target.value)} required className="public-input py-2.5 text-sm">
                                                    <option value="">Select Income Range</option>
                                                    <option value="Below 10000">Below ₱10,000</option>
                                                    <option value="10000-20000">₱10,000 - ₱20,000</option>
                                                    <option value="20001-30000">₱20,001 - ₱30,000</option>
                                                    <option value="30001-50000">₱30,001 - ₱50,000</option>
                                                    <option value="50001-75000">₱50,001 - ₱75,000</option>
                                                    <option value="Above 75000">Above ₱75,000</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="grid md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Membership Type <span className="text-red-500">*</span></label>
                                                <select value={form.membership_type} onChange={e => set('membership_type', e.target.value)} required className="public-input py-2.5 text-sm">
                                                    <option value="Regular">Regular Member</option>
                                                    <option value="Associate">Associate Member</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-2">Referred By (Optional)</label>
                                                <input value={form.referred_by} onChange={e => set('referred_by', e.target.value)} className="public-input py-2.5 text-sm" placeholder="Name of referring member" />
                                            </div>
                                        </div>
                                    </>
                                )}

                                {step === 3 && (
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-2">Password <span className="text-red-500">*</span></label>
                                            <div className="relative">
                                                <input type={showPassword ? 'text' : 'password'} value={form.password} onChange={e => set('password', e.target.value)} required minLength={8} className="public-input py-2.5 pr-12 text-sm" placeholder="Minimum 8 characters" />
                                                <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                                    <i className={`fas fa-eye${showPassword ? '-slash' : ''}`}></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-2">Confirm Password <span className="text-red-500">*</span></label>
                                            <div className="relative">
                                                <input type={showConfirm ? 'text' : 'password'} value={form.password_confirmation} onChange={e => set('password_confirmation', e.target.value)} required className="public-input py-2.5 pr-12 text-sm" placeholder="Re-enter password" />
                                                <button type="button" onClick={() => setShowConfirm(!showConfirm)} className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                                    <i className={`fas fa-eye${showConfirm ? '-slash' : ''}`}></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-col sm:flex-row gap-4">
                            <button onClick={() => step > 0 ? setStep(step - 1) : window.history.back()} className="flex-1 sm:flex-initial bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3.5 px-6 rounded-lg transition-all text-center">
                                {step === 0 ? 'Cancel' : 'Back'}
                            </button>
                            {step < steps.length - 1 ? (
                                <button onClick={() => setStep(step + 1)} className="public-btn public-btn-primary flex-1">
                                    Next <i className="fas fa-arrow-right ml-2"></i>
                                </button>
                            ) : (
                                <button onClick={() => registerMutation.mutate()} disabled={registerMutation.isPending} className="public-btn public-btn-primary flex-1">
                                    {registerMutation.isPending ? 'Submitting...' : 'Submit Application'}
                                    <i className="fas fa-user-plus ml-2"></i>
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
