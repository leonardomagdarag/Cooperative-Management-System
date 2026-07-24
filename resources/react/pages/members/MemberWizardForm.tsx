import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

const STEPS = ['personal', 'address', 'contact', 'ids', 'employment', 'login', 'beneficiaries', 'review'];

const emptyForm = {
    first_name: '', mid_name: '', last_name: '', suffix: '', birth_date: '', birthplace: '',
    gender: '', civil_status: '', religion: '', title: 'Member',
    country: 'Philippines', region: '', province: '', city: '', barangay: '', street: '', postal_code: '',
    email: '', phone_number: '', facebook_link: '',
    national_id: '', tin: '', sss: '', philhealth: '', barangay_id: '',
    employment_type: '', employer_name: '', position: '', monthly_income: '',
    password: '', password_confirmation: '',
    beneficiaries: [] as any[],
};

export default function MemberWizardForm({ member, onClose }: { member: any; onClose: () => void }) {
    const [step, setStep] = useState(0);
    const [form, setForm] = useState(member ? {
        ...emptyForm,
        first_name: member.personal_data?.first_name || member.name?.split(' ')[0] || '',
        last_name: member.personal_data?.last_name || member.name?.split(' ').slice(1).join(' ') || '',
        email: member.email || '',
        phone_number: member.contact?.phone_no || '',
        gender: member.personal_data?.gender || '',
        civil_status: member.personal_data?.civil_status || '',
        birth_date: member.personal_data?.birth_date || '',
    } : emptyForm);
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const isEdit = !!member;

    const saveMutation = useMutation({
        mutationFn: (payload: any) => isEdit ? api.put(`/api/members/${member.id}`, payload) : api.post('/api/members', payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast(isEdit ? 'Member updated' : 'Member created', 'success'); onClose(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save', 'error'),
    });

    const set = (key: string, value: any) => setForm(f => ({ ...f, [key]: value }));
    const currentStep = STEPS[step];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                {/* Header */}
                <div className="sticky top-0 z-10 border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">{isEdit ? 'Edit' : 'New'} Member</h2>
                        <button onClick={onClose} className="text-slate-400 hover:text-slate-600"><i className="fas fa-times"></i></button>
                    </div>
                    {/* Step indicator */}
                    <div className="mt-3 flex gap-1">
                        {STEPS.map((s, i) => (
                            <div key={s} className={`h-1 flex-1 rounded-full ${i <= step ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
                        ))}
                    </div>
                    <p className="mt-2 text-xs text-slate-500">Step {step + 1} of {STEPS.length}: {currentStep}</p>
                </div>

                {/* Step content */}
                <div className="p-6">
                    {currentStep === 'personal' && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Personal Information</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="First Name" value={form.first_name} onChange={v => set('first_name', v)} required />
                                <Field label="Middle Name" value={form.mid_name} onChange={v => set('mid_name', v)} />
                                <Field label="Last Name" value={form.last_name} onChange={v => set('last_name', v)} required />
                                <Field label="Suffix" value={form.suffix} onChange={v => set('suffix', v)} />
                                <Field label="Birth Date" type="date" value={form.birth_date} onChange={v => set('birth_date', v)} required />
                                <Field label="Birthplace" value={form.birthplace} onChange={v => set('birthplace', v)} />
                                <Select label="Gender" value={form.gender} onChange={v => set('gender', v)} options={['male', 'female', 'other']} required />
                                <Select label="Civil Status" value={form.civil_status} onChange={v => set('civil_status', v)} options={['single', 'married', 'widowed', 'separated']} required />
                                <Field label="Religion" value={form.religion} onChange={v => set('religion', v)} />
                                <Field label="Title" value={form.title} onChange={v => set('title', v)} />
                            </div>
                        </div>
                    )}

                    {currentStep === 'address' && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Address</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Country" value={form.country} onChange={v => set('country', v)} />
                                <Field label="Region" value={form.region} onChange={v => set('region', v)} />
                                <Field label="Province" value={form.province} onChange={v => set('province', v)} />
                                <Field label="City/Municipality" value={form.city} onChange={v => set('city', v)} />
                                <Field label="Barangay" value={form.barangay} onChange={v => set('barangay', v)} />
                                <Field label="Street" value={form.street} onChange={v => set('street', v)} />
                                <Field label="Postal Code" value={form.postal_code} onChange={v => set('postal_code', v)} />
                            </div>
                        </div>
                    )}

                    {currentStep === 'contact' && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Contact Information</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Email" type="email" value={form.email} onChange={v => set('email', v)} required />
                                <Field label="Phone Number" value={form.phone_number} onChange={v => set('phone_number', v)} required />
                                <Field label="Facebook Link" value={form.facebook_link} onChange={v => set('facebook_link', v)} />
                            </div>
                        </div>
                    )}

                    {currentStep === 'ids' && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Government IDs</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="National ID" value={form.national_id} onChange={v => set('national_id', v)} />
                                <Field label="TIN" value={form.tin} onChange={v => set('tin', v)} />
                                <Field label="SSS" value={form.sss} onChange={v => set('sss', v)} />
                                <Field label="PhilHealth" value={form.philhealth} onChange={v => set('philhealth', v)} />
                                <Field label="Barangay ID" value={form.barangay_id} onChange={v => set('barangay_id', v)} />
                            </div>
                        </div>
                    )}

                    {currentStep === 'employment' && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Employment</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Select label="Employment Type" value={form.employment_type} onChange={v => set('employment_type', v)} options={['full-time', 'part-time', 'self-employed', 'freelancer', 'government', 'private', 'ofw', 'retired', 'unemployed', 'student', 'homemaker']} />
                                <Field label="Employer Name" value={form.employer_name} onChange={v => set('employer_name', v)} />
                                <Field label="Position" value={form.position} onChange={v => set('position', v)} />
                                <Field label="Monthly Income" type="number" value={form.monthly_income} onChange={v => set('monthly_income', v)} />
                            </div>
                        </div>
                    )}

                    {currentStep === 'login' && !isEdit && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Login Credentials</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Password" type="password" value={form.password} onChange={v => set('password', v)} required />
                                <Field label="Confirm Password" type="password" value={form.password_confirmation} onChange={v => set('password_confirmation', v)} required />
                            </div>
                        </div>
                    )}

                    {currentStep === 'beneficiaries' && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-slate-900 dark:text-white">Beneficiaries</h3>
                                <button type="button" onClick={() => set('beneficiaries', [...form.beneficiaries, { first_name: '', last_name: '', relationship: '' }])} className="text-sm text-emerald-600 hover:text-emerald-700"><i className="fas fa-plus mr-1"></i> Add</button>
                            </div>
                            {form.beneficiaries.length === 0 && <p className="text-sm text-slate-500">No beneficiaries added.</p>}
                            {form.beneficiaries.map((b: any, i: number) => (
                                <div key={i} className="flex items-end gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-600">
                                    <div className="flex-1 grid grid-cols-3 gap-2">
                                        <input placeholder="First Name" value={b.first_name} onChange={e => { const list = [...form.beneficiaries]; list[i].first_name = e.target.value; set('beneficiaries', list); }} className="rounded border border-slate-200 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                        <input placeholder="Last Name" value={b.last_name} onChange={e => { const list = [...form.beneficiaries]; list[i].last_name = e.target.value; set('beneficiaries', list); }} className="rounded border border-slate-200 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                        <input placeholder="Relationship" value={b.relationship} onChange={e => { const list = [...form.beneficiaries]; list[i].relationship = e.target.value; set('beneficiaries', list); }} className="rounded border border-slate-200 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-700" />
                                    </div>
                                    <button type="button" onClick={() => { const list = [...form.beneficiaries]; list.splice(i, 1); set('beneficiaries', list); }} className="text-red-400 hover:text-red-600"><i className="fas fa-times"></i></button>
                                </div>
                            ))}
                        </div>
                    )}

                    {currentStep === 'review' && (
                        <div className="space-y-4">
                            <h3 className="font-semibold text-slate-900 dark:text-white">Review</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <Info label="Name" value={`${form.first_name} ${form.last_name}`} />
                                <Info label="Email" value={form.email} />
                                <Info label="Phone" value={form.phone_number} />
                                <Info label="Gender" value={form.gender} />
                                <Info label="Civil Status" value={form.civil_status} />
                                <Info label="Birth Date" value={form.birth_date} />
                                <Info label="Address" value={`${form.street}, ${form.barangay}, ${form.city}, ${form.province}`} />
                                <Info label="Employment" value={form.employment_type || '—'} />
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="sticky bottom-0 border-t border-slate-200 bg-white px-6 py-4 dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex justify-between">
                        <button type="button" onClick={() => step > 0 ? setStep(step - 1) : onClose()} className="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50 dark:border-slate-600">
                            {step === 0 ? 'Cancel' : 'Back'}
                        </button>
                        <div className="flex gap-2">
                            {step < STEPS.length - 1 ? (
                                <button onClick={() => setStep(step + 1)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Next</button>
                            ) : (
                                <button onClick={() => saveMutation.mutate(form)} disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {saveMutation.isPending ? 'Saving...' : isEdit ? 'Update' : 'Create Member'}
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Field({ label, value, onChange, type = 'text', required }: { label: string; value: any; onChange: (v: any) => void; type?: string; required?: boolean }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{label}{required && <span className="text-red-500 ml-0.5">*</span>}</label>
            <input type={type} value={value || ''} onChange={e => onChange(e.target.value)} required={required} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
        </div>
    );
}

function Select({ label, value, onChange, options, required }: { label: string; value: any; onChange: (v: any) => void; options: string[]; required?: boolean }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{label}{required && <span className="text-red-500 ml-0.5">*</span>}</label>
            <select value={value || ''} onChange={e => onChange(e.target.value)} required={required} className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700">
                <option value="">Select...</option>
                {options.map(o => <option key={o} value={o}>{o}</option>)}
            </select>
        </div>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
            <p className="text-xs text-slate-500">{label}</p>
            <p className="text-sm font-medium text-slate-900 dark:text-white">{value || '—'}</p>
        </div>
    );
}
