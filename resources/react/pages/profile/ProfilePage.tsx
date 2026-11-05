import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getProfile, updateProfile, type ProfileData } from '../../api/profile';
import EmailChangeVerification from '../../components/EmailChangeVerification';
import FallbackImage from '../../components/FallbackImage';
import LoadingIndicator from '../../components/ui/LoadingIndicator';
import DatePicker from '../../components/DatePicker';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { z } from 'zod';
import { toFieldErrors, type FieldErrors } from '../../lib/validation';
import { useLanguage } from '../../contexts/LanguageContext';
import HelpTip from '../../components/ui/HelpTip';
import PageHeader from '../../components/ui/PageHeader';

type Tab = 'overview' | 'contact' | 'employment' | 'financial' | 'beneficiaries' | 'security' | 'privacy';

const TABS: { key: Tab; icon: string; label: string; hideForOfficers?: boolean }[] = [
    { key: 'overview', icon: 'fas fa-user', label: 'Overview' },
    { key: 'contact', icon: 'fas fa-address-book', label: 'Contact & Address' },
    { key: 'employment', icon: 'fas fa-briefcase', label: 'Employment', hideForOfficers: true },
    { key: 'financial', icon: 'fas fa-wallet', label: 'Financial', hideForOfficers: true },
    { key: 'beneficiaries', icon: 'fas fa-users', label: 'Beneficiaries', hideForOfficers: true },
    { key: 'security', icon: 'fas fa-shield-alt', label: 'Security' },
    { key: 'privacy', icon: 'fas fa-lock', label: 'Privacy' },
];

function createProfileFormData(profile: ProfileData, overrides: Record<string, any> = {}, beneficiariesOverride?: any[]): FormData {
    const data: Record<string, any> = {
        title: profile.personal_data?.title || '',
        first_name: profile.personal_data?.first_name || '',
        mid_name: profile.personal_data?.mid_name || '',
        last_name: profile.personal_data?.last_name || '',
        suffix: profile.personal_data?.suffix || '',
        birth_date: profile.personal_data?.birth_date || '',
        birthplace: profile.personal_data?.birthplace || '',
        gender: profile.personal_data?.gender || '',
        education: profile.personal_data?.education || '',
        civil_status: profile.personal_data?.civil_status || '',
        religion: profile.personal_data?.religion || '',

        email: profile.user.email,
        phone_no: profile.contact?.phone_no || '',
        facebook: profile.contact?.facebook || '',

        country: profile.address?.country || '',
        region: profile.address?.region || '',
        province: profile.address?.province || '',
        city_municipality: profile.address?.city_municipality || '',
        barangay: profile.address?.barangay || '',
        street: profile.address?.street || '',
        zip_code: profile.address?.zip_code || '',

        national_id: profile.member_id?.national_id || '',
        tin: profile.member_id?.tin || '',
        sss: profile.member_id?.sss || '',
        philhealth: profile.member_id?.philhealth || '',
        barangay_id: profile.member_id?.barangay_id || '',

        employment_type: profile.employment?.employment_type || '',
        employer_name: profile.employment?.company || '',
        position: profile.employment?.position || '',
        monthly_income: profile.employment?.monthly_income || '',
    };

    const finalData = { ...data, ...overrides };
    const fd = new FormData();

    for (const [key, value] of Object.entries(finalData)) {
        if (value !== null && value !== undefined) {
            fd.append(key, value as string);
        }
    }

    const bens = beneficiariesOverride || profile.beneficiaries || [];
    bens.forEach((b: any, index: number) => {
        fd.append(`beneficiaries[${index}][first_name]`, b.first_name || b.name.split(' ')[0] || '');
        fd.append(`beneficiaries[${index}][last_name]`, b.last_name || b.name.split(' ').slice(1).join(' ') || '');
        fd.append(`beneficiaries[${index}][relationship]`, b.relationship || '');
    });

    return fd;
}

export default function ProfilePage() {
    const { t } = useLanguage();
    const [activeTab, setActiveTab] = useState<Tab>('overview');
    const [editModal, setEditModal] = useState<'personal'|'contact'|'address'|'ids'|'employment'|'beneficiaries'|'photo'|null>(null);
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
            setEditModal(null);
            addToast(t('Profile updated successfully'), 'success');
        },
        onError: () => addToast(t('Failed to update profile'), 'error'),
    });

    if (isLoading) return <LoadingIndicator message={t('Loading your profile...')} />;

    if (!profile) return null;

    const { user, personal_data, address, contact, employment, member_id, accounts, beneficiaries, recent_logs, trusted_devices } = profile;

    return (
        <div className="space-y-6">
            <PageHeader title={t('My Profile')} description={t('Review your details and keep your information up to date.')} icon="fas fa-user" />
            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="h-20 bg-gradient-to-r from-emerald-500 to-cyan-600 relative group cursor-pointer" onClick={() => setEditModal('photo')}>
                    <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-medium">
                        <i className="fas fa-camera mr-2"></i> Update Photo
                    </div>
                </div>
                <div className="px-6 pb-6">
                    <div className="flex items-end gap-4 -mt-8 relative z-10">
                        <div
                            className="h-20 w-20 rounded-full bg-white flex items-center justify-center ring-4 ring-white shadow-lg overflow-hidden cursor-pointer group"
                            onClick={() => setEditModal('photo')}
                        >
                            {personal_data?.profile_picture ? (
                                <FallbackImage src={personal_data.profile_picture} alt="Profile" kind="avatar" className="h-full w-full object-cover group-hover:opacity-75 transition-opacity" />
                            ) : (
                                <div className="h-full w-full bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center text-2xl font-bold text-white group-hover:opacity-90 transition-opacity">
                                    {personal_data?.first_name?.charAt(0)}{personal_data?.last_name?.charAt(0)}
                                </div>
                            )}
                        </div>
                        <div className="flex-1 pb-1">
                            <h2 className="text-xl font-bold text-gray-900">
                                {personal_data?.first_name} {personal_data?.last_name}
                            </h2>
                            <p className="text-sm text-gray-500">{user.email}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
                {TABS.filter(tab => !user.is_officer || !tab.hideForOfficers).map(tab => (
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
                        {t(tab.label)}
                    </button>
                ))}
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                {activeTab === 'overview' && (
                    <div className="space-y-8">
                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="font-semibold text-gray-900">{t('Personal Information')}</h3>
                                <button onClick={() => setEditModal('personal')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700"><i className="fas fa-edit mr-1"></i>Edit</button>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                <InfoCard label={t('First Name')} value={personal_data?.first_name || '\u2014'} />
                                <InfoCard label={t('Middle Name')} value={personal_data?.mid_name || '\u2014'} />
                                <InfoCard label={t('Last Name')} value={personal_data?.last_name || '\u2014'} />
                                <InfoCard label={t('Suffix')} value={personal_data?.suffix || '\u2014'} />
                                <InfoCard label={t('Gender')} value={personal_data?.gender || '\u2014'} />
                                <InfoCard label={t('Civil Status')} value={personal_data?.civil_status || '\u2014'} />
                                <InfoCard label={t('Birth Date')} value={personal_data?.birth_date || '\u2014'} />
                                <InfoCard label={t('Birthplace')} value={personal_data?.birthplace || '\u2014'} />
                                <InfoCard label={t('Religion')} value={personal_data?.religion || '\u2014'} />
                                <InfoCard label={t('Education')} value={personal_data?.education || '\u2014'} />
                            </div>
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="font-semibold text-gray-900">{t('Government IDs')}</h3>
                                <button onClick={() => setEditModal('ids')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700"><i className="fas fa-edit mr-1"></i>Edit</button>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                <InfoCard label={t('National ID')} value={member_id?.national_id || '\u2014'} />
                                <InfoCard label={t('TIN')} value={member_id?.tin || '\u2014'} />
                                <InfoCard label={t('SSS')} value={member_id?.sss || '\u2014'} />
                                <InfoCard label={t('PhilHealth')} value={member_id?.philhealth || '\u2014'} />
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'contact' && (
                    <div className="space-y-8">
                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="font-semibold text-gray-900">{t('Contact Information')}</h3>
                                <button onClick={() => setEditModal('contact')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700"><i className="fas fa-edit mr-1"></i>Edit</button>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <InfoCard label={t('Phone')} value={contact?.phone_no || '\u2014'} />
                                <InfoCard label={t('Email')} value={contact?.email || user.email} />
                                <InfoCard label={t('Facebook')} value={contact?.facebook || '\u2014'} />
                            </div>
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="font-semibold text-gray-900">{t('Address')}</h3>
                                <button onClick={() => setEditModal('address')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700"><i className="fas fa-edit mr-1"></i>Edit</button>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <InfoCard label={t('Country')} value={address?.country || '\u2014'} />
                                <InfoCard label={t('Region')} value={address?.region || '\u2014'} />
                                <InfoCard label={t('Province')} value={address?.province || '\u2014'} />
                                <InfoCard label={t('City/Municipality')} value={address?.city_municipality || '\u2014'} />
                                <InfoCard label={t('Barangay')} value={address?.barangay || '\u2014'} />
                                <InfoCard label={t('Street')} value={address?.street || '\u2014'} />
                                <InfoCard label={t('ZIP Code')} value={address?.zip_code || '\u2014'} />
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'employment' && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-semibold text-gray-900">{t('Employment Information')}</h3>
                            <button onClick={() => setEditModal('employment')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700"><i className="fas fa-edit mr-1"></i>Edit</button>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <InfoCard label={t('Employment Type')} value={employment?.employment_type || '\u2014'} />
                            <InfoCard label={t('Company / Employer')} value={employment?.company || '\u2014'} />
                            <InfoCard label={t('Position')} value={employment?.position || '\u2014'} />
                            <InfoCard label={t('Monthly Income')} value={employment?.monthly_income ? `\u20B1${Number(employment.monthly_income).toLocaleString()}` : '\u2014'} />
                        </div>
                    </div>
                )}

                {activeTab === 'financial' && (
                    <div className="space-y-6">
                        <h3 className="font-semibold text-gray-900">{t('Accounts')}</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            {accounts.map(a => (
                                <div key={a.id} className="rounded-xl border border-gray-200 p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 capitalize">{a.account_type}</p>
                                    <p className="text-lg font-bold text-gray-900">&#8369;{Number(a.balance).toLocaleString()}</p>
                                    <p className="text-xs text-gray-400">{a.account_number}</p>
                                </div>
                            ))}
                        </div>
                        <h3 className="font-semibold text-gray-900 mt-6">{t('Active Loans:')} {profile.active_loans_count}</h3>
                    </div>
                )}

                {activeTab === 'beneficiaries' && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-semibold text-gray-900">{t('Beneficiaries')}</h3>
                            <button onClick={() => setEditModal('beneficiaries')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700"><i className="fas fa-edit mr-1"></i>Manage</button>
                        </div>
                        {beneficiaries.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                                {t('No beneficiaries listed. Click manage to add.')}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {beneficiaries.map((b, i) => (
                                    <div key={b.id || i} className="flex items-center justify-between rounded-xl border border-gray-200 p-4 bg-gray-50/50">
                                        <div className="flex items-center gap-3">
                                            <div className="h-10 w-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                                                {b.first_name?.charAt(0) || b.name?.charAt(0)}{b.last_name?.charAt(0)}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900">{b.name || (b.first_name + ' ' + b.last_name)}</p>
                                                <p className="text-xs text-gray-500 uppercase tracking-wider">{b.relationship}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'security' && (
                    <div className="space-y-6">
                        <h3 className="font-semibold text-gray-900">{t('Trusted Devices')} <HelpTip text="Ito ang mga device na dati mong ginamit para mag-login. Kung may hindi ka kilalang device, kontakin agad ang cooperative para ma-check ang account mo." /></h3>
                        {trusted_devices.length === 0 ? (
                            <p className="text-sm text-gray-500">{t('No trusted devices on record.')}</p>
                        ) : (
                            <div className="space-y-2">
                                {trusted_devices.map(d => (
                                    <div key={d.id} className="flex items-center justify-between rounded-xl border border-gray-100 p-3">
                                        <div>
                                            <p className="text-sm font-medium text-gray-700">{d.device_label || t('Unknown device')}</p>
                                            <p className="text-xs text-gray-500">{d.last_ip_address} &middot; {d.last_used_at ? `${t('Last used')} ${new Date(d.last_used_at).toLocaleString()}` : ''}</p>
                                        </div>
                                        <span className={`px-2.5 py-1 text-xs font-semibold rounded-full ${d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{t(d.is_active ? 'Active' : 'Inactive')}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                        <h3 className="font-semibold text-gray-900 pt-4 border-t border-gray-100">{t('Recent Activity')}</h3>
                        <div className="space-y-3">
                            {recent_logs.length === 0 ? (
                                <p className="text-sm text-gray-500">{t('No recent activity.')}</p>
                            ) : recent_logs.map(log => (
                                <div key={log.id} className="flex items-start gap-3">
                                    <div className="mt-0.5"><i className="fas fa-circle-dot text-[10px] text-gray-300"></i></div>
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
                        <h3 className="font-semibold text-gray-900">{t('Data Sharing Preferences')}</h3>
                        <div className="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center">
                            <i className="fas fa-shield-alt text-3xl text-gray-300 mb-3 block"></i>
                            <p className="text-sm text-gray-500">
                                {t('Data sharing preferences will be available in a future update. Your data is handled according to our privacy policy.')}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {editModal === 'personal' && (
                <EditModal
                    title={t('Edit Personal Info')}
                    onClose={() => setEditModal(null)}
                    onSave={(data) => updateMutation.mutateAsync(createProfileFormData(profile, data))}
                    isSaving={updateMutation.isPending}
                >
                    {(state, handleChange, errors) => (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label={t('First Name')} name="first_name" value={state.first_name ?? profile.personal_data?.first_name} onChange={handleChange} required error={errors.first_name} />
                            <Field label={t('Middle Name')} name="mid_name" value={state.mid_name ?? profile.personal_data?.mid_name} onChange={handleChange} />
                            <Field label={t('Last Name')} name="last_name" value={state.last_name ?? profile.personal_data?.last_name} onChange={handleChange} required error={errors.last_name} />
                            <Field label={t('Suffix')} name="suffix" value={state.suffix ?? profile.personal_data?.suffix} onChange={handleChange} />
                            <Field label={t('Birth Date')} name="birth_date" type="date" value={state.birth_date ?? profile.personal_data?.birth_date} onChange={handleChange} />
                            <Field label={t('Gender')} name="gender" value={state.gender ?? profile.personal_data?.gender} onChange={handleChange} />
                            <Field label={t('Civil Status')} name="civil_status" value={state.civil_status ?? profile.personal_data?.civil_status} onChange={handleChange} />
                            <Field label={t('Birthplace')} name="birthplace" value={state.birthplace ?? profile.personal_data?.birthplace} onChange={handleChange} />
                            <Field label={t('Religion')} name="religion" value={state.religion ?? profile.personal_data?.religion} onChange={handleChange} />
                            <Field label={t('Education')} name="education" value={state.education ?? profile.personal_data?.education} onChange={handleChange} />
                        </div>
                    )}
                </EditModal>
            )}

            {editModal === 'contact' && (
                <EditModal
                    title={t('Edit Contact Info')}
                    onClose={() => setEditModal(null)}
                    onSave={(data) => updateMutation.mutateAsync(createProfileFormData(profile, data))}
                    isSaving={updateMutation.isPending}
                >
                    {(state, handleChange, errors) => (
                        <div className="space-y-4">
                            <Field label={t('Phone Number')} name="phone_no" value={state.phone_no ?? profile.contact?.phone_no} onChange={handleChange} required error={errors.phone_no} />
                            <div>
                                <Field label={t('Email Address')} name="email" type="email" value={state.email ?? profile.user.email} onChange={handleChange} required error={errors.email} />
                                <EmailChangeVerification originalEmail={profile.user.email} email={state.email ?? profile.user.email} />
                            </div>
                            <Field label={t('Facebook Link')} name="facebook" value={state.facebook ?? profile.contact?.facebook} onChange={handleChange} />
                        </div>
                    )}
                </EditModal>
            )}

            {editModal === 'address' && (
                <EditModal
                    title={t('Edit Address')}
                    onClose={() => setEditModal(null)}
                    onSave={(data) => updateMutation.mutateAsync(createProfileFormData(profile, data))}
                    isSaving={updateMutation.isPending}
                >
                    {(state, handleChange, errors) => (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label={t('Country')} name="country" value={state.country ?? profile.address?.country} onChange={handleChange} />
                            <Field label={t('Region')} name="region" value={state.region ?? profile.address?.region} onChange={handleChange} />
                            <Field label={t('Province')} name="province" value={state.province ?? profile.address?.province} onChange={handleChange} />
                            <Field label={t('City/Municipality')} name="city_municipality" value={state.city_municipality ?? profile.address?.city_municipality} onChange={handleChange} required error={errors.city_municipality} />
                            <Field label={t('Barangay')} name="barangay" value={state.barangay ?? profile.address?.barangay} onChange={handleChange} />
                            <Field label={t('Street')} name="street" value={state.street ?? profile.address?.street} onChange={handleChange} required error={errors.street} />
                            <Field label={t('ZIP Code')} name="zip_code" value={state.zip_code ?? profile.address?.zip_code} onChange={handleChange} />
                        </div>
                    )}
                </EditModal>
            )}

            {editModal === 'ids' && (
                <EditModal
                    title={t('Edit Government IDs')}
                    onClose={() => setEditModal(null)}
                    onSave={(data) => updateMutation.mutateAsync(createProfileFormData(profile, data))}
                    isSaving={updateMutation.isPending}
                >
                    {(state, handleChange) => (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label={t('National ID')} name="national_id" value={state.national_id ?? profile.member_id?.national_id} onChange={handleChange} />
                            <Field label={t('TIN')} name="tin" value={state.tin ?? profile.member_id?.tin} onChange={handleChange} />
                            <Field label={t('SSS')} name="sss" value={state.sss ?? profile.member_id?.sss} onChange={handleChange} />
                            <Field label={t('PhilHealth')} name="philhealth" value={state.philhealth ?? profile.member_id?.philhealth} onChange={handleChange} />
                        </div>
                    )}
                </EditModal>
            )}

            {editModal === 'employment' && (
                <EditModal
                    title={t('Edit Employment Info')}
                    onClose={() => setEditModal(null)}
                    onSave={(data) => updateMutation.mutateAsync(createProfileFormData(profile, data))}
                    isSaving={updateMutation.isPending}
                >
                    {(state, handleChange) => (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label={t('Employment Type')} name="employment_type" value={state.employment_type ?? profile.employment?.employment_type} onChange={handleChange} />
                            <Field label={t('Company/Employer')} name="employer_name" value={state.employer_name ?? profile.employment?.company} onChange={handleChange} />
                            <Field label={t('Position')} name="position" value={state.position ?? profile.employment?.position} onChange={handleChange} />
                            <Field label={t('Monthly Income')} name="monthly_income" type="number" value={state.monthly_income ?? profile.employment?.monthly_income} onChange={handleChange} />
                        </div>
                    )}
                </EditModal>
            )}

            {editModal === 'photo' && (
                <EditModal
                    title={t('Update Profile Photo')}
                    onClose={() => setEditModal(null)}
                    onSave={(data) => {
                        const fd = createProfileFormData(profile, {});
                        if (data.profile_photo) fd.append('profile_photo', data.profile_photo);
                        return updateMutation.mutateAsync(fd);
                    }}
                    isSaving={updateMutation.isPending}
                >
                    {(state, handleChange) => (
                        <div className="space-y-4">
                            <input
                                type="file"
                                name="profile_photo"
                                accept="image/*"
                                onChange={(e) => handleChange('profile_photo', e.target.files?.[0])}
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                            />
                        </div>
                    )}
                </EditModal>
            )}

            {editModal === 'beneficiaries' && (
                <BeneficiariesEditModal
                    profile={profile}
                    onClose={() => setEditModal(null)}
                    onSave={(bens) => updateMutation.mutate(createProfileFormData(profile, {}, bens))}
                    isSaving={updateMutation.isPending}
                />
            )}
        </div>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">{label}</p>
            <p className="text-sm font-medium text-gray-900">{value}</p>
        </div>
    );
}

function Field({ label, name, required, type, value, onChange, error }: { label: string; name: string; required?: boolean; type?: string; value?: string; onChange: (name: string, v: string) => void; error?: string }) {
    const { t } = useLanguage();
    return (
        <div>
            <label htmlFor={`profile-${name}`} className="mb-1.5 block text-sm font-medium text-gray-700">{label}{required && ' *'}</label>
            {type === 'date' ? (
                <>
                    <DatePicker value={value ?? ''} onChange={(v) => onChange(name, v)} fromYear={new Date().getFullYear() - 100} toYear={new Date().getFullYear()} className={`w-full rounded-lg border bg-white px-3 py-2 text-sm ${error ? 'border-red-400' : 'border-gray-200'}`} />
                </>
            ) : (
                <input
                    id={`profile-${name}`}
                    type={type || 'text'}
                    value={value ?? ''}
                    onChange={(e) => onChange(name, e.target.value)}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `profile-${name}-error` : undefined}
                    className={`w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 ${error ? 'border-red-400' : 'border-gray-200'}`}
                />
            )}
            {error && <p id={`profile-${name}-error`} role="alert" className="mt-1 text-xs text-red-600">{t(error)}</p>}
        </div>
    );
}

function EditModal({ title, children, onClose, onSave, isSaving }: {
    title: string;
    children: (state: any, handleChange: (name: string, v: any) => void, errors: FieldErrors) => React.ReactNode;
    onClose: () => void;
    onSave: (data: any) => Promise<unknown>;
    isSaving: boolean;
}) {
    const { t } = useLanguage();
    const [state, setState] = useState<any>({});
    const [errors, setErrors] = useState<FieldErrors>({});
    const { markDirty, requestClose, renderDialog } = useDiscardGuard();

    const handleChange = (name: string, v: any) => {
        markDirty();
        setState((s: any) => ({ ...s, [name]: v }));
        if (errors[name]) setErrors(e => ({ ...e, [name]: undefined }));
    };

    const requestCloseGuard = () => requestClose(onClose);

    useEffect(() => {
        const previouslyFocused = document.activeElement as HTMLElement | null;
        return () => previouslyFocused?.focus();
    }, []);

    useEffect(() => {
        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') requestClose(onClose);
        };
        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, [onClose, requestClose]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await onSave(state);
        } catch (error: any) {
            const responseErrors = error.response?.data?.errors;
            if (responseErrors) {
                const fields: FieldErrors = {};
                for (const [field, messages] of Object.entries(responseErrors)) {
                    fields[field] = Array.isArray(messages) ? String(messages[0]) : String(messages);
                }
                setErrors(fields);
            }
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={requestCloseGuard}>
            <div role="dialog" aria-modal="true" aria-label={title} className="w-full max-w-xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 shrink-0">
                    <h2 className="text-lg font-bold text-gray-900">{title}</h2>
                    <button onClick={requestCloseGuard} className="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"><i className="fas fa-times"></i></button>
                </div>
                <div className="p-6 overflow-y-auto">
                    <form id="edit-form" onSubmit={handleSubmit}>
                        {children(state, handleChange, errors)}
                    </form>
                </div>
                <div className="border-t border-gray-100 bg-gray-50 px-6 py-4 flex justify-end gap-3 shrink-0">
                    <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" form="edit-form" disabled={isSaving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        {isSaving ? t('Saving...') : 'Save Changes'}
                    </button>
                </div>
            </div>
            {renderDialog()}
        </div>
    );
}

function BeneficiariesEditModal({ profile, onClose, onSave, isSaving }: { profile: ProfileData, onClose: () => void, onSave: (b: any[]) => void, isSaving: boolean }) {
    const { t } = useLanguage();
    const { markDirty, requestClose, renderDialog } = useDiscardGuard();
    const [bens, setBens] = useState<any[]>(
        profile.beneficiaries?.length > 0
            ? profile.beneficiaries.map(b => ({ first_name: b.first_name || b.name.split(' ')[0], last_name: b.last_name || b.name.split(' ').slice(1).join(' '), relationship: b.relationship }))
            : [{ first_name: '', last_name: '', relationship: '' }]
    );
    const [errors, setErrors] = useState<string>('');

    const requestCloseGuard = () => requestClose(onClose);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const valid = bens.every(b => b.first_name?.trim() && b.last_name?.trim() && b.relationship?.trim());
        if (!valid) {
            setErrors('Please fill out all fields for all beneficiaries');
            return;
        }
        onSave(bens);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={requestCloseGuard}>
            <div className="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 shrink-0">
                    <h2 className="text-lg font-bold text-gray-900">{t('Manage Beneficiaries')} <HelpTip text="Ilagay ang pangalan at relasyon sa iyo ng bawat beneficiary. Puwede kang magdagdag o mag-alis bago i-save." /></h2>
                    <button onClick={requestCloseGuard} className="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"><i className="fas fa-times"></i></button>
                </div>
                <div className="p-6 overflow-y-auto">
                    <form id="bens-form" onSubmit={handleSubmit} className="space-y-4">
                        {errors && <div className="p-3 bg-red-50 text-red-700 text-sm rounded-lg border border-red-100">{t(errors)}</div>}
                        {bens.map((b, i) => (
                            <div key={i} className="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end p-4 bg-gray-50 rounded-xl border border-gray-200">
                                <div className="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-500">{t('First Name')}</label>
                                        <input type="text" value={b.first_name} onChange={e => { markDirty(); const n = [...bens]; n[i].first_name = e.target.value; setBens(n); setErrors(''); }} className="w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" required />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-500">{t('Last Name')}</label>
                                        <input type="text" value={b.last_name} onChange={e => { markDirty(); const n = [...bens]; n[i].last_name = e.target.value; setBens(n); setErrors(''); }} className="w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" required />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-500">{t('Relationship')}</label>
                                        <input type="text" value={b.relationship} onChange={e => { markDirty(); const n = [...bens]; n[i].relationship = e.target.value; setBens(n); setErrors(''); }} className="w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" required />
                                    </div>
                                </div>
                                <button type="button" onClick={() => { markDirty(); setBens(bens.filter((_, idx) => idx !== i)) }} className="h-[38px] px-3 self-end sm:self-auto text-red-500 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-100 flex items-center justify-center"><i className="fas fa-trash"></i></button>
                            </div>
                        ))}
                        <button type="button" onClick={() => { markDirty(); setBens([...bens, { first_name: '', last_name: '', relationship: '' }]) }} className="w-full py-3 border-2 border-dashed border-gray-200 rounded-xl text-sm font-medium text-emerald-600 hover:border-emerald-200 hover:bg-emerald-50 transition-colors"><i className="fas fa-plus mr-2"></i>{t('Add Beneficiary')}</button>
                    </form>
                </div>
                <div className="border-t border-gray-100 bg-gray-50 px-6 py-4 flex justify-end gap-3 shrink-0">
                    <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" form="bens-form" disabled={isSaving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        {isSaving ? t('Saving...') : t('Save Beneficiaries')}
                    </button>
                </div>
            </div>
            {renderDialog()}
        </div>
    );
}
