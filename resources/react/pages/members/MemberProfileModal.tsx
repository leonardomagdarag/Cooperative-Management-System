import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'personal', label: 'Personal' },
    { key: 'address', label: 'Address' },
    { key: 'contact', label: 'Contact' },
    { key: 'ids', label: 'IDs' },
    { key: 'employment', label: 'Employment' },
    { key: 'beneficiaries', label: 'Beneficiaries' },
    { key: 'financial', label: 'Financial' },
    { key: 'documents', label: 'Documents' },
    { key: 'activity', label: 'Activity' },
];

const ID_ROWS = [
    { key: 'national_id', photo: 'national_id_photo', label: 'National ID (PhilSys)' },
    { key: 'tin', photo: 'tin_photo', label: 'TIN' },
    { key: 'sss', photo: 'sss_photo', label: 'SSS Number' },
    { key: 'philhealth', photo: 'philhealth_photo', label: 'PhilHealth Number' },
    { key: 'barangay_id', photo: 'barangay_id_photo', label: 'Barangay ID' },
];

const LOAN_STATUS_COLORS: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-emerald-100 text-emerald-700',
    active: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    closed: 'bg-gray-100 text-gray-600',
    paid: 'bg-gray-100 text-gray-600',
};

export default function MemberProfileModal({ memberId, onClose, onEdit, canEdit, canViewFullId, canApprove, onApprove, onDecline, isApproving, isDeclining }: { memberId: number; onClose: () => void; onEdit?: () => void; canEdit?: boolean; canViewFullId?: boolean; canApprove?: boolean; onApprove?: (id: number) => void; onDecline?: () => void; isApproving?: boolean; isDeclining?: boolean }) {
    const [activeTab, setActiveTab] = useState('overview');
    const [lightbox, setLightbox] = useState<string | null>(null);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['member', memberId],
        queryFn: () => api.get(`/api/members/${memberId}`),
    });

    const toggleMutation = useMutation({
        mutationFn: () => api.post(`/api/members/${memberId}/toggle-special-loan`),
        onSuccess: (res: any) => {
            queryClient.invalidateQueries({ queryKey: ['member', memberId] });
            addToast(res?.data?.special_loan_eligible ? 'Special loan eligibility enabled' : 'Special loan eligibility disabled', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update eligibility', 'error'),
    });

    const m = data?.data;

    const fmtDate = (d: string | undefined) => {
        if (!d) return 'N/A';
        const date = new Date(d);
        return isNaN(date.getTime()) ? 'N/A' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const peso = (n: any) => `₱${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const maskedId = (val: string | undefined) => {
        if (!val) return null;
        return `********${val.slice(-3)}`;
    };

    const isImageUrl = (url: string) => /\.(jpe?g|png|gif|webp)$/i.test(url);

    const beneficiaryAddress = (b: any) => {
        const parts = [b.street, b.barangay, b.city, b.province, b.postal_code].filter(Boolean);
        return parts.length ? parts.join(', ') : 'N/A';
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={onClose}>
            <div className="w-full max-w-3xl h-[90vh] rounded-2xl shadow-2xl bg-white overflow-hidden flex flex-col" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Member Profile</h2>
                        <button onClick={onClose} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white"><i className="fas fa-times"></i></button>
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
                                        {m.personal_data?.profile_picture ? (
                                            <img src={m.personal_data.profile_picture} alt="Profile" className="h-16 w-16 rounded-full object-cover border-2 border-emerald-200" />
                                        ) : (
                                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                                                <span className="text-2xl font-bold text-emerald-600">
                                                    {m.personal_data?.first_name?.charAt(0)}{m.personal_data?.last_name?.charAt(0)}
                                                </span>
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-lg font-semibold text-gray-900">{m.personal_data?.first_name} {m.personal_data?.mid_name ? m.personal_data.mid_name + ' ' : ''}{m.personal_data?.last_name}</p>
                                            <p className="text-sm text-gray-500">{m.user_number} &middot; {m.email}</p>
                                        </div>
                                    </div>
                                    {m.has_pending_application && (
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                            <p className="text-sm font-semibold text-amber-800">Pending membership application</p>
                                            <p className="mt-0.5 text-xs text-amber-700">Review this applicant's information below, then approve or decline their application.</p>
                                            {canApprove && (
                                                <div className="mt-3 flex items-center gap-2">
                                                    <button onClick={() => onApprove?.(m.id)} disabled={isApproving || isDeclining} className="px-3.5 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                                                        <i className="fas fa-check mr-1"></i>{isApproving ? 'Approving...' : 'Approve'}
                                                    </button>
                                                    <button onClick={onDecline} disabled={isApproving || isDeclining} className="px-3.5 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
                                                        <i className="fas fa-times mr-1"></i>{isDeclining ? 'Declining...' : 'Decline'}
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    <div className="grid grid-cols-2 gap-3">
                                        <InfoRow label="Account Status" value={<span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${m.archived_at ? 'bg-gray-100 text-gray-600' : m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{m.archived_at ? 'Archived' : m.account_status}</span>} />
                                        <InfoRow label="Primary Role" value={m.primary_role} />
                                        <InfoRow label="Gender" value={m.personal_data?.gender} />
                                        <InfoRow label="Civil Status" value={m.personal_data?.civil_status} />
                                        <InfoRow label="Birth Date" value={fmtDate(m.personal_data?.birth_date)} />
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
                                    <InfoRow label="ZIP Code" value={m.address?.postal_code} />
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
                                <div className="space-y-3">
                                    {ID_ROWS.map(row => {
                                        const fieldVal = m.member_ids?.[row.key];
                                        const photoVal = m.member_ids?.[row.photo];
                                        return (
                                            <div key={row.key} className="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3">
                                                <div className="min-w-0">
                                                    <p className="text-xs text-gray-500">{row.label}</p>
                                                    <p className="text-sm font-mono font-medium text-gray-900 mt-0.5">
                                                        {fieldVal ? (canViewFullId ? fieldVal : maskedId(fieldVal)) : <span className="text-gray-400 font-normal">N/A</span>}
                                                    </p>
                                                </div>
                                                {canViewFullId && (photoVal ? (
                                                    <img src={photoVal} alt={`${row.label} photo`} onClick={() => setLightbox(photoVal)} className="ml-3 h-10 w-16 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-90 transition-opacity" />
                                                ) : (
                                                    <div className="ml-3 flex h-10 w-16 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50">
                                                        <i className="fas fa-id-card text-gray-300"></i>
                                                    </div>
                                                ))}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {activeTab === 'employment' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <InfoRow label="Employment Type" value={m.employment?.employment_type} />
                                    <InfoRow label="Employer Name" value={m.employment?.company} />
                                    <InfoRow label="Position" value={m.employment?.position} />
                                    <InfoRow label="Monthly Income" value={m.employment?.monthly_income != null ? peso(m.employment.monthly_income) : 'N/A'} />
                                    {m.employment?.other_income_source && (
                                        <InfoRow label="Other Income" value={`${m.employment.other_income_source}${m.employment.other_income_amount != null ? ` (${peso(m.employment.other_income_amount)})` : ''}`} />
                                    )}
                                </div>
                            )}

                            {activeTab === 'beneficiaries' && (
                                <div className="space-y-3">
                                    {m.beneficiaries?.length > 0 ? m.beneficiaries.map((b: any) => (
                                        <div key={b.id} className="rounded-lg border border-gray-100 px-4 py-3">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <p className="font-medium text-gray-900">{b.first_name} {b.mid_name ? b.mid_name[0] + '. ' : ''}{b.last_name}</p>
                                                    <span className="mt-1 inline-block px-2 py-0.5 text-xs font-medium rounded bg-indigo-50 text-indigo-700">{b.relationship}</span>
                                                </div>
                                            </div>
                                            <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
                                                <InfoRow label="Date of Birth" value={fmtDate(b.birth_date)} />
                                                <InfoRow label="Contact Number" value={b.phone_no} />
                                            </div>
                                            <p className="mt-2 text-sm text-gray-600"><span className="text-xs text-gray-500">Address: </span>{beneficiaryAddress(b)}</p>
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
                                                            <p className="font-semibold text-gray-900">{peso(a.balance)}</p>
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
                                                                <p className="font-medium text-gray-900">{l.loan_type ? l.loan_type.charAt(0).toUpperCase() + l.loan_type.slice(1) : ''} Loan</p>
                                                                <p className="text-xs text-gray-500">Loan No: {l.loan_number}</p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-gray-900">{peso(l.loan_amount)}</p>
                                                                <span className={`mt-0.5 inline-block px-2 py-0.5 text-xs font-semibold rounded-full ${LOAN_STATUS_COLORS[l.loan_status] || 'bg-gray-100 text-gray-600'}`}>{l.loan_status}</span>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-1 text-sm text-gray-600">
                                                            {l.interest_rate != null && <span>{l.interest_rate}% &middot; {l.term_months} months</span>}
                                                            {l.applied_at && <span className="ml-auto">Applied {fmtDate(l.applied_at)}</span>}
                                                        </div>
                                                        {['approved', 'active'].includes(l.loan_status) && (
                                                            <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                                                                <div>
                                                                    <p className="text-xs text-gray-500">Remaining Balance</p>
                                                                    <p className="font-semibold text-red-600">{peso(l.remaining_balance)}</p>
                                                                </div>
                                                                <div className="text-right">
                                                                    <p className="text-xs text-gray-500">Due Date</p>
                                                                    <p className="text-sm font-medium text-gray-900">{fmtDate(l.due_date)}</p>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {(!m.accounts?.length && !m.loans?.length) && <p className="text-sm text-gray-500">No financial records.</p>}
                                </div>
                            )}

                            {activeTab === 'documents' && (
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="mb-3 text-sm font-semibold text-gray-700">Profile Photo</h3>
                                        {m.personal_data?.profile_picture ? (
                                            <div className="flex items-center gap-4">
                                                <img src={m.personal_data.profile_picture} alt="Profile photo" onClick={() => setLightbox(m.personal_data.profile_picture)} className="h-20 w-20 rounded-full object-cover border-2 border-gray-200 cursor-pointer hover:opacity-90 transition-opacity" />
                                                <a href={m.personal_data.profile_picture} target="_blank" rel="noreferrer" className="text-sm text-blue-600 hover:text-blue-800"><i className="fas fa-external-link-alt mr-1"></i>View full size</a>
                                            </div>
                                        ) : <p className="text-sm text-gray-500">No profile photo uploaded.</p>}
                                    </div>
                                    <div>
                                        <h3 className="mb-3 text-sm font-semibold text-gray-700">Government ID Photos</h3>
                                        {canViewFullId ? (
                                            ID_ROWS.some(row => m.member_ids?.[row.photo]) ? (
                                                <div className="grid grid-cols-1 gap-2">
                                                    {ID_ROWS.filter(row => m.member_ids?.[row.photo]).map(row => (
                                                        <div key={row.key} className="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3">
                                                            <p className="text-sm font-medium text-gray-700">{row.label}</p>
                                                            <img src={m.member_ids[row.photo]} alt={`${row.label} photo`} onClick={() => setLightbox(m.member_ids[row.photo])} className="h-12 w-20 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-90 transition-opacity" />
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-500">No ID photos uploaded.</p>
                                            )
                                        ) : (
                                            <p className="text-sm text-gray-500">ID photos require the members.view-full-details permission.</p>
                                        )}
                                    </div>
                                    <div>
                                        <h3 className="mb-3 text-sm font-semibold text-gray-700">Other Member Documents</h3>
                                        {(() => {
                                            const docs = [
                                                { label: 'Proof of Registration', path: m.member_ids?.agreement_url },
                                                { label: 'Other Supporting Document', path: m.member_ids?.other_document },
                                            ].filter(d => d.path);
                                            return docs.length > 0 ? (
                                                <div className="space-y-2">
                                                    {docs.map(doc => (
                                                        <div key={doc.label} className="flex items-center justify-between rounded-lg border border-gray-100 px-4 py-3">
                                                            <div className="flex min-w-0 items-center gap-3">
                                                                {isImageUrl(doc.path!) ? (
                                                                    <img src={doc.path} alt={doc.label} onClick={() => setLightbox(doc.path!)} className="h-12 w-16 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-90 transition-opacity" />
                                                                ) : (
                                                                    <div className="flex h-12 w-16 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-500"><i className="fas fa-file-alt"></i></div>
                                                                )}
                                                                <div className="min-w-0">
                                                                    <p className="text-sm font-medium text-gray-900">{doc.label}</p>
                                                                    <p className="truncate text-xs text-gray-500">{doc.path!.split('/').pop()}</p>
                                                                </div>
                                                            </div>
                                                            <a href={doc.path} target="_blank" rel="noreferrer" className="text-sm text-blue-600 hover:text-blue-800"><i className="fas fa-external-link-alt mr-1"></i>Open</a>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-500">No additional documents uploaded.</p>
                                            );
                                        })()}
                                    </div>
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

                <div className="flex items-center justify-between px-6 py-3 border-t border-gray-200 shrink-0">
                    <div className="flex items-center">
                        {canEdit && !m?.archived_at && (
                            <label className="inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={!!m?.special_loan_eligible}
                                    onChange={() => toggleMutation.mutate()}
                                    disabled={toggleMutation.isPending}
                                    className="sr-only peer"
                                />
                                <div className="relative w-10 h-5.5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-indigo-600"></div>
                                <span className="ms-3 text-sm font-medium text-gray-700">Special Loan Eligible</span>
                            </label>
                        )}
                        {canEdit && onEdit && !m?.archived_at && (
                            <button
                                onClick={onEdit}
                                className="ml-4 inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors"
                            >
                                <i className="fas fa-edit mr-1.5"></i>Edit Member
                            </button>
                        )}
                        {m?.archived_at && (
                            <span className="text-sm text-gray-500"><i className="fas fa-archive mr-1"></i>Archived member</span>
                        )}
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>

            {lightbox && (
                <div className="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4" onClick={() => setLightbox(null)}>
                    <img src={lightbox} alt="Preview full" className="max-w-full max-h-full rounded-lg shadow-2xl" onClick={e => e.stopPropagation()} />
                    <button onClick={() => setLightbox(null)} className="absolute top-4 right-4 text-white/80 hover:text-white text-2xl"><i className="fas fa-times"></i></button>
                </div>
            )}
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