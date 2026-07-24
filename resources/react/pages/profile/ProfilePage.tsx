import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getProfile, updateProfile, ProfileData } from '../../api/profile';
import { useToast } from '../../contexts/ToastContext';

type Tab = 'overview' | 'contact' | 'employment' | 'financial' | 'beneficiaries' | 'security';

const TABS: { key: Tab; icon: string; label: string }[] = [
    { key: 'overview', icon: 'fas fa-user', label: 'Overview' },
    { key: 'contact', icon: 'fas fa-address-book', label: 'Contact & Address' },
    { key: 'employment', icon: 'fas fa-briefcase', label: 'Employment' },
    { key: 'financial', icon: 'fas fa-wallet', label: 'Financial' },
    { key: 'beneficiaries', icon: 'fas fa-users', label: 'Beneficiaries' },
    { key: 'security', icon: 'fas fa-shield-alt', label: 'Security' },
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
                    <div key={i} className="h-32 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
                ))}
            </div>
        );
    }

    if (!profile) return null;

    const { user, personal_data, address, contact, accounts, loans, beneficiaries, recent_logs, trusted_devices } = profile;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">My Profile</h1>
                <button
                    onClick={() => setShowEditModal(true)}
                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    <i className="fas fa-edit mr-1"></i> Edit Profile
                </button>
            </div>

            {/* Tabs */}
            <div className="flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-700">
                {TABS.map(tab => (
                    <button
                        key={tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={`flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                            activeTab === tab.key
                                ? 'border-emerald-600 text-emerald-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        <i className={tab.icon}></i>
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Tab Content */}
            <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                {activeTab === 'overview' && (
                    <div className="space-y-6">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 rounded-full bg-emerald-600 flex items-center justify-center text-2xl font-bold text-white">
                                {personal_data?.first_name?.charAt(0)}{personal_data?.last_name?.charAt(0)}
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                    {personal_data?.first_name} {personal_data?.last_name}
                                </h2>
                                <p className="text-sm text-slate-500">{user.email}</p>
                                <p className="text-xs text-slate-400">{user.user_number}</p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <InfoCard label="Role" value={user.primary_role} />
                            <InfoCard label="Status" value={user.account_status} />
                            <InfoCard label="Gender" value={personal_data?.gender || '—'} />
                            <InfoCard label="Civil Status" value={personal_data?.civil_status || '—'} />
                        </div>
                    </div>
                )}

                {activeTab === 'contact' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-slate-900 dark:text-white">Contact Information</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <InfoCard label="Phone" value={contact?.phone_no || '—'} />
                            <InfoCard label="Email" value={contact?.email || user.email} />
                            <InfoCard label="Facebook" value={contact?.facebook || '—'} />
                        </div>
                        <h3 className="mt-6 font-semibold text-slate-900 dark:text-white">Address</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <InfoCard label="Country" value={address?.country || '—'} />
                            <InfoCard label="Province" value={address?.province || '—'} />
                            <InfoCard label="City/Municipality" value={address?.city_municipality || '—'} />
                            <InfoCard label="Barangay" value={address?.barangay || '—'} />
                            <InfoCard label="Street" value={address?.street || '—'} />
                            <InfoCard label="ZIP Code" value={address?.zip_code || '—'} />
                        </div>
                    </div>
                )}

                {activeTab === 'employment' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-slate-900 dark:text-white">Employment Information</h3>
                        <p className="text-sm text-slate-500">Employment details will be displayed here.</p>
                    </div>
                )}

                {activeTab === 'financial' && (
                    <div className="space-y-6">
                        <h3 className="font-semibold text-slate-900 dark:text-white">Accounts</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            {accounts.map(a => (
                                <div key={a.id} className="rounded-lg border border-slate-200 p-4 dark:border-slate-600">
                                    <p className="text-sm text-slate-500">{a.account_type}</p>
                                    <p className="text-lg font-bold text-slate-900 dark:text-white">₱{Number(a.balance).toLocaleString()}</p>
                                    <p className="text-xs text-slate-400">{a.account_number}</p>
                                </div>
                            ))}
                        </div>
                        <h3 className="font-semibold text-slate-900 dark:text-white">Active Loans: {profile.active_loans_count}</h3>
                    </div>
                )}

                {activeTab === 'beneficiaries' && (
                    <div className="space-y-4">
                        <h3 className="font-semibold text-slate-900 dark:text-white">Beneficiaries</h3>
                        {beneficiaries.length === 0 ? (
                            <p className="text-sm text-slate-500">No beneficiaries listed.</p>
                        ) : (
                            <div className="space-y-3">
                                {beneficiaries.map(b => (
                                    <div key={b.id} className="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-600">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-white">{b.name}</p>
                                            <p className="text-xs text-slate-500">{b.relationship}</p>
                                        </div>
                                        <span className="text-sm font-medium text-emerald-600">{b.percentage}%</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'security' && (
                    <div className="space-y-6">
                        <h3 className="font-semibold text-slate-900 dark:text-white">Recent Activity</h3>
                        <div className="space-y-2">
                            {recent_logs.map(log => (
                                <div key={log.id} className="flex items-center gap-3 rounded-lg border border-slate-100 p-3 dark:border-slate-700">
                                    <i className="fas fa-clock text-slate-400"></i>
                                    <div>
                                        <p className="text-sm text-slate-700 dark:text-slate-300">{log.description}</p>
                                        <p className="text-xs text-slate-400">{new Date(log.created_at).toLocaleString()}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Edit Modal */}
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
            <p className="text-xs font-medium text-slate-500">{label}</p>
            <p className="text-sm font-medium text-slate-900 dark:text-white">{value}</p>
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

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        onSave(form);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-white">Edit Profile</h2>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600"><i className="fas fa-times"></i></button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="First Name" name="first_name" defaultValue={pd?.first_name} required />
                        <Field label="Middle Name" name="mid_name" defaultValue={pd?.mid_name} />
                        <Field label="Last Name" name="last_name" defaultValue={pd?.last_name} required />
                        <Field label="Suffix" name="suffix" defaultValue={pd?.suffix} />
                        <Field label="Phone" name="phone_no" defaultValue={cont?.phone_no} />
                        <Field label="Facebook" name="facebook" defaultValue={cont?.facebook} />
                    </div>
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={onClose} className="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
                        <button type="submit" disabled={isSaving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            {isSaving ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function Field({ label, name, defaultValue, required }: { label: string; name: string; defaultValue?: string; required?: boolean }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{label}</label>
            <input type="text" name={name} defaultValue={defaultValue || ''} required={required}
                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
        </div>
    );
}
