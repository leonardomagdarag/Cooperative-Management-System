import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import DatePicker from '../../components/DatePicker';
import BranchSelect from '../../components/BranchSelect';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, memberStepSchemas, memberEditStepSchemas, memberSchema, memberEditSchema, passwordStrength, onlyDigits, formatGroupedDigits, EMPLOYMENT_TYPES, type FieldErrors } from '../../lib/validation';

const STEPS = ['personal', 'address', 'contact', 'ids', 'employment', 'login', 'beneficiaries', 'agreement', 'review'];

const BIRTH_FROM_YEAR = new Date().getFullYear() - 100;
const BIRTH_TO_YEAR = new Date().getFullYear();

const STEP_LABELS: Record<string, string> = {
    personal: 'Personal Info',
    address: 'Address',
    contact: 'Contact Info',
    ids: "Gov't IDs",
    employment: 'Employment',
    login: 'Login Info',
    beneficiaries: 'Beneficiaries',
    agreement: 'Agreement',
    review: 'Review',
};

const FILE_FIELDS = [
    'profile_photo',
    'national_id_photo', 'tin_photo', 'sss_photo', 'philhealth_photo', 'barangay_id_photo',
    'agreement_file',
];

const ID_CARDS: { key: string; label: string; photo: string; placeholder: string; groups?: number[]; maxLength?: number; helperText?: string }[] = [
    { key: 'national_id', label: 'National ID', photo: 'national_id_photo', placeholder: 'XXXX-XXXX-XXXX-XXXX', groups: [4, 4, 4, 4], maxLength: 19, helperText: '16 digits' },
    { key: 'tin', label: 'TIN', photo: 'tin_photo', placeholder: 'XXX-XXX-XXX-XXX', groups: [3, 3, 3, 3], maxLength: 15, helperText: '12 digits' },
    { key: 'sss', label: 'SSS', photo: 'sss_photo', placeholder: 'XX-XXXXXXX-X', groups: [2, 7, 1], maxLength: 11, helperText: '10 digits' },
    { key: 'philhealth', label: 'PhilHealth', photo: 'philhealth_photo', placeholder: 'XX-XXXXXXXXX-X', groups: [2, 9, 1], maxLength: 13, helperText: '12 digits' },
    { key: 'barangay_id', label: 'Barangay ID', photo: 'barangay_id_photo', placeholder: 'Enter barangay ID', maxLength: 64, helperText: 'Letters, numbers and hyphens only' },
];

const PHONE_MAX = 11;
const POSTAL_MAX = 4;
const NAME_MAX = 100;

const emptyForm = {
    first_name: '', mid_name: '', last_name: '', suffix: '', birth_date: '', birthplace: '',
    gender: '', civil_status: '', religion: '', title: 'Member',
    country: 'Philippines', region: '', province: '', city: '', barangay: '', street: '', postal_code: '',
    email: '', phone_number: '', facebook_link: '',
    national_id: '', tin: '', sss: '', philhealth: '', barangay_id: '',
    employment_type: '', employer_name: '', position: '', monthly_income: '',
    password: '', password_confirmation: '',
    branch_id: '' as number | '',
    agreement: false,
    beneficiaries: [] as any[],
    profile_photo: null as File | null,
    national_id_photo: null as File | null,
    tin_photo: null as File | null,
    sss_photo: null as File | null,
    philhealth_photo: null as File | null,
    barangay_id_photo: null as File | null,
    agreement_file: null as File | null,
};

export default function MemberWizardForm({ member, onClose }: { member: any; onClose: () => void }) {
    const isEdit = !!member;
    const [step, setStep] = useState(0);
    const [form, setForm] = useState(emptyForm);
    const [previews, setPreviews] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<FieldErrors>({});
    const { user } = useAuth();
    const canAssignBranch = !!user?.permissions?.includes('branches.view');
    const { markDirty, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data: detailData, isLoading: detailLoading } = useQuery({
        queryKey: ['member-detail', member?.id],
        queryFn: () => api.get(`/api/members/${member?.id}`),
        enabled: isEdit,
    });

    useEffect(() => {
        if (!isEdit || !detailData?.data) return;
        const d = detailData.data;
        setForm({
            ...emptyForm,
            first_name: d.personal_data?.first_name ?? '',
            mid_name: d.personal_data?.mid_name ?? '',
            last_name: d.personal_data?.last_name ?? '',
            suffix: d.personal_data?.suffix ?? '',
            birth_date: d.personal_data?.birth_date ?? '',
            birthplace: d.personal_data?.birthplace ?? '',
            gender: d.personal_data?.gender ?? '',
            civil_status: d.personal_data?.civil_status ?? '',
            religion: d.personal_data?.religion ?? '',
            title: d.personal_data?.title ?? '',
            country: d.address?.country ?? 'Philippines',
            region: d.address?.region ?? '',
            province: d.address?.province ?? '',
            city: d.address?.city ?? '',
            barangay: d.address?.barangay ?? '',
            street: d.address?.street ?? '',
            postal_code: d.address?.postal_code ?? '',
            email: d.email ?? '',
            phone_number: d.contact?.phone_no ?? '',
            facebook_link: d.contact?.facebook ?? '',
            national_id: d.member_ids?.national_id ?? '',
            tin: d.member_ids?.tin ?? '',
            sss: d.member_ids?.sss ?? '',
            philhealth: d.member_ids?.philhealth ?? '',
            barangay_id: d.member_ids?.barangay_id ?? '',
            employment_type: d.employment?.employment_type ?? '',
            employer_name: d.employment?.company ?? '',
            position: d.employment?.position ?? '',
            monthly_income: d.employment?.monthly_income ?? '',
            branch_id: d.branch_id ?? '',
            beneficiaries: (d.beneficiaries ?? []).map((b: any) => ({
                first_name: b.first_name ?? '',
                last_name: b.last_name ?? '',
                relationship: b.relationship ?? '',
            })),
        });
        setPreviews({
            profile_photo: d.personal_data?.profile_picture ?? '',
            national_id_photo: d.member_ids?.national_id_photo ?? '',
            tin_photo: d.member_ids?.tin_photo ?? '',
            sss_photo: d.member_ids?.sss_photo ?? '',
            philhealth_photo: d.member_ids?.philhealth_photo ?? '',
            barangay_id_photo: d.member_ids?.barangay_id_photo ?? '',
        });
    }, [isEdit, detailData]);

    const buildFormData = () => {
        const fd = new FormData();
        Object.entries(form).forEach(([k, v]) => {
            if (k === 'beneficiaries') {
                fd.append(k, JSON.stringify(v));
                return;
            }
            if (FILE_FIELDS.includes(k) && v instanceof File) {
                fd.append(k, v);
                return;
            }
            if (v !== '' && v !== null && v !== undefined && typeof v !== 'boolean') {
                fd.append(k, String(v));
            }
        });
        if (form.agreement) fd.append('agreement', '1');
        return fd;
    };

    const saveMutation = useMutation({
        mutationFn: async (payload: any) => {
            const hasFiles = FILE_FIELDS.some(f => form[f as keyof typeof form] instanceof File);
            if (hasFiles) {
                const fd = buildFormData();
                if (isEdit) fd.append('_method', 'PUT');
                return api.post(isEdit ? `/api/members/${member.id}` : '/api/members', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            }
            return isEdit ? api.put(`/api/members/${member.id}`, payload) : api.post('/api/members', payload);
        },
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast(isEdit ? 'Member updated' : 'Member created', 'success'); onClose(); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save', 'error'),
    });

    const set = (key: string, value: any) => {
        markDirty();
        setForm(f => ({ ...f, [key]: value }));
    };
    const setFile = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        markDirty();
        setForm(f => ({ ...f, [key]: file }));
        if (file) {
            const reader = new FileReader();
            reader.onload = ev => setPreviews(p => ({ ...p, [key]: String(ev.target?.result) }));
            reader.readAsDataURL(file);
        } else {
            setPreviews(p => { const next = { ...p }; delete next[key]; return next; });
        }
    };
    const currentStep = STEPS[step];

    const validateStep = (s: string) => {
        const schema = isEdit ? memberEditStepSchemas[s] : memberStepSchemas[s];
        if (!schema) return true;
        const errs = validate(schema, form);
        setErrors(errs);
        return Object.keys(errs).length === 0;
    };

    const goNext = () => {
        if (!validateStep(currentStep)) return;
        setStep(step + 1);
        setErrors({});
    };

    const clearError = (key: string) => setErrors(prev => {
        const next = { ...prev };
        delete next[key];
        return next;
    });

    /** Digit-only fields (phone, postal code): strip everything else as the operator types. */
    const setDigits = (key: string, max: number) => (raw: string) => {
        set(key, onlyDigits(raw, max));
        clearError(key);
    };

    /** Grouped Philippine IDs: keep digits only, re-insert the canonical dashes. */
    const setGroupedId = (key: string, groups: number[]) => (raw: string) => {
        set(key, formatGroupedDigits(raw, groups));
        clearError(key);
    };

    const setClearing = (key: string) => (raw: string) => {
        set(key, raw);
        clearError(key);
    };

    const goBack = () => {
        if (step > 0) setStep(step - 1);
        setErrors({});
    };

    const requestCloseGuard = () => requestClose(onClose);

    const handleSubmit = () => {
        const errs = validate(isEdit ? memberEditSchema : memberSchema, form);
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            return;
        }
        saveMutation.mutate(form);
    };

    const isStepDone = (i: number) => i < step;
    const isStepCurrent = (i: number) => i === step;
    const agreementFile = form.agreement_file;
    const agreementIsImage = agreementFile instanceof File && agreementFile.type.startsWith('image/');

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm overflow-hidden px-4 sm:px-6" onClick={requestCloseGuard}>
            <div className="relative w-full max-w-5xl h-[90vh] mx-auto flex flex-col overflow-hidden rounded-lg bg-white shadow-xl" onClick={e => e.stopPropagation()}>
                {/* Header */}
                <div className="flex items-start justify-between p-6 border-b border-gray-200 rounded-t bg-linear-to-r from-cyan-500 to-emerald-500">
                    <div>
                        <h3 className="text-2xl font-bold text-white">{isEdit ? 'Edit Member' : 'Add New Member'}</h3>
                        <p className="text-sm text-cyan-50 mt-1">{isEdit ? 'Update member information' : 'Complete all required fields'}</p>
                    </div>
                    <button type="button" onClick={requestCloseGuard} className="text-white hover:text-cyan-100 hover:bg-white/10 rounded-lg text-sm p-2 transition-colors" aria-label="Close">
                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                    </button>
                </div>

                {/* Required fields notice */}
                <div className="px-6 py-3 bg-blue-50 border-b border-blue-100">
                    <p className="text-sm text-blue-700">
                        <svg className="inline-block w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                        Fields marked with <span className="text-red-500 font-semibold">*</span> are required
                    </p>
                </div>

                {/* Stepper */}
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50 overflow-x-auto">
                    <ol className="flex min-w-full items-center gap-0">
                        {STEPS.map((s, i) => (
                            <li key={s} className={`flex items-center ${i === STEPS.length - 1 ? '' : 'flex-1'}`}>
                                <div className="flex flex-col items-center">
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors ${isStepCurrent(i) ? 'bg-cyan-500 text-white ring-2 ring-cyan-300' : isStepDone(i) ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                        {isStepDone(i) ? (
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        ) : i + 1}
                                    </div>
                                    <span className={`mt-1 text-xs sm:text-sm whitespace-nowrap transition-colors ${isStepCurrent(i) ? 'text-cyan-600 font-semibold' : 'text-gray-500'}`}>{STEP_LABELS[s]}</span>
                                </div>
                                {i < STEPS.length - 1 && (
                                    <div className={`flex-1 h-0.5 mx-1 -mt-3.5 ${isStepDone(i) ? 'bg-emerald-400' : 'bg-gray-200'}`} />
                                )}
                            </li>
                        ))}
                    </ol>
                </div>

                {/* Body */}
                <div className="p-6 overflow-y-auto flex-1">
                    {isEdit && detailLoading && (
                        <div className="flex h-full min-h-[300px] items-center justify-center text-sm text-gray-500">
                            <i className="fas fa-spinner fa-spin mr-2"></i> Loading member data...
                        </div>
                    )}
                    {!(isEdit && detailLoading) && currentStep === 'personal' && (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Field label="First Name" value={form.first_name} onChange={setClearing('first_name')} required error={errors.first_name} maxLength={NAME_MAX} helperText="Legal first name as it appears on ID" />
                                <Field label="Middle Name" value={form.mid_name} onChange={setClearing('mid_name')} error={errors.mid_name} maxLength={NAME_MAX} />
                                <Field label="Last Name" value={form.last_name} onChange={setClearing('last_name')} required error={errors.last_name} maxLength={NAME_MAX} />
                                <Field label="Suffix" value={form.suffix} onChange={setClearing('suffix')} error={errors.suffix} maxLength={50} placeholder="Jr., Sr., III, etc." />
                                <Field label="Date of Birth" type="date" value={form.birth_date} onChange={setClearing('birth_date')} required error={errors.birth_date} helperText="Must be at least 18 years old" />
                                <Field label="Place of Birth" value={form.birthplace} onChange={setClearing('birthplace')} error={errors.birthplace} maxLength={255} placeholder="City, Province" />
                                <Select label="Gender" value={form.gender} onChange={setClearing('gender')} options={['male', 'female', 'other']} required error={errors.gender} />
                                <Select label="Civil Status" value={form.civil_status} onChange={setClearing('civil_status')} options={['single', 'married', 'widowed', 'separated']} required error={errors.civil_status} />
                                <Field label="Religion" value={form.religion} onChange={setClearing('religion')} error={errors.religion} maxLength={64} />
                            </div>

                            {/* Profile photo upload with preview */}
                            <div className="mt-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Profile Photo <span className="text-gray-400 text-xs">(optional)</span></label>
                                <div className="flex items-center gap-4">
                                    <div className="w-20 h-20 rounded-full bg-gray-100 border border-gray-300 overflow-hidden flex items-center justify-center shrink-0">
                                        {previews.profile_photo ? (
                                            <img src={previews.profile_photo} className="w-full h-full object-cover" alt="Preview" />
                                        ) : (
                                            <span className="text-gray-400"><i className="fas fa-user text-2xl"></i></span>
                                        )}
                                    </div>
                                    <div className="flex-1">
                                        <input type="file" accept="image/*" onChange={setFile('profile_photo')} className="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer" />
                                        <p className="mt-1 text-xs text-gray-400">JPG, PNG or GIF &middot; max 2MB</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {currentStep === 'address' && (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Field label="Street Address" value={form.street} onChange={setClearing('street')} error={errors.street} maxLength={255} placeholder="House No., Street Name" />
                                <Field label="Barangay" value={form.barangay} onChange={setClearing('barangay')} error={errors.barangay} maxLength={100} />
                                <Field label="City/Municipality" value={form.city} onChange={setClearing('city')} error={errors.city} maxLength={100} />
                                <Field label="Province" value={form.province} onChange={setClearing('province')} error={errors.province} maxLength={100} />
                                <Field label="Region" value={form.region} onChange={setClearing('region')} error={errors.region} maxLength={100} placeholder="e.g., Region IV-A" />
                                <Field label="Postal/ZIP Code" value={form.postal_code} onChange={setDigits('postal_code', POSTAL_MAX)} error={errors.postal_code} maxLength={POSTAL_MAX} inputMode="numeric" helperText="4 digits" />
                                <Field label="Country" value={form.country} onChange={setClearing('country')} error={errors.country} maxLength={100} placeholder="Philippines" />
                            </div>
                        </div>
                    )}

                    {currentStep === 'contact' && (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Field label="Email Address" type="email" value={form.email} onChange={setClearing('email')} required error={errors.email} placeholder="email@example.com" helperText="Primary contact email" />
                                <Field label="Phone Number" value={form.phone_number} onChange={setDigits('phone_number', PHONE_MAX)} required error={errors.phone_number} maxLength={PHONE_MAX} inputMode="tel" placeholder="09XXXXXXXXX" helperText="10 or 11 digits, numbers only" />
                                <div className="md:col-span-3">
                                    <Field label="Facebook Profile" type="url" value={form.facebook_link} onChange={setClearing('facebook_link')} error={errors.facebook_link} maxLength={255} placeholder="https://facebook.com/username" helperText="Optional: Your Facebook profile URL" />
                                </div>
                            </div>
                        </div>
                    )}

                    {currentStep === 'ids' && (
                        <div className="space-y-3">
                            {ID_CARDS.map(card => (
                                <div key={card.key} className="grid grid-cols-2 gap-4 items-start p-3 rounded-xl bg-gray-50 border border-gray-100">
                                    <div>
                                        <Field label={`${card.label} Number`} value={form[card.key as keyof typeof form] as string} onChange={card.groups ? setGroupedId(card.key, card.groups) : setClearing(card.key)} error={errors[card.key]} maxLength={card.maxLength} inputMode={card.groups ? 'numeric' : undefined} placeholder={card.placeholder} helperText={card.helperText} />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">{card.label} Photo</label>
                                        <div className="flex items-center gap-3">
                                            <div className="w-24 h-16 rounded-lg bg-white border border-gray-300 overflow-hidden flex items-center justify-center shrink-0">
                                                {previews[card.photo] ? (
                                                    <img src={previews[card.photo]} className="w-full h-full object-cover" alt="Preview" />
                                                ) : (
                                                    <span className="text-gray-300 text-xs text-center"><i className="fas fa-id-card text-xl"></i></span>
                                                )}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <input type="file" accept="image/*" onChange={setFile(card.photo)} className="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-medium file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {currentStep === 'employment' && (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Select label="Employment Type" value={form.employment_type} onChange={setClearing('employment_type')} options={EMPLOYMENT_TYPES} error={errors.employment_type} />
                                <Field label="Company/Employer Name" value={form.employer_name} onChange={setClearing('employer_name')} error={errors.employer_name} maxLength={255} />
                                <Field label="Position" value={form.position} onChange={setClearing('position')} error={errors.position} maxLength={100} />
                                <Field label="Monthly Income" type="number" value={form.monthly_income} onChange={setClearing('monthly_income')} error={errors.monthly_income} inputMode="decimal" min="0" max="9999999999" placeholder="0.00" />
                                {canAssignBranch && (
                                    <BranchSelect value={form.branch_id} onChange={v => { set('branch_id', v); clearError('branch_id'); }} error={errors.branch_id} />
                                )}
                            </div>
                        </div>
                    )}

                    {currentStep === 'login' && !isEdit && (
                        <div className="space-y-6">
                            <div className="rounded-xl border border-cyan-100 bg-linear-to-r from-cyan-50 to-emerald-50 p-4">
                                <div className="flex items-start gap-3">
                                    <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-700">
                                        <i className="fas fa-info"></i>
                                    </div>
                                    <div className="text-sm text-gray-700">
                                        <p className="font-semibold text-gray-900">Password guidance</p>
                                        <p className="mt-1 leading-6">Must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number.</p>
                                    </div>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <Field label="Password" type="password" value={form.password} onChange={v => { set('password', v); clearError('password'); clearError('password_confirmation'); }} required error={errors.password} placeholder="Enter password" helperText="Re-enter the same password to continue." />
                                    {form.password && (
                                        <div className="mt-3 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                                            <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">Strength:</span>
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                                <div className="h-full rounded-full bg-emerald-500 transition-all duration-200" style={{ width: `${passwordStrength(form.password).score * 25}%` }} />
                                            </div>
                                            <span className="min-w-16 text-sm font-semibold text-gray-700">{passwordStrength(form.password).label}</span>
                                        </div>
                                    )}
                                </div>
                                <Field label="Confirm Password" type="password" value={form.password_confirmation} onChange={v => { set('password_confirmation', v); clearError('password_confirmation'); }} required error={errors.password_confirmation} placeholder="Re-enter password" />
                            </div>
                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <div className="flex items-start gap-3">
                                    <i className="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                                    <div className="text-sm text-amber-800">
                                        <p className="font-medium">Initial Login Credentials</p>
                                        <p className="mt-1">The member will use the email and password set here to log into the system.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {currentStep === 'beneficiaries' && (
                        <div className="space-y-6">
                            <div className="flex items-center justify-between">
                                <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Beneficiaries</h4>
                                <button type="button" onClick={() => set('beneficiaries', [...form.beneficiaries, { first_name: '', last_name: '', relationship: '' }])} disabled={form.beneficiaries.length >= 5} className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-cyan-700 hover:border-cyan-300 hover:bg-cyan-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:border-gray-200 disabled:hover:bg-gray-50"><i className="fas fa-plus text-xs"></i> Add Beneficiary</button>
                            </div>
                            {form.beneficiaries.length === 0 && <p className="text-sm text-gray-500">No beneficiaries added.</p>}
                            {form.beneficiaries.map((b: any, i: number) => (
                                <div key={i} className="flex items-end gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <div className="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
                                        <div>
                                            <input placeholder="First Name" value={b.first_name} onChange={e => { const list = [...form.beneficiaries]; list[i].first_name = e.target.value; set('beneficiaries', list); clearError(`beneficiaries.${i}.first_name`); }} className={`w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm ${errors[`beneficiaries.${i}.first_name`] ? 'border-red-400' : ''}`} />
                                            {errors[`beneficiaries.${i}.first_name`] && <p className="mt-1 text-xs text-red-600">{errors[`beneficiaries.${i}.first_name`]}</p>}
                                        </div>
                                        <div>
                                            <input placeholder="Last Name" value={b.last_name} onChange={e => { const list = [...form.beneficiaries]; list[i].last_name = e.target.value; set('beneficiaries', list); clearError(`beneficiaries.${i}.last_name`); }} className={`w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm ${errors[`beneficiaries.${i}.last_name`] ? 'border-red-400' : ''}`} />
                                            {errors[`beneficiaries.${i}.last_name`] && <p className="mt-1 text-xs text-red-600">{errors[`beneficiaries.${i}.last_name`]}</p>}
                                        </div>
                                        <div>
                                            <input placeholder="Relationship" value={b.relationship} onChange={e => { const list = [...form.beneficiaries]; list[i].relationship = e.target.value; set('beneficiaries', list); clearError(`beneficiaries.${i}.relationship`); }} className={`w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm ${errors[`beneficiaries.${i}.relationship`] ? 'border-red-400' : ''}`} />
                                            {errors[`beneficiaries.${i}.relationship`] && <p className="mt-1 text-xs text-red-600">{errors[`beneficiaries.${i}.relationship`]}</p>}
                                        </div>
                                    </div>
                                    <button type="button" onClick={() => { const list = [...form.beneficiaries]; list.splice(i, 1); set('beneficiaries', list); }} className="text-red-400 hover:text-red-600 mb-2"><i className="fas fa-times"></i></button>
                                </div>
                            ))}
                        </div>
                    )}

                    {currentStep === 'agreement' && (
                        <div className="space-y-6">
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
                                <i className="fas fa-info-circle text-blue-600 mt-0.5"></i>
                                <div className="text-sm text-blue-800">
                                    <p className="font-semibold">Terms, Privacy &amp; Agreement</p>
                                    <p className="mt-0.5">Please read and accept the terms and conditions before proceeding. You may also upload a signed membership agreement or any supporting document.</p>
                                </div>
                            </div>

                            <div>
                                <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <i className="fas fa-file-contract"></i> Review the policy documents
                                </h4>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <button type="button" className="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700">
                                        <i className="fas fa-file-contract"></i> View Terms and Agreement
                                    </button>
                                    <button type="button" className="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700">
                                        <i className="fas fa-shield-halved"></i> View Privacy Policy
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="flex items-start gap-3 cursor-pointer rounded-lg border border-gray-200 bg-white p-4 hover:bg-gray-50 transition-colors">
                                    <input type="checkbox" checked={form.agreement} onChange={e => { set('agreement', e.target.checked); clearError('agreement'); }} className="mt-0.5 h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer" />
                                    <span className="text-sm text-gray-700 leading-5">
                                        I have reviewed the cooperative's <span className="font-semibold text-cyan-700 underline underline-offset-2">Terms and Agreement</span> and <span className="font-semibold text-cyan-700 underline underline-offset-2">Privacy Policy</span>. I voluntarily agree to be bound by these terms. <span className="text-red-500">*</span>
                                    </span>
                                </label>
                                {errors.agreement && <p className="mt-1.5 text-xs text-red-600 flex items-center gap-1"><i className="fas fa-exclamation-circle"></i> {errors.agreement}</p>}
                            </div>

                            <div>
                                <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <i className="fas fa-upload"></i> Upload Agreement / Supporting Document <span className="text-gray-400 font-normal normal-case text-xs">(optional)</span>
                                </h4>
                                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <input type="file" accept="image/jpeg,image/png,application/pdf" onChange={setFile('agreement_file')} className="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer" />
                                    <p className="mt-2 text-xs text-gray-500">Select a JPG, PNG, or PDF file up to 10 MB.</p>
                                    {agreementFile && (
                                        <div className="mt-4 flex items-start gap-4">
                                            <div className="w-20 h-20 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-white flex items-center justify-center">
                                                {agreementIsImage && previews.agreement_file ? (
                                                    <img src={previews.agreement_file} className="h-full w-full object-cover" alt="Preview" />
                                                ) : (
                                                    <span className="flex items-center justify-center h-14 w-14 rounded-lg border border-red-100 bg-red-50 text-red-500"><i className="fas fa-file-pdf text-2xl"></i></span>
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-gray-900">{agreementFile.name}</p>
                                                <p className="mt-0.5 flex items-center gap-1 text-xs text-emerald-600"><i className="fas fa-check-circle"></i> File selected</p>
                                                <button type="button" onClick={() => { setForm(f => ({ ...f, agreement_file: null })); setPreviews(p => { const next = { ...p }; delete next.agreement_file; return next; }); }} className="mt-1 text-xs text-red-500 transition-colors hover:text-red-700">Remove file</button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {currentStep === 'review' && (
                        <div className="space-y-6">
                            <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-start gap-3">
                                <i className="fas fa-check-circle text-emerald-600 mt-0.5"></i>
                                <div className="text-sm text-emerald-800">
                                    <p className="font-semibold">Review before creating</p>
                                    <p className="mt-0.5">Please verify the information below. Click <strong>Previous</strong> to go back and make changes, or <strong>Create Member</strong> when everything looks correct.</p>
                                </div>
                            </div>

                            <ReviewSection icon="fas fa-user" title="Personal Information" rows={[
                                ['First Name', form.first_name], ['Middle Name', form.mid_name], ['Last Name', form.last_name], ['Suffix', form.suffix],
                                ['Date of Birth', form.birth_date], ['Birthplace', form.birthplace], ['Gender', form.gender], ['Civil Status', form.civil_status], ['Religion', form.religion],
                            ]} />
                            <ReviewSection icon="fas fa-map-marker-alt" title="Address" rows={[
                                ['Street', form.street], ['Barangay', form.barangay], ['City/Municipality', form.city], ['Province', form.province], ['Region', form.region], ['Postal Code', form.postal_code], ['Country', form.country],
                            ]} />
                            <ReviewSection icon="fas fa-envelope" title="Contact Information" rows={[
                                ['Email', form.email], ['Phone Number', form.phone_number], ['Facebook', form.facebook_link],
                            ]} />
                            <ReviewSection icon="fas fa-id-card" title="Government IDs" rows={[
                                ['National ID', form.national_id], ['TIN', form.tin], ['SSS', form.sss], ['PhilHealth', form.philhealth], ['Barangay ID', form.barangay_id],
                            ]} />
                            <ReviewSection icon="fas fa-briefcase" title="Employment" rows={[
                                ['Type', form.employment_type], ['Employer', form.employer_name], ['Position', form.position], ['Monthly Income', form.monthly_income ? `₱${Number(form.monthly_income).toLocaleString()}` : ''],
                            ]} />
                            {!isEdit && (
                                <ReviewSection icon="fas fa-lock" title="Login Credentials" rows={[
                                    ['Email (login)', form.email], ['Password', form.password ? '•'.repeat(Math.min(form.password.length, 12)) : ''],
                                ]} />
                            )}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50 rounded-b">
                    <div className="flex gap-2">
                        {step > 0 && (
                            <button type="button" onClick={goBack} className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i className="fas fa-arrow-left mr-1"></i> Previous
                            </button>
                        )}
                        {step === 0 && (
                            <button type="button" onClick={requestCloseGuard} className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        {step < STEPS.length - 1 ? (
                            <button type="button" onClick={goNext} className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-linear-to-r from-cyan-500 to-emerald-500 rounded-lg hover:from-cyan-600 hover:to-emerald-600 transition-all">
                                Next <i className="fas fa-arrow-right ml-1"></i>
                            </button>
                        ) : (
                            <button type="button" onClick={handleSubmit} disabled={saveMutation.isPending} className="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-linear-to-r from-emerald-500 to-cyan-500 rounded-lg hover:from-emerald-600 hover:to-cyan-600 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                {saveMutation.isPending ? (
                                    <>
                                        <i className="fas fa-spinner fa-spin mr-2"></i> {isEdit ? 'Updating...' : 'Creating...'}
                                    </>
                                ) : (
                                    <>
                                        <i className={`fas fa-user-plus mr-2`}></i> {isEdit ? 'Update Member' : 'Create Member'}
                                    </>
                                )}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {renderDialog()}
        </div>
    );
}

function ReviewSection({ icon, title, rows }: { icon: string; title: string; rows: [string, string][] }) {
    return (
        <div>
            <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                <i className={icon}></i> {title}
            </h4>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                {rows.map(([label, value]) => (
                    <div key={label}>
                        <p className="text-gray-500 text-xs">{label}</p>
                        <p className="font-medium text-gray-900 capitalize">{value || '—'}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Field({ label, value, onChange, type = 'text', required, error, helperText, placeholder, maxLength, inputMode, min, max }: { label: string; value: any; onChange: (v: any) => void; type?: string; required?: boolean; error?: string; helperText?: string; placeholder?: string; maxLength?: number; inputMode?: 'numeric' | 'tel' | 'text' | 'decimal'; min?: string; max?: string }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}{required && <span className="text-red-500 ml-0.5">*</span>}</label>
            {type === 'date' ? (
                <DatePicker value={value || ''} onChange={onChange} fromYear={BIRTH_FROM_YEAR} toYear={BIRTH_TO_YEAR} className={`w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500 ${error ? 'border-red-400' : ''}`} />
            ) : (
                <input type={type} value={value || ''} onChange={e => onChange(e.target.value)} required={required} placeholder={placeholder} maxLength={maxLength} inputMode={inputMode} min={min} max={max} className={`w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500 ${error ? 'border-red-400' : ''}`} />
            )}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            {helperText && !error && <p className="mt-1 text-xs text-gray-400">{helperText}</p>}
        </div>
    );
}

function Select({ label, value, onChange, options, required, error }: { label: string; value: any; onChange: (v: any) => void; options: string[]; required?: boolean; error?: string }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}{required && <span className="text-red-500 ml-0.5">*</span>}</label>
            <select value={value || ''} onChange={e => onChange(e.target.value)} required={required} className={`w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500 ${error ? 'border-red-400' : ''}`}>
                <option value="">Select...</option>
                {options.map(o => <option key={o} value={o} className="capitalize">{o.charAt(0).toUpperCase() + o.slice(1).replace(/-/g, ' ')}</option>)}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}