import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { api } from '../../api/client';
import AddressSelector, { type AddressValues } from '../../components/AddressSelector';
import { useAuth } from '../../contexts/AuthContext';

const CIVIL_STATUS_OPTIONS = ['single', 'married', 'widowed', 'separated'];
const EMPLOYMENT_TYPE_OPTIONS = ['full-time', 'part-time', 'self-employed', 'freelancer', 'government', 'private', 'ofw', 'retired', 'unemployed', 'student', 'homemaker'];

interface BeneficiaryRow {
    first_name: string;
    last_name: string;
    relationship: string;
}

export default function ProfileCompletionPage() {
    const navigate = useNavigate();
    const { refreshUser } = useAuth();

    const { data: completion, isLoading: loadingCompletion } = useQuery({
        queryKey: ['profile-completion'],
        queryFn: () => api.get('/api/profile/completion'),
    });

    const { data: profile, isLoading: loadingProfile } = useQuery({
        queryKey: ['profile-data'],
        queryFn: () => api.get('/api/profile'),
    });

    const missing = new Set<string>(completion?.data?.missing ?? []);
    const required = completion?.data?.required ?? false;

    const initial = useMemo(() => {
        const pd = profile?.data?.personal_data ?? {};
        const adr = profile?.data?.address ?? {};
        const ctc = profile?.data?.contact ?? {};
        const ids = profile?.data?.member_id ?? {};
        const emp = profile?.data?.employment ?? {};

        return {
            civil_status: pd.civil_status ?? '',
            religion: pd.religion ?? '',
            birthplace: pd.birthplace ?? '',
            street: adr.street ?? '',
            zip_code: adr.zip_code ?? '',
            region: adr.region ?? '',
            province: adr.province ?? '',
            city_municipality: adr.city_municipality ?? '',
            barangay: adr.barangay ?? '',
            phone_no: ctc.phone_no ?? '',
            national_id: ids.national_id ?? '',
            tin: ids.tin ?? '',
            sss: ids.sss ?? '',
            philhealth: ids.philhealth ?? '',
            barangay_id: ids.barangay_id ?? '',
            employment_type: emp.employment_type ?? '',
            employer_name: emp.company ?? '',
            position: emp.position ?? '',
            monthly_income: emp.monthly_income ?? '',
            beneficiaries: (profile?.data?.beneficiaries ?? []).map((b: any) => ({
                first_name: b.first_name ?? '',
                last_name: b.last_name ?? '',
                relationship: b.relationship ?? '',
            })) as BeneficiaryRow[],
        };
    }, [profile?.data]);

    const [form, setForm] = useState(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        setForm(initial);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [profile?.data]);

    const set = (key: string, value: any) => setForm(f => ({ ...f, [key]: value }));

    const saveMutation = useMutation({
        mutationFn: (payload: any) => api.put('/api/profile', payload),
        onSuccess: async () => {
            await refreshUser();
            navigate('/dashboard', { replace: true });
        },
        onError: (err: any) => {
            const server = err.response?.data?.errors;
            if (server) {
                const mapped: Record<string, string> = {};
                Object.entries(server).forEach(([k, v]: any) => { mapped[k] = v?.[0] ?? String(v); });
                setErrors(mapped);
            } else {
                setErrors({ form: err.response?.data?.message || 'Failed to save your profile. Please try again.' });
            }
        },
    });

    const validateSection = (section: string): boolean => {
        const sectionErrors: Record<string, string> = {};
        if (section === 'personal') {
            if (!form.civil_status) sectionErrors.civil_status = 'Select your civil status';
            if (!form.religion) sectionErrors.religion = 'Religion is required';
            if (!form.birthplace) sectionErrors.birthplace = 'Birthplace is required';
        }
        if (section === 'address') {
            if (!form.region) sectionErrors.region = 'Select your region';
            if (!form.province) sectionErrors.province = 'Select your province';
            if (!form.city_municipality) sectionErrors.city_municipality = 'Select your city/municipality';
            if (!form.barangay) sectionErrors.barangay = 'Select your barangay';
            if (!form.street) sectionErrors.street = 'Street address is required';
            if (!form.zip_code) sectionErrors.zip_code = 'ZIP code is required';
        }
        if (section === 'contact' && !form.phone_no) sectionErrors.phone_no = 'Phone number is required';
        if (section === 'ids' && !form.national_id && !form.tin && !form.sss && !form.philhealth && !form.barangay_id) {
            sectionErrors.ids = 'Provide at least one government ID number';
        }
        if (section === 'employment' && !form.employment_type) sectionErrors.employment_type = 'Select an employment type';
        if (section === 'beneficiaries') {
            form.beneficiaries.forEach((b, i) => {
                if (!b.first_name || !b.last_name || !b.relationship) {
                    sectionErrors.beneficiaries = 'Complete all beneficiary fields';
                }
            });
        }
        setErrors(prev => ({ ...prev, ...sectionErrors }));
        return Object.keys(sectionErrors).length === 0;
    };

    const submit = () => {
        setErrors({});
        const allValid = [...missing].every(section => {
            if (section === 'agreement') return true;
            return validateSection(section);
        });
        if (!allValid || !required) return;

        const payload: Record<string, any> = {
            first_name: profile?.data?.personal_data?.first_name ?? 'Member',
            last_name: profile?.data?.personal_data?.last_name ?? 'Member',
            email: profile?.data?.user?.email ?? '',
            civil_status: form.civil_status,
            religion: form.religion,
            birthplace: form.birthplace,
            country: 'Philippines',
            region: form.region,
            province: form.province,
            city_municipality: form.city_municipality,
            barangay: form.barangay,
            street: form.street,
            zip_code: form.zip_code,
            phone_no: form.phone_no,
            national_id: form.national_id,
            tin: form.tin,
            sss: form.sss,
            philhealth: form.philhealth,
            barangay_id: form.barangay_id,
            employment_type: form.employment_type,
            employer_name: form.employer_name,
            position: form.position,
            monthly_income: form.monthly_income ? String(form.monthly_income) : '',
            beneficiaries: form.beneficiaries.filter(b => b.first_name || b.last_name || b.relationship),
        };
        saveMutation.mutate(payload);
    };

    if (loadingCompletion || loadingProfile) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <p className="text-sm text-gray-500"><i className="fas fa-spinner fa-spin mr-2"></i>Loading profile...</p>
            </div>
        );
    }

    if (!required || missing.size === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center p-8">
                    <i className="fas fa-check-circle text-4xl text-emerald-600 mb-3"></i>
                    <h2 className="text-xl font-bold text-gray-800">Your profile is complete</h2>
                    <p className="text-sm text-gray-500 mt-2">You're all set. Redirecting you to the dashboard...</p>
                    <button onClick={() => navigate('/dashboard')} className="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Go to Dashboard</button>
                </div>
            </div>
        );
    }

    const inputClass = (field: string) => `w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 ${errors[field] ? 'border-red-400 focus:border-red-500 focus:ring-red-200' : 'border-gray-300 focus:border-cyan-500 focus:ring-cyan-200'}`;

    return (
        <div className="min-h-screen bg-gray-50 py-10 px-4">
            <div className="mx-auto max-w-3xl">
                <div className="text-center mb-8">
                    <div className="mb-4 flex justify-center">
                        <img src="/images/Logo.png" alt="Settlers Logo" className="h-16 w-16 rounded-full object-cover shadow-sm" />
                    </div>
                    <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900">Complete Your Profile</h1>
                    <p className="mt-2 text-sm text-gray-600">Your membership is approved! Fill in the missing details below to finish setting up your account.</p>
                </div>

                <div className="space-y-5">
                    {missing.has('personal') && (
                        <div className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="mb-1 text-lg font-bold text-gray-900 flex items-center gap-2"><i className="fas fa-user text-cyan-700"></i> Personal Details</h2>
                            <p className="mb-4 text-xs text-gray-500">Civil status, religion and birthplace</p>
                            <div className="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Civil Status <span className="text-red-500">*</span></label>
                                    <select value={form.civil_status} onChange={e => set('civil_status', e.target.value)} className={inputClass('civil_status')}>
                                        <option value="">Select civil status</option>
                                        {CIVIL_STATUS_OPTIONS.map(o => <option key={o} value={o} className="capitalize">{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
                                    </select>
                                    {errors.civil_status && <p className="mt-1 text-xs text-red-600">{errors.civil_status}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Religion <span className="text-red-500">*</span></label>
                                    <input value={form.religion} onChange={e => set('religion', e.target.value)} placeholder="e.g. Roman Catholic" className={inputClass('religion')} />
                                    {errors.religion && <p className="mt-1 text-xs text-red-600">{errors.religion}</p>}
                                </div>
                                <div className="md:col-span-2">
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Birthplace <span className="text-red-500">*</span></label>
                                    <input value={form.birthplace} onChange={e => set('birthplace', e.target.value)} placeholder="City/Municipality, Province" className={inputClass('birthplace')} />
                                    {errors.birthplace && <p className="mt-1 text-xs text-red-600">{errors.birthplace}</p>}
                                </div>
                            </div>
                        </div>
                    )}

                    {missing.has('address') && (
                        <div className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="mb-1 text-lg font-bold text-gray-900 flex items-center gap-2"><i className="fas fa-map-marker-alt text-cyan-700"></i> Complete Address</h2>
                            <p className="mb-4 text-xs text-gray-500">Fill in any missing address details</p>
                            <AddressSelector
                                value={{ region: form.region, province: form.province, city: form.city_municipality, barangay: form.barangay, street: form.street, zip_code: form.zip_code }}
                                onChange={(v: AddressValues) => setForm(f => ({
                                    ...f,
                                    region: v.region,
                                    province: v.province,
                                    city_municipality: v.city,
                                    barangay: v.barangay,
                                    street: v.street,
                                    zip_code: v.zip_code,
                                }))}
                                errors={errors}
                            />
                        </div>
                    )}

                    {missing.has('contact') && (
                        <div className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="mb-1 text-lg font-bold text-gray-900 flex items-center gap-2"><i className="fas fa-phone text-cyan-700"></i> Contact Details</h2>
                            <p className="mb-4 text-xs text-gray-500">Provide a mobile number for verification</p>
                            <div className="max-w-md">
                                <label className="mb-1 block text-sm font-medium text-gray-700">Phone Number <span className="text-red-500">*</span></label>
                                <input value={form.phone_no} onChange={e => set('phone_no', e.target.value)} placeholder="09171234567" className={inputClass('phone_no')} />
                                {errors.phone_no && <p className="mt-1 text-xs text-red-600">{errors.phone_no}</p>}
                            </div>
                        </div>
                    )}

                    {missing.has('ids') && (
                        <div className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="mb-1 text-lg font-bold text-gray-900 flex items-center gap-2"><i className="fas fa-id-card text-cyan-700"></i> Government IDs</h2>
                            <p className="mb-4 text-xs text-gray-500">Provide at least one government ID number</p>
                            <div className="grid md:grid-cols-2 gap-4">
                                {(['national_id', 'tin', 'sss', 'philhealth', 'barangay_id'] as const).map(field => (
                                    <div key={field}>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">{field === 'barangay_id' ? 'Barangay ID' : field.toUpperCase()}</label>
                                        <input value={(form as any)[field] || ''} onChange={e => set(field, e.target.value)} placeholder={`${field === 'barangay_id' ? 'Barangay' : field.toUpperCase()} number`} className={inputClass(field)} />
                                    </div>
                                ))}
                                {errors.ids && <p className="md:col-span-2 text-xs text-red-600">{errors.ids}</p>}
                            </div>
                        </div>
                    )}

                    {missing.has('employment') && (
                        <div className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="mb-1 text-lg font-bold text-gray-900 flex items-center gap-2"><i className="fas fa-briefcase text-cyan-700"></i> Employment Details</h2>
                            <p className="mb-4 text-xs text-gray-500">Current employment information</p>
                            <div className="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Employment Type <span className="text-red-500">*</span></label>
                                    <select value={form.employment_type} onChange={e => set('employment_type', e.target.value)} className={inputClass('employment_type')}>
                                        <option value="">Select employment type</option>
                                        {EMPLOYMENT_TYPE_OPTIONS.map(o => <option key={o} value={o} className="capitalize">{o.replace('-', ' ').charAt(0).toUpperCase() + o.replace('-', ' ').slice(1)}</option>)}
                                    </select>
                                    {errors.employment_type && <p className="mt-1 text-xs text-red-600">{errors.employment_type}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Employer / Company</label>
                                    <input value={form.employer_name} onChange={e => set('employer_name', e.target.value)} placeholder="Company name" className={inputClass('employer_name')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Position</label>
                                    <input value={form.position} onChange={e => set('position', e.target.value)} placeholder="Job title" className={inputClass('position')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Monthly Income</label>
                                    <input type="number" min="0" value={form.monthly_income || ''} onChange={e => set('monthly_income', e.target.value)} placeholder="e.g. 25000" className={inputClass('monthly_income')} />
                                </div>
                            </div>
                        </div>
                    )}

                    {missing.has('beneficiaries') && (
                        <div className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="mb-1 text-lg font-bold text-gray-900 flex items-center gap-2"><i className="fas fa-users text-cyan-700"></i> Beneficiaries</h2>
                            <p className="mb-4 text-xs text-gray-500">Add at least one beneficiary to your account</p>
                            <div className="space-y-4">
                                {form.beneficiaries.length === 0 && (
                                    <button type="button" onClick={() => set('beneficiaries', [{ first_name: '', last_name: '', relationship: '' }])} className="text-sm font-medium text-cyan-700 hover:text-cyan-800">
                                        <i className="fas fa-plus mr-1"></i> Add a beneficiary
                                    </button>
                                )}
                                {form.beneficiaries.map((b: BeneficiaryRow, i: number) => (
                                    <div key={i} className="grid md:grid-cols-3 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <input value={b.first_name} onChange={e => { const arr = [...form.beneficiaries]; arr[i] = { ...arr[i], first_name: e.target.value }; set('beneficiaries', arr); }} placeholder="First name" className={inputClass('beneficiaries')} />
                                        <input value={b.last_name} onChange={e => { const arr = [...form.beneficiaries]; arr[i] = { ...arr[i], last_name: e.target.value }; set('beneficiaries', arr); }} placeholder="Last name" className={inputClass('beneficiaries')} />
                                        <div className="flex gap-2">
                                            <input value={b.relationship} onChange={e => { const arr = [...form.beneficiaries]; arr[i] = { ...arr[i], relationship: e.target.value }; set('beneficiaries', arr); }} placeholder="Relationship" className={inputClass('beneficiaries')} />
                                            <button type="button" onClick={() => set('beneficiaries', form.beneficiaries.filter((_: any, idx: number) => idx !== i))} className="shrink-0 px-2 text-gray-400 hover:text-red-600" title="Remove"><i className="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                ))}
                                {errors.beneficiaries && <p className="text-xs text-red-600">{errors.beneficiaries}</p>}
                            </div>
                        </div>
                    )}

                    {errors.form && (
                        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{errors.form}</div>
                    )}

                    <div className="flex justify-end gap-3 pb-10">
                        <button onClick={() => navigate('/logout')} className="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Log out</button>
                        <button
                            onClick={submit}
                            disabled={saveMutation.isPending}
                            className="inline-flex items-center rounded-lg bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {saveMutation.isPending ? <><i className="fas fa-spinner fa-spin mr-2"></i>Saving...</> : <><i className="fas fa-check mr-2"></i>Save & Continue</>}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}