import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'personal', label: 'Personal' },
    { key: 'address', label: 'Address' },
    { key: 'contact', label: 'Contact' },
    { key: 'ids', label: 'IDs' },
    { key: 'employment', label: 'Employment' },
    { key: 'beneficiaries', label: 'Beneficiaries' },
    { key: 'financial', label: 'Financial' },
    { key: 'activity', label: 'Activity' },
];

export default function MemberProfileModal({ memberId, onClose }: { memberId: number; onClose: () => void }) {
    const [activeTab, setActiveTab] = useState('overview');

    const { data, isLoading } = useQuery({
        queryKey: ['member', memberId],
        queryFn: () => api.get(`/api/members/${memberId}`),
    });

    const m = data?.data;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/25 p-4" onClick={onClose}>
            <div className="w-full max-w-3xl max-h-[90vh] rounded-2xl shadow-2xl bg-white overflow-hidden flex flex-col" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Member Profile</h2>
                        <button onClick={onClose} className="text-white/70 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                </div>

                <div className="overflow-x-auto border-b border-gray-200 shrink-0">
                    <div className="flex min-w-max px-6">
                        {TABS.map(tab => (
                            <button
                                key={tab.key}
                                onClick={() => setActiveTab(tab.key)}
                                className={`px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${activeTab === tab.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-6">
                    {isLoading ? (
                        <div className="space-y-3">{[...Array(6)].map((_, i) => <div key={i} className="h-10 animate-pulse rounded bg-gray-100" />)}</div>
                    ) : m ? (
                        <>
                            {activeTab === 'overview' && (
                                <div className="space-y-5">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                                            <span className="text-2xl font-bold text-emerald-600">
                                                {m.personal_data?.first_name?.charAt(0)}{m.personal_data?.last_name?.charAt(0)}
                                            </span>
                                        </div>
                                        <div>
                                            <p className="text-lg font-semibold text-gray-900">{m.personal_data?.first_name} {m.personal_data?.mid_name ? m.personal_data.mid_name + ' ' : ''}{m.personal_data?.last_name}</p>
                                            <p className="text-sm text-gray-500">{m.user_number} &middot; {m.email}</p>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <InfoRow label="Account Status" value={<span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{m.account_status}</span>} />
                                        <InfoRow label="Primary Role" value={m.primary_role} />
                                        <InfoRow label="Gender" value={m.personal_data?.gender} />
                                        <InfoRow label="Civil Status" value={m.personal_data?.civil_status} />
                                        <InfoRow label="Birth Date" value={m.personal_data?.birth_date} />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'personal' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <InfoRow label="First Name" value={m.personal_data?.first_name} />
                                    <InfoRow label="Middle Name" value={m.personal_data?.mid_name} />
                                    <InfoRow label="Last Name" value={m.personal_data?.last_name} />
                                    <InfoRow label="Suffix" value={m.personal_data?.suffix} />
                                    <InfoRow label="Birthplace" value={m.personal_data?.birthplace} />
                                    <InfoRow label="Religion" value={m.personal_data?.religion} />
                                    <InfoRow label="Education" value={m.personal_data?.education} />
                                    <InfoRow label="Title" value={m.personal_data?.title} />
                                </div>
                            )}

                            {activeTab === 'address' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <InfoRow label="Country" value={m.address?.country} />
                                    <InfoRow label="Region" value={m.address?.region} />
                                    <InfoRow label="Province" value={m.address?.province} />
                                    <InfoRow label="City" value={m.address?.city} />
                                    <InfoRow label="Barangay" value={m.address?.barangay} />
                                    <InfoRow label="Street" value={m.address?.street} />
                                    <InfoRow label="ZIP Code" value={m.address?.zip_code} />
                                </div>
                            )}

                            {activeTab === 'contact' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <InfoRow label="Phone" value={m.contact?.phone_no} />
                                    <InfoRow label="Email" value={m.contact?.email || m.email} />
                                    <InfoRow label="Facebook" value={m.contact?.facebook} />
                                </div>
                            )}

                            {activeTab === 'ids' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <InfoRow label="National ID" value={m.personal_data?.national_id} />
                                    <InfoRow label="TIN" value={m.personal_data?.tin} />
                                    <InfoRow label="SSS" value={m.personal_data?.sss} />
                                    <InfoRow label="PhilHealth" value={m.personal_data?.philhealth} />
                                    <InfoRow label="Barangay ID" value={m.personal_data?.barangay_id} />
                                </div>
                            )}

                            {activeTab === 'employment' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <InfoRow label="Employment Type" value={m.employment?.employment_type} />
                                    <InfoRow label="Employer Name" value={m.employment?.employer_name} />
                                    <InfoRow label="Position" value={m.employment?.position} />
                                    <InfoRow label="Monthly Income" value={m.employment?.monthly_income ? `₱${Number(m.employment.monthly_income).toLocaleString()}` : undefined} />
                                </div>
                            )}

                            {activeTab === 'beneficiaries' && (
                                <div className="space-y-3">
                                    {m.beneficiaries?.length > 0 ? m.beneficiaries.map((b: any) => (
                                        <div key={b.id} className="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3">
                                            <div>
                                                <p className="font-medium text-gray-900">{b.first_name} {b.last_name}</p>
                                                <p className="text-sm text-gray-500">{b.relationship}</p>
                                            </div>
                                            {b.percentage && <span className="text-sm font-medium text-indigo-600">{b.percentage}%</span>}
                                        </div>
                                    )) : <p className="text-sm text-gray-500">No beneficiaries.</p>}
                                </div>
                            )}

                            {activeTab === 'financial' && (
                                <div className="space-y-6">
                                    {m.accounts?.length > 0 && (
                                        <div>
                                            <h3 className="mb-3 text-sm font-semibold text-gray-700">Accounts</h3>
                                            <div className="space-y-2">
                                                {m.accounts.map((a: any) => (
                                                    <div key={a.id} className="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3">
                                                        <div>
                                                            <p className="font-medium text-gray-900">{a.account_type || a.type}</p>
                                                            {a.account_number && <p className="text-xs text-gray-500">{a.account_number}</p>}
                                                        </div>
                                                        <div className="text-right">
                                                            <p className="font-semibold text-gray-900">₱{Number(a.balance).toLocaleString()}</p>
                                                            {a.status && <p className="text-xs text-gray-500">{a.status}</p>}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {m.loans?.length > 0 && (
                                        <div>
                                            <h3 className="mb-3 text-sm font-semibold text-gray-700">Loans</h3>
                                            <div className="space-y-2">
                                                {m.loans.map((l: any) => (
                                                    <div key={l.id} className="rounded-lg border border-gray-100 px-4 py-3 space-y-2">
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <p className="font-medium text-gray-900">{l.loan_type || l.type}</p>
                                                                <p className="text-xs text-gray-500">{l.loan_number}</p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-gray-900">₱{Number(l.amount).toLocaleString()}</p>
                                                                <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${l.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{l.status}</span>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                                                <div className="h-full rounded-full bg-indigo-500" style={{ width: `${l.progress || 0}%` }} />
                                                            </div>
                                                            <span className="text-xs text-gray-500">{l.progress || 0}%</span>
                                                        </div>
                                                        {l.remaining_balance !== undefined && (
                                                            <p className="text-xs text-gray-500">Remaining: ₱{Number(l.remaining_balance).toLocaleString()}</p>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {(!m.accounts?.length && !m.loans?.length) && <p className="text-sm text-gray-500">No financial records.</p>}
                                </div>
                            )}

                            {activeTab === 'activity' && (
                                <div className="space-y-2">
                                    {m.recent_logs?.length > 0 ? m.recent_logs.map((log: any) => (
                                        <div key={log.id} className="flex items-start justify-between rounded-lg border border-gray-100 px-4 py-3">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{log.description}</p>
                                                {log.event && (
                                                    <span className="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600">
                                                        {log.event}
                                                    </span>
                                                )}
                                            </div>
                                            <span className="text-xs text-gray-400 ml-3 whitespace-nowrap">{new Date(log.created_at).toLocaleDateString()}</span>
                                        </div>
                                    )) : <p className="text-sm text-gray-500">No recent activity.</p>}
                                </div>
                            )}
                        </>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <p className="text-xs text-gray-500">{label}</p>
            <p className="text-sm font-medium text-gray-900">{value || '\u2014'}</p>
        </div>
    );
}
