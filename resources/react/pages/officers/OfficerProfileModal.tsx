import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import FallbackImage from '../../components/FallbackImage';
import LoadingIndicator from '../../components/ui/LoadingIndicator';

const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'contact', label: 'Contact & IDs' },
    { key: 'documents', label: 'Documents' },
];

const ROLE_COLORS: Record<string, string> = {
    admin: 'bg-red-100 text-red-800 border-red-200',
    manager: 'bg-purple-100 text-purple-800 border-purple-200',
    accountant: 'bg-amber-100 text-amber-800 border-amber-200',
    loan: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    officer: 'bg-emerald-100 text-emerald-800 border-emerald-200',
};

const ROLE_CHIP_COLORS: Record<string, string> = {
    admin: 'bg-red-100 text-red-800',
    manager: 'bg-purple-100 text-purple-800',
    accountant: 'bg-amber-100 text-amber-800',
    default: 'bg-emerald-100 text-emerald-800',
};

const ROLE_CHIPS: Record<string, string[]> = {
    admin: ['Full System Access', 'User Management', 'Financial Management', 'Loan Approval', 'Reports Access'],
    manager: ['Member Management', 'Loan Approval', 'Transaction Oversight', 'Reports Access'],
    accountant: ['Transaction Management', 'Member Onboarding', 'Service Processing', 'Financial Reports'],
    default: ['View Records', 'Process Transactions'],
};

export default function OfficerProfileModal({ officer, onClose, onEdit, onManagePermissions, onDelete, canEdit, canDelete, canManagePermissions }: { officer: any; onClose: () => void; onEdit?: (fullOfficer: any) => void; onManagePermissions?: () => void; onDelete?: () => void; canEdit?: boolean; canDelete?: boolean; canManagePermissions?: boolean }) {
    const [activeTab, setActiveTab] = useState('overview');

    const { data, isLoading } = useQuery({
        queryKey: ['member', officer.id],
        queryFn: () => api.get(`/api/members/${officer.id}`),
    });

    if (isLoading) return <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"><LoadingIndicator message="Loading officer details..." className="min-h-64 w-full max-w-6xl rounded-lg bg-white p-10 shadow" /></div>;

    const m = data?.data;
    if (!m) {
        return null;
    }

    const pd = m.personal_data || {};
    const fullName = [pd.first_name, pd.mid_name ? (pd.mid_name[0] + '.') : '', pd.last_name, pd.suffix].filter(Boolean).join(' ') || officer.name || 'N/A';
    const primaryRole = m.primary_role || officer.primary_role || 'officer';
    const roleColor = ROLE_COLORS[primaryRole] || ROLE_COLORS.officer;
    const chips = ROLE_CHIPS[primaryRole] || ROLE_CHIPS.default;
    const chipColor = ROLE_CHIP_COLORS[primaryRole] || ROLE_CHIP_COLORS.default;

    const fmtDate = (d: string | undefined) => {
        if (!d) return 'N/A';
        const date = new Date(d);
        return isNaN(date.getTime()) ? 'N/A' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" onClick={onClose}>
            <div className="relative w-full max-w-6xl max-h-[90vh] mx-auto my-6" onClick={e => e.stopPropagation()}>
                <div className="relative flex max-h-[90vh] flex-col rounded-lg bg-white shadow overflow-hidden">
                    <div className="flex items-center justify-between border-b border-gray-200 bg-gradient-to-r from-cyan-600 to-emerald-600 p-5 shrink-0">
                        <h3 className="text-xl font-semibold text-white">Officer Profile</h3>
                        <button onClick={onClose} className="inline-flex items-center rounded-lg p-1.5 text-white bg-transparent hover:bg-cyan-800 transition-colors">
                            <i className="fas fa-times text-lg"></i>
                        </button>
                    </div>

                    <div className="border-b border-gray-200 bg-gray-50 shrink-0">
                        <div className="flex space-x-1 px-4 sm:px-6 overflow-x-auto">
                            {TABS.map(tab => (
                                <button
                                    key={tab.key}
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`px-6 py-3 text-sm font-medium whitespace-nowrap transition-colors ${activeTab === tab.key ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800'}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto p-6">
                        {activeTab === 'overview' && (
                            <div className="flex flex-col gap-6 md:flex-row">
                                <div className="w-full md:w-64 flex-shrink-0">
                                    {pd.profile_picture ? (
                                        <FallbackImage src={pd.profile_picture} alt="Profile" kind="avatar" className="w-full h-64 rounded-lg shadow-lg object-cover border-4 border-cyan-100" />
                                    ) : (
                                        <div className="w-full h-64 rounded-lg shadow-lg bg-gradient-to-br from-cyan-100 to-emerald-100 flex items-center justify-center border-4 border-cyan-100">
                                            <svg className="w-32 h-32 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                    )}
                                    <div className="mt-4 text-center">
                                        <h4 className="text-xl font-bold text-gray-800">{fullName}</h4>
                                        <p className="text-cyan-600 font-semibold mt-1">{pd.title ? pd.title.charAt(0).toUpperCase() + pd.title.slice(1) : 'Officer'}</p>
                                        <p className="text-sm text-gray-600 mt-1">ID: {m.user_number}</p>
                                        <span className={`inline-block mt-3 px-3 py-1 text-xs font-semibold rounded-full border-2 ${roleColor}`}>
                                            {primaryRole.toUpperCase()}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex-1">
                                    <h5 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">Personal Information</h5>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                        <InfoField label="Date of Birth" value={fmtDate(pd.birth_date)} />
                                        <InfoField label="Place of Birth" value={pd.birthplace} />
                                        <InfoField label="Gender" value={pd.gender ? pd.gender.charAt(0).toUpperCase() + pd.gender.slice(1) : 'N/A'} />
                                        <InfoField label="Civil Status" value={pd.civil_status ? pd.civil_status.charAt(0).toUpperCase() + pd.civil_status.slice(1) : 'N/A'} />
                                        <InfoField label="Religion" value={pd.religion} />
                                        <InfoField label="Education" value={pd.education} />
                                    </div>

                                    <h5 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100 mt-6">Role & Permissions</h5>
                                    <div className="bg-gradient-to-r from-cyan-50 to-emerald-50 p-4 rounded-lg border border-cyan-200">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <InfoField label="System Role" value={primaryRole.charAt(0).toUpperCase() + primaryRole.slice(1)} strong />
                                            <InfoField label="Position" value={pd.title ? pd.title.charAt(0).toUpperCase() + pd.title.slice(1) : 'N/A'} strong />
                                        </div>
                                        <div className="mt-4">
                                            <span className="text-sm font-medium text-gray-600 block mb-2">Permissions:</span>
                                            <div className="flex flex-wrap gap-2">
                                                {chips.map(chip => (
                                                    <span key={chip} className={`px-2 py-1 text-xs ${chipColor} rounded`}>{chip}</span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'contact' && (
                            <div>
                                <h5 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">Contact Information</h5>
                                <div className="grid grid-cols-2 gap-6">
                                    <InfoField label="Email Address" value={m.contact?.email || m.email} />
                                    <InfoField label="Phone Number" value={m.contact?.phone_no} />
                                    <InfoField label="Facebook" value={m.contact?.facebook} />
                                </div>
                            </div>
                        )}

                        {activeTab === 'documents' && (
                            <div>
                                <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Profile Photo</h4>
                                {pd.profile_picture ? (
                                    <div className="flex items-center gap-4">
                                    <FallbackImage src={pd.profile_picture} alt="Profile photo" kind="avatar" className="w-32 h-32 rounded-full object-cover border-2 border-gray-200 shadow-sm" />
                                        <div>
                                            <p className="text-sm text-gray-600">Profile photo on file</p>
                                            <a href={pd.profile_picture} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                                <i className="fas fa-external-link-alt text-xs"></i> View full size
                                            </a>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-3 text-gray-400 py-4">
                                        <div className="w-16 h-16 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center"><i className="fas fa-user text-2xl"></i></div>
                                        <p className="text-sm">No profile photo uploaded</p>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between p-4 space-x-2 border-t border-gray-200 bg-gray-50 shrink-0">
                        <div className="flex items-center space-x-2">
                            {canDelete && onDelete && (
                                <button
                                    onClick={onDelete}
                                    className="inline-flex items-center text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors"
                                >
                                    <i className="fas fa-trash mr-2"></i>Delete Officer
                                </button>
                            )}
                        </div>
                        <div className="flex items-center space-x-2">
                            {canManagePermissions && onManagePermissions && (
                                <button
                                    onClick={onManagePermissions}
                                    className="inline-flex items-center text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors"
                                >
                                    <i className="fas fa-user-shield mr-2"></i>Manage Permissions
                                </button>
                            )}
                            {canEdit && onEdit && (
                                <button
                                    onClick={() => onEdit(m)}
                                    className="inline-flex items-center text-white bg-cyan-600 hover:bg-cyan-700 focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors"
                                >
                                    <i className="fas fa-edit mr-2"></i>Edit Officer
                                </button>
                            )}
                            <button
                                onClick={onClose}
                                className="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 transition-colors"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function InfoField({ label, value, strong }: { label: string; value: any; strong?: boolean }) {
    return (
        <div>
            <span className="text-sm font-medium text-gray-600">{label}:</span>
            <p className={`text-gray-800 ${strong ? 'font-semibold' : 'font-medium'}`}>{value || 'N/A'}</p>
        </div>
    );
}
