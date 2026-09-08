import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useLocation } from 'react-router';
import { changePassword, revokeDevices, deactivateAccount, deleteAccount, sendPasswordOtp } from '../../api/settings';
import { getProfile } from '../../api/profile';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import { validate, passwordChangeSchema, profileSchema, passwordStrength, type FieldErrors } from '../../lib/validation';
import DatePicker from '../../components/DatePicker';
import PublicContentPage from '../public-content/PublicContentPage';
import LoanProductsPage from '../loan-products/LoanProductsPage';
import RolePermissionsPage from '../role-permissions/RolePermissionsPage';
import ServiceAvailabilityPage from './ServiceAvailabilityPage';
import SettingsTabHeader from './SettingsTabHeader';
// import EmailChangeVerification from '../../components/EmailChangeVerification';

interface SettingsTab {
    key: string;
    label: string;
    icon: string;
    visible: boolean;
}

export default function SettingsPage() {
    const { user } = useAuth();
    const location = useLocation();
    const isMember = user?.primary_role === 'member';
    const canManageSettings = user?.permissions?.includes('settings.update');
    const isAdmin = user?.primary_role === 'admin';

    const tabs: SettingsTab[] = [
        { key: 'account', label: 'My Account', icon: 'fas fa-user-cog', visible: true },
        { key: 'public-content', label: 'Public Page', icon: 'fas fa-globe', visible: !isMember && canManageSettings },
        { key: 'loan-products', label: 'Loan Products', icon: 'fas fa-hand-holding-usd', visible: !isMember && canManageSettings },
        { key: 'role-permissions', label: 'Roles & Permissions', icon: 'fas fa-user-shield', visible: !isMember && isAdmin },
        { key: 'service-availability', label: 'Service Availability', icon: 'fas fa-toggle-on', visible: !isMember && canManageSettings },
    ];

    const visibleTabs = tabs.filter(t => t.visible);
    const initial = (location.state as { tab?: string } | null)?.tab;
    const [activeTab, setActiveTab] = useState(visibleTabs.some(t => t.key === initial) ? initial! : 'account');

    return (
        <div className="space-y-6">
            <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 rounded-2xl p-6 text-white shadow-lg">
                <h1 className="text-2xl font-bold">Settings</h1>
                <p className="mt-1 text-sm text-white/80">Manage your account and configure the cooperative system</p>
            </div>

            <div className="flex gap-2 border-b border-gray-200 overflow-x-auto">
                {visibleTabs.map(t => (
                    <button key={t.key} onClick={() => setActiveTab(t.key)} className={`flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap ${activeTab === t.key ? 'border-emerald-600 text-emerald-600' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                        <i className={`${t.icon} text-xs`}></i>
                        {t.label}
                    </button>
                ))}
            </div>

{activeTab === 'public-content' ? <PublicContentPage /> :
              activeTab === 'loan-products' ? <LoanProductsPage /> :
              activeTab === 'role-permissions' ? <RolePermissionsPage /> :
              activeTab === 'service-availability' ? <ServiceAvailabilityPage /> :
              <MemberSettings />}
        </div>
    );
}

function MemberSettings() {
    const { addToast } = useToast();
    const [showPasswordForm, setShowPasswordForm] = useState(false);
    const [showProfileEdit, setShowProfileEdit] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deactivatePassword, setDeactivatePassword] = useState('');
    const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '', otp_code: '' });
    const [pwdErrors, setPwdErrors] = useState<FieldErrors>({});
    const [otpSent, setOtpSent] = useState(false);
    const [otpError, setOtpError] = useState('');

    const queryClient = useQueryClient();

    const { data: profileData } = useQuery({
        queryKey: ['profile'],
        queryFn: () => getProfile(),
    });

    const profileMutation = useMutation({
        mutationFn: (data: Record<string, any>) => api.put('/api/profile', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['profile'] });
            setShowProfileEdit(false);
            addToast('Profile updated successfully', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update profile', 'error'),
    });

    const passwordMutation = useMutation({
        mutationFn: changePassword,
        onSuccess: () => {
            addToast('Password updated. Trusted devices were revoked.', 'success');
            setShowPasswordForm(false);
            setPwd({ current_password: '', password: '', password_confirmation: '', otp_code: '' });
            setPwdErrors({});
            setOtpSent(false);
            setOtpError('');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to change password', 'error'),
    });

    const sendOtpMutation = useMutation({
        mutationFn: sendPasswordOtp,
        onSuccess: () => { setOtpSent(true); setOtpError(''); addToast('Verification code sent to your email', 'success'); },
        onError: (err: any) => { setOtpError(err.response?.data?.message || 'Failed to send code'); addToast(err.response?.data?.message || 'Failed to send code', 'error'); },
    });

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(passwordChangeSchema, pwd);
        if (Object.keys(errs).length > 0) {
            setPwdErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setPwdErrors({});
        passwordMutation.mutate(pwd);
    };

    const revokeMutation = useMutation({
        mutationFn: revokeDevices,
        onSuccess: () => addToast('Trusted devices revoked.', 'success'),
        onError: () => addToast('Failed to revoke devices', 'error'),
    });

    const deactivateMutation = useMutation({
        mutationFn: (data: { current_password: string }) => deactivateAccount(data),
        onSuccess: () => {
            addToast('Account deactivated.', 'success');
            window.location.href = '/login';
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                title="My Account"
                description="Manage your profile, password, trusted devices, and account security."
                icon="fas fa-user-cog"
            />

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">Edit Profile</h3>
                        <p className="mt-1 text-sm text-gray-500">Your personal information.</p>
                    </div>
                    <button onClick={() => setShowProfileEdit(true)} className="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        <i className="fas fa-pen mr-1"></i> Edit Profile
                    </button>
                </div>
                <ProfileSummary profileData={profileData} />
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Change Password</h3>
                <p className="mt-1 text-sm text-gray-500">Update your password to keep your account secure.</p>
                {!showPasswordForm ? (
                    <button onClick={() => setShowPasswordForm(true)} className="mt-4 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                        Change Password
                    </button>
                ) : (
                    <form onSubmit={handlePasswordSubmit} className="mt-4 space-y-3">
                        <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                            <p className="text-xs font-semibold text-indigo-800">Security verification</p>
                            <p className="mt-1 text-xs text-indigo-700">A 6-digit code is required to change your password. Enter the code sent to your email.</p>
                            <div className="mt-2 flex gap-2">
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    maxLength={6}
                                    placeholder="6-digit code"
                                    value={pwd.otp_code}
                                    onChange={e => { setPwd(f => ({ ...f, otp_code: e.target.value.replace(/\D/g, '') })); setOtpError(''); }}
                                    className={`w-full rounded-lg border px-3 py-2 text-sm tracking-[0.3em] ${pwdErrors.otp_code ? 'border-red-400' : 'border-gray-200'}`}
                                />
                                <button
                                    type="button"
                                    onClick={() => sendOtpMutation.mutate()}
                                    disabled={sendOtpMutation.isPending}
                                    className="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {sendOtpMutation.isPending ? 'Sending...' : otpSent ? 'Resend' : 'Send code'}
                                </button>
                            </div>
                            {otpError && <p className="mt-1 text-xs text-red-600">{otpError}</p>}
                            {pwdErrors.otp_code && <p className="mt-1 text-xs text-red-600">{pwdErrors.otp_code}</p>}
                        </div>
                        <div>
                            <input type="password" name="current_password" placeholder="Current password" required value={pwd.current_password} onChange={e => setPwd(f => ({ ...f, current_password: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${pwdErrors.current_password ? 'border-red-400' : 'border-gray-200'}`} />
                            {pwdErrors.current_password && <p className="mt-1 text-xs text-red-600">{pwdErrors.current_password}</p>}
                        </div>
                        <div>
                            <input type="password" name="password" placeholder="New password (min 8 chars)" required value={pwd.password} onChange={e => setPwd(f => ({ ...f, password: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${pwdErrors.password ? 'border-red-400' : 'border-gray-200'}`} />
                            {pwdErrors.password && <p className="mt-1 text-xs text-red-600">{pwdErrors.password}</p>}
                        </div>
                        {pwd.password && (
                            <div>
                                <div className="flex items-center gap-2">
                                    {[1, 2, 3, 4].map(i => (
                                        <div key={i} className={`h-1.5 flex-1 rounded-full ${i <= passwordStrength(pwd.password).score ? 'bg-emerald-500' : 'bg-gray-200'}`} />
                                    ))}
                                    <span className="text-xs font-medium text-gray-600">{passwordStrength(pwd.password).label}</span>
                                </div>
                            </div>
                        )}
                        <div>
                            <input type="password" name="password_confirmation" placeholder="Confirm new password" required value={pwd.password_confirmation} onChange={e => setPwd(f => ({ ...f, password_confirmation: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${pwdErrors.password_confirmation ? 'border-red-400' : 'border-gray-200'}`} />
                            {pwdErrors.password_confirmation && <p className="mt-1 text-xs text-red-600">{pwdErrors.password_confirmation}</p>}
                        </div>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setShowPasswordForm(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" disabled={passwordMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                {passwordMutation.isPending ? 'Updating...' : 'Update Password'}
                            </button>
                        </div>
                    </form>
                )}
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Trusted Devices</h3>
                <p className="mt-1 text-sm text-gray-500">Revoke all trusted devices to force re-authentication.</p>
                <button
                    onClick={() => revokeMutation.mutate()}
                    disabled={revokeMutation.isPending}
                    className="mt-4 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                >
                    <i className="fas fa-shield-alt mr-1"></i>
                    {revokeMutation.isPending ? 'Revoking...' : 'Revoke All Devices'}
                </button>
            </div>

            <div className="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-red-600">Deactivate Account</h3>
                <p className="mt-1 text-sm text-gray-500">Deactivate your account. You can contact support to reactivate.</p>
                <button onClick={() => setShowDeleteConfirm(true)} className="mt-4 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Deactivate Account
                </button>

                {showDeleteConfirm && (
                    <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
                        <p className="text-sm text-red-700 mb-3">Are you sure? Enter your password to confirm.</p>
                        <input
                            type="password"
                            value={deactivatePassword}
                            onChange={e => setDeactivatePassword(e.target.value)}
                            placeholder="Enter your password"
                            className="w-full rounded-lg border border-red-200 px-3 py-2 text-sm mb-3"
                        />
                        <div className="flex gap-2">
                            <button onClick={() => { setShowDeleteConfirm(false); setDeactivatePassword(''); }} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">Cancel</button>
                            <button
                                onClick={() => deactivateMutation.mutate({ current_password: deactivatePassword })}
                                disabled={!deactivatePassword || deactivateMutation.isPending}
                                className="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {deactivateMutation.isPending ? 'Deactivating...' : 'Confirm Deactivate'}
                            </button>
                        </div>
                    </div>
                )}
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Privacy Notice</h3>
                <p className="mt-1 text-sm text-gray-500">
                    Your personal data is handled in accordance with our privacy policy and the Data Privacy Act.
                    Only authorized personnel have access to your information.
                </p>
            </div>

            {showProfileEdit && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setShowProfileEdit(false)}>
                    <div className="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between rounded-t-xl bg-gradient-to-r from-emerald-600 to-cyan-600 p-4 text-white">
                            <h3 className="font-bold">Edit Profile</h3>
                            <button onClick={() => setShowProfileEdit(false)} className="rounded-full p-1 hover:bg-white/20" aria-label="Close">
                                <i className="fas fa-times"></i>
                            </button>
                        </div>
                        <div className="overflow-y-auto p-6">
                            <ProfileEditForm
                                profileData={profileData}
                                isSaving={profileMutation.isPending}
                                onSave={(data) => profileMutation.mutate(data)}
                                onCancel={() => setShowProfileEdit(false)}
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function ProfileSummary({ profileData }: { profileData: any }) {
    const d = profileData?.data;
    const pd = d?.personal_data;
    const addr = d?.address;
    const cont = d?.contact;
    const user = d?.user;

    if (!pd) {
        return <div className="mt-4 h-20 animate-pulse rounded-lg bg-gray-100" />;
    }

    const name = [pd.title, pd.first_name, pd.mid_name, pd.last_name, pd.suffix].filter(Boolean).join(' ') || '—';
    const address = [addr?.street, addr?.barangay, addr?.city_municipality, addr?.province, addr?.region, addr?.country].filter(Boolean).join(', ') || '—';

    const items: [string, string][] = [
        ['Full Name', name],
        ['Birth Date', pd.birth_date || '—'],
        ['Gender', pd.gender || '—'],
        ['Civil Status', pd.civil_status || '—'],
        ['Education', pd.education || '—'],
        ['Religion', pd.religion || '—'],
        ['Phone', cont?.phone_no || '—'],
        ['Email', user?.email || '—'],
        ['Facebook', cont?.facebook || '—'],
        ['Address', address],
    ];

    return (
        <div className="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            {items.map(([label, value]) => (
                <div key={label}>
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-400">{label}</p>
                    <p className="mt-0.5 text-sm text-gray-800">{value}</p>
                </div>
            ))}
        </div>
    );
}

function ProfileEditForm({ profileData, isSaving, onSave, onCancel }: { profileData: any; isSaving: boolean; onSave: (data: Record<string, any>) => void; onCancel?: () => void }) {
    const pd = profileData?.data?.personal_data;
    const addr = profileData?.data?.address;
    const cont = profileData?.data?.contact;
    const user = profileData?.data?.user;
    const { addToast } = useToast();

    const [values, setValues] = useState({
        title: pd?.title || '',
        first_name: pd?.first_name || '',
        mid_name: pd?.mid_name || '',
        last_name: pd?.last_name || '',
        suffix: pd?.suffix || '',
        birth_date: pd?.birth_date || '',
        birthplace: pd?.birthplace || '',
        gender: pd?.gender || '',
        education: pd?.education || '',
        civil_status: pd?.civil_status || '',
        religion: pd?.religion || '',
        email: user?.email || '',
        email_otp_code: '',
        phone_no: cont?.phone_no || '',
        facebook: cont?.facebook || '',
        country: addr?.country || '',
        region: addr?.region || '',
        province: addr?.province || '',
        city_municipality: addr?.city_municipality || '',
        barangay: addr?.barangay || '',
        street: addr?.street || '',
        zip_code: addr?.zip_code || '',
    });
    const [errors, setErrors] = useState<FieldErrors>({});

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const errs = validate(profileSchema, { first_name: values.first_name, last_name: values.last_name, email: values.email, phone_number: values.phone_no });
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setErrors({});
        const fd = new FormData();
        Object.entries(values).forEach(([k, v]) => { fd.append(k, v ?? ''); });
        onSave(fd);
    };

    const set = (k: string, v: string) => setValues(f => ({ ...f, [k]: v }));
    const Field = ({ label, k, error, type = 'text', className = '' }: { label: string; k: string; error?: string; type?: string; className?: string }) => (
        <div className={className}>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {type === 'date' ? (
                <>
                    <DatePicker value={values.birth_date} onChange={v => set(k, v)} fromYear={new Date().getFullYear() - 100} toYear={new Date().getFullYear()} className={`w-full rounded-lg border bg-white px-3 py-2 text-sm ${error ? 'border-red-400' : 'border-gray-200'}`} />
                </>
            ) : (
                <input type={type} name={k} value={(values as any)[k]} onChange={e => set(k, e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm ${error ? 'border-red-400' : 'border-gray-200'}`} />
            )}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );

    return (
        <form onSubmit={handleSubmit} className="mt-4 space-y-6">
            <div className="grid grid-cols-2 gap-4">
                <Field label="Title" k="title" />
                <Field label="First Name" k="first_name" error={errors.first_name} />
                <Field label="Middle Name" k="mid_name" />
                <Field label="Last Name" k="last_name" error={errors.last_name} />
                <Field label="Suffix" k="suffix" />
                <Field label="Birth Date" k="birth_date" type="date" />
                <Field label="Birthplace" k="birthplace" />
                <Field label="Gender" k="gender" />
                <Field label="Education" k="education" />
                <Field label="Civil Status" k="civil_status" />
                <Field label="Religion" k="religion" />
                <Field label="Phone Number" k="phone_no" error={errors.phone_number} />
                <div className="col-span-2">
                    <Field label="Email" k="email" type="email" error={errors.email} />
                    <EmailChangeVerification
                        originalEmail={user?.email || ''}
                        email={values.email}
                        otpCode={values.email_otp_code}
                        onOtpCodeChange={value => set('email_otp_code', value)}
                    />
                </div>
                <Field label="Facebook" k="facebook" className="col-span-2" />
            </div>
            <div>
                <h3 className="mb-2 border-b border-gray-100 pb-2 text-sm font-semibold text-gray-900">Address Details</h3>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Country" k="country" />
                    <Field label="Region" k="region" />
                    <Field label="Province" k="province" />
                    <Field label="City / Municipality" k="city_municipality" />
                    <Field label="Barangay" k="barangay" />
                    <Field label="ZIP Code" k="zip_code" />
                    <Field label="Street" k="street" className="col-span-2" />
                </div>
            </div>
            <div className="flex justify-end gap-2">
                {onCancel && (
                    <button type="button" onClick={onCancel} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">
                        Cancel
                    </button>
                )}
                <button type="submit" disabled={isSaving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                    {isSaving ? 'Saving...' : 'Update Profile'}
                </button>
            </div>
        </form>
    );
}
