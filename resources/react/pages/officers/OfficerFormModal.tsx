import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import DatePicker from '../../components/DatePicker';
import BranchSelect from '../../components/BranchSelect';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, officerSchema, officerEditSchema, officerStepSchemas, passwordStrength, onlyDigits, PERSON_TITLES, type FieldErrors } from '../../lib/validation';
import OtpVerifyField from '../../components/OtpVerifyField';

const PHONE_MAX = 11;
const POSTAL_MAX = 4;
const NAME_MAX = 100;

const steps = [
    { title: 'Personal Information', icon: 'fas fa-user' },
    { title: 'Contact Details', icon: 'fas fa-address-book' },
    { title: 'Role Assignment', icon: 'fas fa-shield-alt' },
    { title: 'Login Credentials', icon: 'fas fa-lock' },
];

const roleOptions = [
    { value: 'admin', label: 'Admin' },
    { value: 'chairman', label: 'Chairman' },
    { value: 'manager', label: 'Manager' },
    { value: 'accountant', label: 'Accountant' },
    { value: 'loan', label: 'Loan Officer' },
];

export default function OfficerFormModal({ officer, onClose }: { officer?: any; onClose: () => void }) {
    const isEdit = !!officer;
    const [step, setStep] = useState(0);
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const { user } = useAuth();
    const canAssignBranch = !!user?.permissions?.includes('branches.view');
    const { markDirty, requestClose, renderDialog } = useDiscardGuard();

    const [form, setForm] = useState({
        first_name: officer?.personal_data?.first_name || '',
        last_name: officer?.personal_data?.last_name || '',
        mid_name: officer?.personal_data?.mid_name || '',
        birth_date: officer?.personal_data?.birth_date || '',
        gender: officer?.personal_data?.gender || '',
        civil_status: officer?.personal_data?.civil_status || '',
        title: officer?.personal_data?.title || '',
        email: officer?.email || '',
        phone_number: officer?.contact?.phone_no || '',
        street: officer?.address?.street || '',
        barangay: officer?.address?.barangay || '',
        city: officer?.address?.city_municipality || '',
        postal_code: officer?.address?.zip_code || '',
        role: officer?.primary_role || '',
        branch_id: officer?.branch_id || '',
        password: '',
        password_confirmation: '',
    });
    const [profilePhoto, setProfilePhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState(officer?.personal_data?.profile_picture || '');
    
    // For new officers, email and phone must be verified via OTP.
    const [isEmailVerified, setIsEmailVerified] = useState(isEdit);
    const [isPhoneVerified, setIsPhoneVerified] = useState(isEdit);

    const saveMutation = useMutation({
        mutationFn: (payload: any) => {
            if (isEdit) {
                return api.put(`/api/members/${officer.id}`, payload);
            }
            return api.post('/api/members', payload);
        },
        onSuccess: (response) => {
            queryClient.invalidateQueries({ queryKey: ['officers'] });
            if (response.data?.status === 'pending') {
                addToast(isEdit
                    ? 'Profile saved. The role change is awaiting chairman approval; current access remains unchanged.'
                    : 'Account created inactive. Role assignment is awaiting chairman approval.', 'info');
            } else {
                addToast(isEdit ? 'Officer updated' : 'Officer created', 'success');
            }
            onClose();
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to save officer', 'error');
        },
    });

    const set = (key: string, value: string) => { markDirty(); setForm(f => ({ ...f, [key]: value })); };

    const [errors, setErrors] = useState<FieldErrors>({});

    const clearError = (key: string) => setErrors(prev => {
        const next = { ...prev };
        delete next[key];
        return next;
    });

    const setClearing = (key: string) => (v: string) => { set(key, v); clearError(key); };

    /** Digit-only fields: strip formatting characters so the OTP-verified value matches what the API stores. */
    const setDigits = (key: string, max: number) => (v: string) => { set(key, onlyDigits(v, max)); clearError(key); };

    const goNext = () => {
        const schema = isEdit && step === 3 ? null : officerStepSchemas[step];
        let errs: FieldErrors = {};
        if (schema) {
            errs = validate(schema, form);
        }

        if (step === 1 && !isEdit) {
            if (!isEmailVerified) errs.email = 'Email must be verified first.';
            if (!isPhoneVerified) errs.phone_number = 'Phone must be verified first.';
        }

        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        setStep(step + 1);
        setErrors({});
    };

    const goBack = () => {
        if (step > 0) setStep(step - 1);
        setErrors({});
    };

    const handleSubmit = () => {
        const payload: any = { ...form, gender: form.gender.toLowerCase(), civil_status: form.civil_status.toLowerCase() };
        const isPasswordChange = !!form.password;
        if (isEdit && !isPasswordChange) {
            delete payload.password;
            delete payload.password_confirmation;
        }
        const errs = validate(isEdit ? officerEditSchema : officerSchema, payload);
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        if (profilePhoto) {
            const fd = new FormData();
            Object.entries(payload).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) fd.append(k, String(v));
            });
            fd.append('profile_photo', profilePhoto);
            if (isEdit) fd.append('_method', 'PUT');
            saveMutation.mutate(fd);
            return;
        }
        saveMutation.mutate(payload);
    };

    const requestCloseGuard = () => requestClose(onClose);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={requestCloseGuard}>
            <div className="w-full max-w-2xl h-[90vh] rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">{isEdit ? 'Edit Officer' : 'Add Officer'}</h2>
                        <button onClick={requestCloseGuard} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white transition-colors"><i className="fas fa-times"></i></button>
                    </div>
                </div>

                <div className="p-6 flex-1 overflow-y-auto">
                    <div className="flex items-center mb-6">
                        {steps.map((s, i) => (
                            <div key={s.title} className="flex-1 flex items-center">
                                <div className="flex flex-col items-center">
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ${i <= step ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                        {i < step ? <i className="fas fa-check text-xs"></i> : i + 1}
                                    </div>
                                    <span className={`text-xs mt-1 ${i <= step ? 'text-emerald-600 font-medium' : 'text-gray-500'}`}>{s.title}</span>
                                </div>
                                {i < steps.length - 1 && <div className={`flex-1 h-0.5 mx-1 ${i < step ? 'bg-emerald-600' : 'bg-gray-200'}`}></div>}
                            </div>
                        ))}
                    </div>

                    <div className="space-y-4">
                        {step === 0 && (
                            <>
                                <h3 className="font-semibold text-gray-900 flex items-center gap-2"><i className="fas fa-user text-emerald-600"></i>Personal Information</h3>
                                <div className="flex items-center gap-5">
                                    <div className="h-20 w-20 shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-50 flex items-center justify-center">
                                        {photoPreview ? (
                                            <img src={photoPreview} className="h-full w-full object-cover" alt="Preview" />
                                        ) : (
                                            <i className="fas fa-user text-2xl text-gray-300"></i>
                                        )}
                                    </div>
                                    <div className="flex-1">
                                        <input type="file" accept="image/*" onChange={e => {
                                            const file = e.target.files?.[0];
                                            if (file) {
                                                markDirty();
                                                setProfilePhoto(file);
                                                setPhotoPreview(URL.createObjectURL(file));
                                            }
                                        }} className="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer" />
                                        <p className="mt-1 text-xs text-gray-400">JPG, PNG or GIF &middot; max 2MB</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">First Name *</label><input value={form.first_name} onChange={e => setClearing('first_name')(e.target.value)} maxLength={NAME_MAX} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.first_name ? 'border-red-400' : 'border-gray-300'}`} />{errors.first_name && <p className="mt-1 text-xs text-red-600">{errors.first_name}</p>}</div>
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">Middle Name</label><input value={form.mid_name} onChange={e => setClearing('mid_name')(e.target.value)} maxLength={NAME_MAX} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.mid_name ? 'border-red-400' : 'border-gray-300'}`} />{errors.mid_name && <p className="mt-1 text-xs text-red-600">{errors.mid_name}</p>}</div>
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">Last Name *</label><input value={form.last_name} onChange={e => setClearing('last_name')(e.target.value)} maxLength={NAME_MAX} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.last_name ? 'border-red-400' : 'border-gray-300'}`} />{errors.last_name && <p className="mt-1 text-xs text-red-600">{errors.last_name}</p>}</div>
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">Birth Date</label><DatePicker value={form.birth_date} onChange={v => { set('birth_date', v); clearError('birth_date'); }} fromYear={new Date().getFullYear() - 100} toYear={new Date().getFullYear()} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.birth_date ? 'border-red-400' : 'border-gray-300'}`} />{errors.birth_date && <p className="mt-1 text-xs text-red-600">{errors.birth_date}</p>}</div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Gender *</label>
                                        <select value={form.gender} onChange={e => { set('gender', e.target.value); clearError('gender'); }} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.gender ? 'border-red-400' : 'border-gray-300'}`}>
                                            <option value="">Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Prefer not to say</option>
                                        </select>
                                        {errors.gender && <p className="mt-1 text-xs text-red-600">{errors.gender}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Civil Status *</label>
                                        <select value={form.civil_status} onChange={e => { set('civil_status', e.target.value); clearError('civil_status'); }} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.civil_status ? 'border-red-400' : 'border-gray-300'}`}>
                                            <option value="">Select Status</option>
                                            <option value="single">Single</option>
                                            <option value="married">Married</option>
                                            <option value="widowed">Widowed</option>
                                            <option value="separated">Separated</option>
                                        </select>
                                        {errors.civil_status && <p className="mt-1 text-xs text-red-600">{errors.civil_status}</p>}
                                    </div>
<div className="col-span-2">
                                            <label className="block text-xs font-medium text-gray-500 mb-1">Title / Position</label>
                                            <select value={form.title} onChange={e => setClearing('title')(e.target.value)} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.title ? 'border-red-400' : 'border-gray-300'}`}>
                                                <option value="">Select Title</option>
                                                {PERSON_TITLES.map(t => <option key={t} value={t}>{t}</option>)}
                                            </select>
                                            {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                                        </div>
                                </div>
                            </>
                        )}

                        {step === 1 && (
                            <>
                                <h3 className="font-semibold text-gray-900 flex items-center gap-2"><i className="fas fa-address-book text-emerald-600"></i>Contact Details</h3>
                                <div className="grid grid-cols-2 gap-4">
                                    {isEdit ? (
                                        <>
                                            <div><label className="block text-xs font-medium text-gray-500 mb-1">Email *</label><input type="email" value={form.email} onChange={e => { set('email', e.target.value); clearError('email'); }} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.email ? 'border-red-400' : 'border-gray-300'}`} disabled />{errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}</div>
                                            <div><label className="block text-xs font-medium text-gray-500 mb-1">Phone Number *</label><input value={form.phone_number} onChange={e => setDigits('phone_number', PHONE_MAX)(e.target.value)} maxLength={PHONE_MAX} inputMode="tel" className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.phone_number ? 'border-red-400' : 'border-gray-300'}`} />{errors.phone_number && <p className="mt-1 text-xs text-red-600">{errors.phone_number}</p>}</div>
                                        </>
                                    ) : (
                                        <>
                                            <OtpVerifyField
                                                label="Email *"
                                                value={form.email}
                                                onChange={v => { setClearing('email')(v); setIsEmailVerified(false); }}
                                                type="email"
                                                error={errors.email}
                                                isVerified={isEmailVerified}
                                                onSendOtp={async (email) => {
                                                    const res = await api.post('/api/officers/verify-email/send', { email });
                                                    return res.data;
                                                }}
                                                onVerifyOtp={async (email, otp) => {
                                                    const res = await api.post('/api/officers/verify-email', { email, otp_code: otp });
                                                    if (res.data.message === 'Email verified successfully.') {
                                                        setIsEmailVerified(true);
                                                    }
                                                    return { success: true, message: res.data.message };
                                                }}
                                            />
                                            <OtpVerifyField
                                                label="Phone Number *"
                                                value={form.phone_number}
                                                onChange={v => { setDigits('phone_number', PHONE_MAX)(v); setIsPhoneVerified(false); }}
                                                type="tel"
                                                error={errors.phone_number}
                                                isVerified={isPhoneVerified}
                                                onSendOtp={async (phone) => {
                                                    const res = await api.post('/api/officers/verify-phone/send', { phone_no: phone });
                                                    return res.data;
                                                }}
                                                onVerifyOtp={async (phone, otp) => {
                                                    const res = await api.post('/api/officers/verify-phone', { phone_no: phone, otp_code: otp });
                                                    if (res.data.message === 'Phone verified successfully.') {
                                                        setIsPhoneVerified(true);
                                                    }
                                                    return { success: true, message: res.data.message };
                                                }}
                                            />
                                        </>
                                    )}
                                    <div className="col-span-2"><label className="block text-xs font-medium text-gray-500 mb-1">Street</label><input value={form.street} onChange={e => setClearing('street')(e.target.value)} maxLength={255} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.street ? 'border-red-400' : 'border-gray-300'}`} />{errors.street && <p className="mt-1 text-xs text-red-600">{errors.street}</p>}</div>
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">Barangay</label><input value={form.barangay} onChange={e => setClearing('barangay')(e.target.value)} maxLength={100} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.barangay ? 'border-red-400' : 'border-gray-300'}`} />{errors.barangay && <p className="mt-1 text-xs text-red-600">{errors.barangay}</p>}</div>
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">City</label><input value={form.city} onChange={e => setClearing('city')(e.target.value)} maxLength={100} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.city ? 'border-red-400' : 'border-gray-300'}`} />{errors.city && <p className="mt-1 text-xs text-red-600">{errors.city}</p>}</div>
                                    <div><label className="block text-xs font-medium text-gray-500 mb-1">Postal Code</label><input value={form.postal_code} onChange={e => setDigits('postal_code', POSTAL_MAX)(e.target.value)} maxLength={POSTAL_MAX} inputMode="numeric" className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.postal_code ? 'border-red-400' : 'border-gray-300'}`} />{errors.postal_code && <p className="mt-1 text-xs text-red-600">{errors.postal_code}</p>}</div>
                                </div>
                            </>
                        )}

                        {step === 2 && (
                            <>
                                <h3 className="font-semibold text-gray-900 flex items-center gap-2"><i className="fas fa-shield-alt text-emerald-600"></i>Role Assignment</h3>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="max-w-xs">
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Role *</label>
                                        <select value={form.role} onChange={e => { set('role', e.target.value); clearError('role'); }} className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.role ? 'border-red-400' : 'border-gray-300'}`}>
                                            <option value="">Select Role</option>
                                            {roleOptions.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                                        </select>
                                        {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
                                    </div>
                                    {canAssignBranch && (
                                        <div className="max-w-xs">
                                            <BranchSelect value={form.branch_id} onChange={v => { set('branch_id', String(v)); clearError('branch_id'); }} error={errors.branch_id} />
                                        </div>
                                    )}
                                </div>
                            </>
                        )}

                        {step === 3 && (
                            <>
                                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                                    <i className="fas fa-lock text-emerald-600"></i>
                                    {isEdit ? 'Change Password (optional)' : 'Login Credentials'}
                                </h3>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">
                                            Password {!isEdit && '*'}
                                        </label>
                                        <input
                                            type="password"
                                            value={form.password}
                                            onChange={e => { set('password', e.target.value); clearError('password'); clearError('password_confirmation'); }}
                                            className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.password ? 'border-red-400' : 'border-gray-300'}`}
                                            placeholder={isEdit ? 'Leave blank to keep current' : 'Min 8 characters'}
                                        />
                                        {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-500 mb-1">
                                            Confirm Password {!isEdit && '*'}
                                        </label>
                                        <input
                                            type="password"
                                            value={form.password_confirmation}
                                            onChange={e => { set('password_confirmation', e.target.value); clearError('password_confirmation'); }}
                                            className={`w-full rounded-lg border p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 ${errors.password_confirmation ? 'border-red-400' : 'border-gray-300'}`}
                                            placeholder="Re-enter password"
                                        />
                                        {errors.password_confirmation && <p className="mt-1 text-xs text-red-600">{errors.password_confirmation}</p>}
                                    </div>
                                </div>
                                {!isEdit && form.password && (
                                    <div>
                                        <div className="flex items-center gap-2">
                                            {[1, 2, 3, 4].map(i => (
                                                <div key={i} className={`h-1.5 flex-1 rounded-full ${i <= passwordStrength(form.password).score ? 'bg-emerald-500' : 'bg-gray-200'}`} />
                                            ))}
                                            <span className="text-xs font-medium text-gray-600">{passwordStrength(form.password).label}</span>
                                        </div>
                                        <ul className="mt-2 grid grid-cols-2 gap-1">
                                            {passwordStrength(form.password).checks.map(c => (
                                                <li key={c.label} className={`flex items-center gap-1 text-xs ${c.ok ? 'text-emerald-600' : 'text-gray-400'}`}>
                                                    <i className={`fas fa-${c.ok ? 'check' : 'times'} text-[10px]`}></i> {c.label}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>

                <div className="flex shrink-0 items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                    <button onClick={() => step > 0 ? goBack() : requestCloseGuard()} className="px-4 py-2.5 text-sm font-medium border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        {step === 0 ? 'Cancel' : 'Back'}
                    </button>
                    {step < 3 ? (
                        <button onClick={goNext} className="px-4 py-2.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 disabled:opacity-50 transition-colors">
                            Next <i className="fas fa-arrow-right ml-1"></i>
                        </button>
                    ) : (
                        <button onClick={handleSubmit} disabled={saveMutation.isPending} className="px-4 py-2.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 disabled:opacity-50 transition-colors">
                            {saveMutation.isPending ? 'Saving...' : isEdit ? 'Update Officer' : 'Create Officer'}
                            <i className="fas fa-check ml-1"></i>
                        </button>
                    )}
                </div>
            </div>
            {renderDialog()}
        </div>
    );
}
