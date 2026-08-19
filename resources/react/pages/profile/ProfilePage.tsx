import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import DatePicker from '../../components/DatePicker';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, profileSchema, type FieldErrors } from '../../lib/validation';

type Tab = 'overview' | 'contact' | 'employment' | 'financial' | 'beneficiaries' | 'security' | 'privacy';

const TABS: { key: Tab; icon: string; label: string }[] = [
    { key: 'overview', icon: 'fas fa-user', label: 'Overview' },
    { key: 'contact', icon: 'fas fa-address-book', label: 'Contact & Address' },
    { key: 'employment', icon: 'fas fa-briefcase', label: 'Employment' },
    { key: 'financial', icon: 'fas fa-wallet', label: 'Financial' },
    { key: 'beneficiaries', icon: 'fas fa-users', label: 'Beneficiaries' },
    { key: 'security', icon: 'fas fa-shield-alt', label: 'Security' },
    { key: 'privacy', icon: 'fas fa-lock', label: 'Privacy' },
];

export default function ProfilePage() {
    const [activeTab, setActiveTab] = useState<Tab>('overview');
    const [showEditModal, setShowEditModal] = useState(false);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['profile'],
        queryFn: () => getProfile(),
    });

    const profile: ProfileData | null = data?.data;

    const updateMutation = useMutation({
        mutationFn: updateProfile,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['profile'] });
            setShowEditModal(false);
            addToast('Profile updated successfully', 'success');
        },
        onError: () => addToast('Failed to update profile', 'error'),
    });

    if (isLoading) {
        return (
            <div className="space-y-4">
                {[...Array(3)].map((_, i) => (
                    <div key={i} className="h-32 animate-pulse rounded-xl bg-gray-100" />
                ))}
            </div>
        );
    }

    if (!profile) return null;

    const { user, personal_data, address, contact, employment, accounts, loans, beneficiaries, recent_logs, trusted_devices } = profile;

    return (
        <div className="space-y-6">
            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="h-20 bg-gradient-to-r from-emerald-500 to-cyan-600"></div>
                <div className="px-6 pb-6">
                    <div className="flex items-end gap-4 -mt-8">
                        <div className="h-16 w-16 rounded-full bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center text-2xl font-bold text-white ring-4 ring-white shadow">
                            {personal_data?.first_name?.charAt(0)}{personal_data?.last_name?.charAt(0)}
                        </div>
                        <div className="flex-1">
                            <h2 className="text-xl font-bold text-gray-900">
                                {personal_data?.first_name} {personal_data?.last_name}
                            </h2>
                            <p className="text-sm text-gray-500">{user.email}</p>
                        </div>
                        <button
                            onClick={() => setShowEditModal(true)}
                            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                        >
                            <i className="fas fa-edit mr-1"></i> Edit Personal Info
                        </button>
                    </div>
                </div>
            </div>

            <div className="flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
                {TABS.map(tab => (
                    <button
                        key={tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={`flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2.5 text-sm font-medium transition-colors ${
                            activeTab === tab.key
                                ? 'bg-emerald-600 text-white shadow'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        <i className={tab.icon}></i>
                        {tab.label}
                    </button>
                ))}
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                {activeTab === 'overview' && (
                    <div className="space-y-6">
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <InfoCard label="Member ID" value={user.user_number || '\u2014'} />
                            <StatChip label="Role" value={user.primary_role} color="emerald" />
                            <StatChip label="Status" value={user.account_status} color="emerald" />
                            <InfoCard label="Gender" value={personal_data?.gender || '\u2014'} />
                            <InfoCard label="Civil Status" value={personal_data?.civil_status || '\u2014'} />
                            <InfoCard label="Birth Date" value={personal_data?.birth_date || '\u2014'} />
                            <StatChip label="Active Loans" value={String(profile.active_loans_count || 0)} color="amber" />
                            <StatChip label="Beneficiaries" value={String(beneficiaries?.length || 0)} color="cyan" />
                        </div>
                    </div>
                )}

                {activeTab === 'contact' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-gray-900">Contact Information</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <InfoCard label="Phone" value={contact?.phone_no || '\u2014'} />
                            <InfoCard label="Email" value={contact?.email || user.email} />
                            <InfoCard label="Facebook" value={contact?.facebook || '\u2014'} />
                        </div>
                        <h3 className="mt-6 font-semibold text-gray-900">Address</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <InfoCard label="Country" value={address?.country || '\u2014'} />
                            <InfoCard label="Province" value={address?.province || '\u2014'} />
                            <InfoCard label="City/Municipality" value={address?.city_municipality || '\u2014'} />
                            <InfoCard label="Barangay" value={address?.barangay || '\u2014'} />
                            <InfoCard label="Street" value={address?.street || '\u2014'} />
                            <InfoCard label="ZIP Code" value={address?.zip_code || '\u2014'} />
                        </div>
                    </div>
                )}

                {activeTab === 'employment' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-gray-900">Employment Information</h3>
                        {employment ? (
                            <div className="grid grid-cols-2 gap-4">
                                <InfoCard label="Employment Type" value={employment.employment_type || '\u2014'} />
                                <InfoCard label="Company / Employer" value={employment.company || '\u2014'} />
                                <InfoCard label="Position" value={employment.position || '\u2014'} />
                                <InfoCard label="Monthly Income" value={employment.monthly_income ? `\u20B1${Number(employment.monthly_income).toLocaleString()}` : '\u2014'} />
                                {employment.other_income_source && <InfoCard label="Other Income Source" value={employment.other_income_source} />}
                                {employment.other_income_amount ? <InfoCard label="Other Income Amount" value={`\u20B1${Number(employment.other_income_amount).toLocaleString()}`} /> : null}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No employment details on record.</p>
                        )}
                    </div>
                )}

                {activeTab === 'financial' && (
                    <div className="space-y-6">
                        <h3 className="font-semibold text-gray-900">Accounts</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            {accounts.map(a => (
                                <div key={a.id} className="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    <p className="text-sm text-gray-500">{a.account_type}</p>
                                    <p className="text-lg font-bold text-gray-900">&#8369;{Number(a.balance).toLocaleString()}</p>
                                    <p className="text-xs text-gray-400">{a.account_number}</p>
                                </div>
                            ))}
                        </div>
                        <h3 className="font-semibold text-gray-900">Active Loans: {profile.active_loans_count}</h3>
                    </div>
                )}

                {activeTab === 'beneficiaries' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-gray-900">Beneficiaries</h3>
                        {beneficiaries.length === 0 ? (
                            <p className="text-sm text-gray-500">No beneficiaries listed.</p>
                        ) : (
                            <div className="space-y-3">
                                {beneficiaries.map(b => (
                                    <div key={b.id} className="flex items-center justify-between rounded-xl border border-gray-200 p-3">
                                        <div>
                                            <p className="font-medium text-gray-900">{b.name}</p>
                                            <p className="text-xs text-gray-500">{b.relationship}</p>
                                        </div>
                                        {b.percentage != null && <span className="text-sm font-medium text-emerald-600">{b.percentage}%</span>}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'security' && (
                    <div className="space-y-6">
                        <h3 className="font-semibold text-gray-900">Trusted Devices</h3>
                        {trusted_devices.length === 0 ? (
                            <p className="text-sm text-gray-500">No trusted devices on record.</p>
                        ) : (
                            <div className="space-y-2">
                                {trusted_devices.map(d => (
                                    <div key={d.id} className="flex items-center justify-between rounded-xl border border-gray-100 p-3">
                                        <div>
                                            <p className="text-sm text-gray-700">{d.device_name || 'Unknown device'}</p>
                                            <p className="text-xs text-gray-400">{d.ip_address}{d.last_used_at ? ` · last used ${new Date(d.last_used_at).toLocaleString()}` : ''}</p>
                                        </div>
                                        <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{d.is_active ? 'Active' : 'Inactive'}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                        <h3 className="font-semibold text-gray-900 pt-2">Recent Activity</h3>
                        <div className="space-y-2">
                            {recent_logs.length === 0 ? (
                                <p className="text-sm text-gray-500">No recent activity.</p>
                            ) : recent_logs.map(log => (
                                <div key={log.id} className="flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                                    <i className="fas fa-clock text-gray-400"></i>
                                    <div>
                                        <p className="text-sm text-gray-700">{log.description}</p>
                                        <p className="text-xs text-gray-400">{new Date(log.created_at).toLocaleString()}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {activeTab === 'privacy' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-gray-900">Data Sharing Preferences</h3>
                        <div className="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center">
                            <i className="fas fa-shield-alt text-3xl text-gray-300 mb-3 block"></i>
                            <p className="text-sm text-gray-500">
                                Data sharing preferences will be available in a future update.
                                Your data is handled according to our privacy policy.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {showEditModal && (
                <EditProfileModal
                    profile={profile}
                    onClose={() => setShowEditModal(false)}
                    onSave={(formData) => updateMutation.mutate(formData)}
                    isSaving={updateMutation.isPending}
                />
            )}
        </div>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-gray-500">{label}</p>
            <p className="text-sm font-medium text-gray-900">{value}</p>
        </div>
    );
}

function StatChip({ label, value, color }: { label: string; value: string; color: 'emerald' | 'cyan' | 'amber' }) {
    const colors = {
        emerald: 'bg-emerald-50 text-emerald-700',
        cyan: 'bg-cyan-50 text-cyan-700',
        amber: 'bg-amber-50 text-amber-700',
    };
    return (
        <div className={`rounded-xl ${colors[color]} p-3 text-center`}>
            <p className="text-xs font-medium opacity-75">{label}</p>
            <p className="text-lg font-bold">{value}</p>
        </div>
    );
}

function EditProfileModal({ profile, onClose, onSave, isSaving }: {
    profile: ProfileData;
    onClose: () => void;
    onSave: (data: FormData) => void;
    isSaving: boolean;
}) {
    const pd = profile.personal_data;
    const addr = profile.address;
    const cont = profile.contact;
    const user = profile.user;

    const [errors, setErrors] = useState<FieldErrors>({});
    const [first_name, setFirstName] = useState(pd?.first_name || '');
    const [last_name, setLastName] = useState(pd?.last_name || '');
    const [email, setEmail] = useState(user.email);
    const [birth_date, setBirthDate] = useState(pd?.birth_date || '');
    const { markDirty, requestClose, renderDialog } = useDiscardGuard();

    const setFirstNameD = (v: string) => { markDirty(); setFirstName(v); };
    const setLastNameD = (v: string) => { markDirty(); setLastName(v); };
    const setEmailD = (v: string) => { markDirty(); setEmail(v); };
    const setBirthDateD = (v: string) => { markDirty(); setBirthDate(v); };
    const requestCloseGuard = () => requestClose(onClose);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const errs = validate(profileSchema, { first_name, last_name, email, phone_number: cont?.phone_no || '' });
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            return;
        }
        setErrors({});
        const form = new FormData(e.currentTarget);
        onSave(form);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={requestCloseGuard}>
            <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Edit Profile</h2>
                        <button onClick={requestCloseGuard} className="rounded-lg p-1 text-white/70 hover:bg-white/10 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                </div>
                <div className="p-6">
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <h3 className="mb-3 text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Personal Info</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Title" name="title" defaultValue={pd?.title || ''} />
                            <Field label="First Name" name="first_name" value={first_name} onChange={setFirstNameD} required error={errors.first_name} />
                            <Field label="Middle Name" name="mid_name" defaultValue={pd?.mid_name || ''} />
                            <Field label="Last Name" name="last_name" value={last_name} onChange={setLastNameD} required error={errors.last_name} />
                            <Field label="Suffix" name="suffix" defaultValue={pd?.suffix || ''} />
                            <Field label="Birth Date" name="birth_date" type="date" value={birth_date} onChange={setBirthDateD} />
                            <Field label="Birthplace" name="birthplace" defaultValue={pd?.birthplace || ''} />
                            <Field label="Gender" name="gender" defaultValue={pd?.gender || ''} />
                            <Field label="Civil Status" name="civil_status" defaultValue={pd?.civil_status || ''} />
                            <Field label="Education" name="education" defaultValue={pd?.education || ''} />
                            <Field label="Religion" name="religion" defaultValue={pd?.religion || ''} />
                            <Field label="Contact Phone" name="phone_no" defaultValue={cont?.phone_no || ''} />
                            <Field label="Facebook" name="facebook" defaultValue={cont?.facebook || ''} />
                            <div className="col-span-2">
                                <Field label="Email" name="email" type="email" value={email} onChange={setEmailD} required error={errors.email} />
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 className="mb-3 text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Address</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Country" name="country" defaultValue={addr?.country || ''} />
                            <Field label="Region" name="region" defaultValue={addr?.region || ''} />
                            <Field label="Province" name="province" defaultValue={addr?.province || ''} />
                            <Field label="City / Municipality" name="city_municipality" defaultValue={addr?.city_municipality || ''} />
                            <Field label="Barangay" name="barangay" defaultValue={addr?.barangay || ''} />
                            <Field label="Street" name="street" defaultValue={addr?.street || ''} />
                            <Field label="ZIP Code" name="zip_code" defaultValue={addr?.zip_code || ''} />
                        </div>
                    </div>
                    <div>
                        <h3 className="mb-3 text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Profile Photo</h3>
                        <input type="file" name="profile_photo" accept="image/*" onChange={() => markDirty()} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 px-4 py-2 text-sm hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={isSaving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {isSaving ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
                </div>
            </div>
            {renderDialog()}
        </div>
    );
}

function Field({ label, name, defaultValue, required, type, value, onChange, error }: { label: string; name: string; defaultValue?: string; required?: boolean; type?: string; value?: string; onChange?: (v: string) => void; error?: string }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {type === 'date' ? (
                <>
                    <DatePicker value={value ?? defaultValue ?? ''} onChange={onChange || (() => {})} fromYear={new Date().getFullYear() - 100} toYear={new Date().getFullYear()} className={`w-full rounded-lg border bg-white px-3 py-2 text-sm ${error ? 'border-red-400' : 'border-gray-200'}`} />
                    <input type="hidden" name={name} value={value ?? defaultValue ?? ''} />
                </>
            ) : (
                <input
                    type={type || 'text'}
                    name={name}
                    defaultValue={defaultValue}
                    value={value}
                    onChange={onChange ? e => onChange(e.target.value) : undefined}
                    required={required}
                    className={`w-full rounded-lg border bg-white px-3 py-2 text-sm ${error ? 'border-red-400' : 'border-gray-200'}`}
                />
            )}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
