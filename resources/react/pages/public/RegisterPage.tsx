import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../../api/client';

const steps = ['Personal Info', 'Contact & Address', 'Employment', 'Account'];

export default function RegisterPage() {
    const [step, setStep] = useState(0);
    const [form, setForm] = useState({
        first_name: '', middle_name: '', last_name: '', gender: '', birthdate: '', age: '',
        phone: '', email: '', address: '',
        occupation: '', monthly_income: '', membership_type: 'Regular', referred_by: '',
        password: '', password_confirmation: '',
    });
    const [success, setSuccess] = useState(false);

    const registerMutation = useMutation({
        mutationFn: () => api.post('/register', form),
        onSuccess: () => setSuccess(true),
    });

    const set = (key: string, value: any) => setForm(f => ({ ...f, [key]: value }));

    if (success) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 pt-20">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl">
                    <div className="mb-4 flex justify-center">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                            <i className="fas fa-check text-3xl text-emerald-600"></i>
                        </div>
                    </div>
                    <h2 className="mb-2 text-2xl font-bold text-slate-900">Application Submitted!</h2>
                    <p className="mb-6 text-slate-500">Your registration is pending approval. You'll be notified once approved.</p>
                    <Link to="/login" className="inline-flex items-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Go to Login</Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 pt-24 pb-16">
            <div className="mx-auto max-w-2xl px-4">
                <div className="mb-8 text-center">
                    <Link to="/" className="mb-4 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-emerald-600"><i className="fas fa-arrow-left"></i> Back to Home</Link>
                    <img src="/images/Logo.png" alt="Logo" className="mx-auto mb-4 h-14 w-14 rounded-full" />
                    <h1 className="text-3xl font-extrabold text-slate-900 md:text-4xl">Membership Application</h1>
                    <p className="mt-2 text-slate-500">Join Settlers Credit Cooperative</p>
                </div>

                {/* Step indicator */}
                <div className="mb-8 flex gap-2">
                    {steps.map((s, i) => (
                        <div key={s} className="flex flex-1 items-center gap-2">
                            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${i <= step ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500'}`}>{i + 1}</div>
                            <span className={`hidden text-xs font-medium sm:block ${i <= step ? 'text-emerald-600' : 'text-slate-400'}`}>{s}</span>
                            {i < steps.length - 1 && <div className={`hidden h-0.5 flex-1 sm:block ${i < step ? 'bg-emerald-600' : 'bg-slate-200'}`}></div>}
                        </div>
                    ))}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    {step === 0 && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900">Personal Information</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">First Name *</label><input value={form.first_name} onChange={e => set('first_name', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Middle Name</label><input value={form.middle_name} onChange={e => set('middle_name', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Last Name *</label><input value={form.last_name} onChange={e => set('last_name', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Gender *</label><select value={form.gender} onChange={e => set('gender', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"><option value="">Select</option><option>Male</option><option>Female</option></select></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Birthdate *</label><input type="date" value={form.birthdate} onChange={e => { set('birthdate', e.target.value); const age = Math.floor((Date.now() - new Date(e.target.value).getTime()) / 31557600000); set('age', String(age)); }} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Age</label><input value={form.age} readOnly className="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-500" /></div>
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900">Contact & Address</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Phone *</label><input value={form.phone} onChange={e => set('phone', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Email *</label><input type="email" value={form.email} onChange={e => set('email', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" /></div>
                            </div>
                            <div><label className="mb-1 block text-sm font-medium text-slate-700">Complete Address *</label><textarea rows={3} value={form.address} onChange={e => set('address', e.target.value)} required className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" placeholder="House No., Street, Barangay, City, Province" /></div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900">Employment & Membership</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Occupation *</label><input value={form.occupation} onChange={e => set('occupation', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Monthly Income *</label><select value={form.monthly_income} onChange={e => set('monthly_income', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"><option value="">Select</option><option>Below 10000</option><option>10000-20000</option><option>20001-30000</option><option>30001-50000</option><option>50001-75000</option><option>Above 75000</option></select></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Membership Type *</label><select value={form.membership_type} onChange={e => set('membership_type', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"><option>Regular</option><option>Associate</option></select></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Referred By</label><input value={form.referred_by} onChange={e => set('referred_by', e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" placeholder="Name of referring member" /></div>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900">Create Account</h3>
                            <div><label className="mb-1 block text-sm font-medium text-slate-700">Password *</label><input type="password" value={form.password} onChange={e => set('password', e.target.value)} required minLength={8} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" placeholder="Minimum 8 characters" /></div>
                            <div><label className="mb-1 block text-sm font-medium text-slate-700">Confirm Password *</label><input type="password" value={form.password_confirmation} onChange={e => set('password_confirmation', e.target.value)} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" /></div>
                        </div>
                    )}

                    {/* Navigation */}
                    <div className="mt-8 flex justify-between">
                        <button onClick={() => step > 0 ? setStep(step - 1) : window.history.back()} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            {step === 0 ? 'Cancel' : 'Back'}
                        </button>
                        {step < steps.length - 1 ? (
                            <button onClick={() => setStep(step + 1)} className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Next</button>
                        ) : (
                            <button onClick={() => registerMutation.mutate()} disabled={registerMutation.isPending} className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                                {registerMutation.isPending ? 'Submitting...' : 'Submit Application'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
