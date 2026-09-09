import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import Modal from '../../components/ui/Modal';
import ModalHeader from '../../components/ui/ModalHeader';
import ModalTabs, { type TabItem } from '../../components/ui/ModalTabs';
import { api } from '../../api/client';

const TABS: TabItem[] = [
    { key: 'overview', label: 'Overview', icon: 'fas fa-user' },
    { key: 'contact', label: 'Contact & IDs', icon: 'fas fa-address-card' },
    { key: 'documents', label: 'Documents', icon: 'fas fa-folder-open' },
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

    const m = data?.data;

    const pd = m?.personal_data || {};
    const fullName = [pd.first_name, pd.mid_name ? (pd.mid_name[0] + '.') : '', pd.last_name, pd.suffix].filter(Boolean).join(' ') || officer.name || 'N/A';
    const primaryRole = m?.primary_role || officer.primary_role || 'officer';
    const roleColor = ROLE_COLORS[primaryRole] || ROLE_COLORS.officer;
    const chips = ROLE_CHIPS[primaryRole] || ROLE_CHIPS.default;
    const chipColor = ROLE_CHIP_COLORS[primaryRole] || ROLE_CHIP_COLORS.default;

    const fmtDate = (d: string | undefined) => {
        if (!d) return 'N/A';
        const date = new Date(d);
        return isNaN(date.getTime()) ? 'N/A' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="6xl"
            fixed
            header={
                <ModalHeader title="Officer Profile" subtitle={m ? `ID: ${m.user_number}` : undefined} onClose={onClose} gradientClassName="from-cyan-600 to-emerald-600" />
            }
            footer={
                <div className="flex w-full flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        {canDelete && onDelete && (
                            <button
                                onClick={onDelete}
                                className="inline-flex items-center rounded-lg bg-red-600 px-5 py-2.5 text-center text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300"
                            >
                                <i className="fas fa-trash mr-2"></i>Delete Officer
                            </button>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canManagePermissions && onManagePermissions && (
                            <button
                                onClick={onManagePermissions}
                                className="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-center text-sm font-medium text-white transition-colors hover:bg-purple-700 focus:outline-none focus:ring-4 focus:ring-purple-300"
                            >
                                <i className="fas fa-user-shield mr-2"></i>Manage Permissions
                            </button>
                        )}
                        {canEdit && onEdit && (
                            <button
                                onClick={() => onEdit(m)}
                                className="inline-flex items-center rounded-lg bg-cyan-600 px-5 py-2.5 text-center text-sm font-medium text-white transition-colors hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300"
                            >
                                <i className="fas fa-edit mr-2"></i>Edit Officer
                            </button>
                        )}
                        <button
                            onClick={onClose}
                            className="inline-flex items-center rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:z-10 focus:outline-none focus:ring-4 focus:ring-gray-200"
                        >
                            Close
                        </button>
                    </div>
                </div>
            }
            bodyClassName="p-0"
        >
            <div className="flex h-full flex-col">
                <ModalTabs
                    tabs={TABS}
                    activeTab={activeTab}
                    onChange={setActiveTab}
                    activeClassName="text-cyan-600 border-b-2 border-cyan-600 bg-white"
                    inactiveClassName="text-gray-600 hover:text-gray-800 hover:border-gray-300"
                />
                <div className="flex-1 overflow-y-auto p-6">
                    {isLoading ? (
                        <div className="space-y-3">{[...Array(6)].map((_, i) => <div key={i} className="h-10 animate-pulse rounded bg-gray-100" />)}</div>
                    ) : m ? (
                        <>
                            {activeTab === 'overview' && (
                                <div className="flex flex-col gap-6 md:flex-row">
                                    <div className="w-full flex-shrink-0 md:w-64">
                                        {pd.profile_picture ? (
                                            <img src={pd.profile_picture} alt="Profile" className="h-64 w-full rounded-lg border-4 border-cyan-100 object-cover shadow-lg" />
                                        ) : (
                                            <div className="flex h-64 w-full items-center justify-center rounded-lg border-4 border-cyan-100 bg-gradient-to-br from-cyan-100 to-emerald-100 shadow-lg">
                                                <svg className="h-32 w-32 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                        )}
                                        <div className="mt-4 text-center">
                                            <h4 className="text-xl font-bold text-gray-800">{fullName}</h4>
                                            <p className="mt-1 font-semibold text-cyan-600">{pd.title ? pd.title.charAt(0).toUpperCase() + pd.title.slice(1) : 'Officer'}</p>
                                            <p className="mt-1 text-sm text-gray-600">ID: {m.user_number}</p>
                                            <span className={`mt-3 inline-block px-3 py-1 text-xs font-semibold rounded-full border-2 ${roleColor}`}>
                                                {primaryRole.toUpperCase()}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex-1">
                                        <h5 className="mb-4 border-b-2 border-cyan-100 text-lg font-semibold text-gray-800 pb-2">Personal Information</h5>
                                        <div className="grid grid-cols-2 gap-x-6 gap-y-4">
                                            <InfoField label="Date of Birth" value={fmtDate(pd.birth_date)} />
                                            <InfoField label="Place of Birth" value={pd.birthplace} />
                                            <InfoField label="Gender" value={pd.gender ? pd.gender.charAt(0).toUpperCase() + pd.gender.slice(1) : 'N/A'} />
                                            <InfoField label="Civil Status" value={pd.civil_status ? pd.civil_status.charAt(0).toUpperCase() + pd.civil_status.slice(1) : 'N/A'} />
                                            <InfoField label="Religion" value={pd.religion} />
                                            <InfoField label="Education" value={pd.education} />
                                        </div>

                                        <h5 className="mt-6 mb-4 border-b-2 border-cyan-100 text-lg font-semibold text-gray-800 pb-2">Role & Permissions</h5>
                                        <div className="rounded-lg border border-cyan-200 bg-gradient-to-r from-cyan-50 to-emerald-50 p-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <InfoField label="System Role" value={primaryRole.charAt(0).toUpperCase() + primaryRole.slice(1)} strong />
                                                <InfoField label="Position" value={pd.title ? pd.title.charAt(0).toUpperCase() + pd.title.slice(1) : 'N/A'} strong />
                                            </div>
                                            <div className="mt-4">
                                                <span className="mb-2 block text-sm font-medium text-gray-600">Permissions:</span>
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
                                    <h5 className="mb-4 border-b-2 border-cyan-100 text-lg font-semibold text-gray-800 pb-2">Contact Information</h5>
                                    <div className="grid grid-cols-2 gap-6">
                                        <InfoField label="Email Address" value={m.contact?.email || m.email} />
                                        <InfoField label="Phone Number" value={m.contact?.phone_no} />
                                        <InfoField label="Facebook" value={m.contact?.facebook} />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'documents' && (
                                <div>
                                    <h4 className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-700">Profile Photo</h4>
                                    {pd.profile_picture ? (
                                        <div className="flex items-center gap-4">
                                            <img src={pd.profile_picture} alt="Profile photo" className="h-32 w-32 rounded-full border-2 border-gray-200 object-cover shadow-sm" />
                                            <div>
                                                <p className="text-sm text-gray-600">Profile photo on file</p>
                                                <a href={pd.profile_picture} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                                    <i className="fas fa-external-link-alt text-xs"></i> View full size
                                                </a>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-3 py-4 text-gray-400">
                                            <div className="flex h-16 w-16 items-center justify-center rounded-full border border-gray-200 bg-gray-100"><i className="fas fa-user text-2xl"></i></div>
                                            <p className="text-sm">No profile photo uploaded</p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </>
                    ) : null}
                </div>
            </div>
        </Modal>
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