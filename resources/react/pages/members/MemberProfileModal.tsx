import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'contact', label: 'Contact & IDs' },
    { key: 'financial', label: 'Financial' },
    { key: 'beneficiaries', label: 'Beneficiaries' },
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

interface MemberProfileModalProps {
    memberId: number;
    onClose: () => void;
    onEdit?: () => void;
    canEdit?: boolean;
    canViewIds?: boolean;
    canViewLoans?: boolean;
    canViewActivity?: boolean;
    canViewFinancial?: boolean;
    canApprove?: boolean;
    onApprove?: (id: number) => void;
    onDecline?: () => void;
    isApproving?: boolean;
    isDeclining?: boolean;
    isAdmin?: boolean;
    onRestore?: () => void;
    onForceDelete?: () => void;
    isRestoring?: boolean;
    isForceDeleting?: boolean;
}

export default function MemberProfileModal({ memberId, onClose, onEdit, canEdit, canViewIds, canViewLoans, canViewActivity, canViewFinancial, canApprove, onApprove, onDecline, isApproving, isDeclining, isAdmin, onRestore, onForceDelete, isRestoring, isForceDeleting }: MemberProfileModalProps) {
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

    if (isLoading) {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div className="w-full max-w-6xl rounded-lg bg-white p-10 shadow">
                    <div className="space-y-3">{[...Array(6)].map((_, i) => <div key={i} className="h-10 animate-pulse rounded bg-gray-100" />)}</div>
                </div>
            </div>
        );
    }

    if (!m) {
        return null;
    }

    const pd = m.personal_data || {};
    const fullName = [pd.first_name, pd.mid_name ? (pd.mid_name[0] + '.') : '', pd.last_name, pd.suffix].filter(Boolean).join(' ') || (m.name || 'N/A');

    const fmtDate = (d: string | undefined) => {
        if (!d) return 'N/A';
        const date = new Date(d);
        return isNaN(date.getTime()) ? 'N/A' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const peso = (n: any) => `₱${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const calcAge = (d: string) => {
        if (!d) return 'N/A';
        const bd = new Date(d);
        if (isNaN(bd.getTime())) return 'N/A';
        const diff = Date.now() - bd.getTime();
        return `${Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000))} years old`;
    };

    const maskedId = (val: string | undefined) => {
        if (!val) return null;
        return `********${val.slice(-3)}`;
    };

    const isImageUrl = (url: string) => /\.(jpe?g|png|gif|webp)$/i.test(url);

    const beneficiaryAddress = (b: any) => {
        const parts = [b.street, b.barangay, b.city, b.province, b.postal_code].filter(Boolean);
        return parts.length ? parts.join(', ') : 'N/A';
    };

    const savingsAccount = m.accounts?.find((a: any) => a.account_type === 'savings');
    const shareCapitalAccount = m.accounts?.find((a: any) => a.account_type === 'share-capital');
    const activeLoans = (m.loans || []).filter((l: any) => ['pending', 'approved', 'active'].includes(l.loan_status));

    const statusColor = m.archived_at
        ? 'bg-gray-100 text-gray-600 border-gray-200'
        : m.has_pending_application
            ? 'bg-amber-100 text-amber-800 border-amber-200'
            : m.account_status === 'active'
                ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                : m.account_status === 'terminated'
                    ? 'bg-red-100 text-red-800 border-red-200'
                    : 'bg-gray-100 text-gray-600 border-gray-200';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" onClick={onClose}>
            <div className="relative w-full max-w-6xl max-h-[90vh] mx-auto my-6" onClick={e => e.stopPropagation()}>
                <div className="relative flex max-h-[90vh] flex-col rounded-lg bg-white shadow overflow-hidden">
                    <div className="flex items-center justify-between border-b border-gray-200 bg-gradient-to-r from-cyan-600 to-emerald-600 p-5 shrink-0">
                        <h3 className="text-xl font-semibold text-white">Member Profile</h3>
                        <button onClick={onClose} className="inline-flex items-center rounded-lg p-1.5 text-white bg-transparent hover:bg-cyan-800 transition-colors">
                            <i className="fas fa-times text-lg"></i>
                        </button>
                    </div>

                    <div className="border-b border-gray-200 bg-gray-50 shrink-0">
                        <div className="flex space-x-1 px-6 overflow-x-auto">
                            {TABS.filter(t => t.key !== 'activity' || canViewActivity).map(tab => (
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
                            <div className="flex flex-col gap-6">
                                {m.has_pending_application && (
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                        <p className="text-sm font-semibold text-amber-800"><i className="fas fa-hourglass-half mr-1.5"></i>Pending membership application</p>
                                        <p className="mt-0.5 text-xs text-amber-700">Review this applicant's information below, then approve or decline their application.</p>
                                        {canApprove && (
                                            <div className="mt-3 flex items-center gap-2">
                                                <button onClick={() => onApprove?.(m.id)} disabled={isApproving || isDeclining} className="inline-flex items-center px-4 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                                                    <i className="fas fa-check mr-1.5"></i>{isApproving ? 'Approving...' : 'Approve'}
                                                </button>
                                                <button onClick={onDecline} disabled={isApproving || isDeclining} className="inline-flex items-center px-4 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
                                                    <i className="fas fa-times mr-1.5"></i>{isDeclining ? 'Declining...' : 'Decline'}
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="flex flex-col gap-6 md:flex-row">
                                    <div className="w-full md:w-64 flex-shrink-0">
                                        {pd.profile_picture ? (
                                            <img src={pd.profile_picture} alt="Profile" className="w-full h-64 rounded-lg shadow-lg object-cover border-4 border-cyan-100" />
                                        ) : (
                                            <div className="w-full h-64 rounded-lg shadow-lg bg-gradient-to-br from-cyan-100 to-emerald-100 flex items-center justify-center border-4 border-cyan-100">
                                                <svg className="w-32 h-32 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                        )}
                                        <div className="mt-4 text-center">
                                            <h4 className="text-xl font-bold text-gray-800">{fullName}</h4>
                                            <p className="text-cyan-600 font-semibold mt-1">Member</p>
                                            <p className="text-sm text-gray-600 mt-1">ID: {m.user_number}</p>
                                            <span className={`inline-block mt-3 px-3 py-1 text-xs font-semibold rounded-full border-2 ${statusColor}`}>
                                                {m.archived_at ? 'ARCHIVED' : m.has_pending_application ? 'PENDING APPROVAL' : (m.account_status || 'INACTIVE').toUpperCase()}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex-1">
                                        <SectionTitle>Personal Information</SectionTitle>
                                        <div className="grid grid-cols-2 gap-x-6 gap-y-4">
                                            <Field label="Date of Birth" value={fmtDate(pd.birth_date)} />
                                            <Field label="Place of Birth" value={pd.birthplace} />
                                            <Field label="Gender" value={pd.gender ? pd.gender.charAt(0).toUpperCase() + pd.gender.slice(1) : 'N/A'} />
                                            <Field label="Civil Status" value={pd.civil_status ? pd.civil_status.charAt(0).toUpperCase() + pd.civil_status.slice(1) : 'N/A'} />
                                            <Field label="Religion" value={pd.religion} />
                                            <Field label="Education" value={pd.education} />
                                        </div>

                                        <div className="mt-6">
                                            <SectionTitle>Address</SectionTitle>
                                            <div className="grid grid-cols-2 gap-x-6 gap-y-4">
                                                <div className="col-span-2"><Field label="Street Address" value={[m.address?.street, m.address?.barangay, m.address?.city, m.address?.province].filter(Boolean).join(', ') || 'N/A'} /></div>
                                                <Field label="Barangay" value={m.address?.barangay} />
                                                <Field label="City/Municipality" value={m.address?.city} />
                                                <Field label="Province" value={m.address?.province} />
                                                <Field label="Postal Code" value={m.address?.postal_code} />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'contact' && (
                            <div className="space-y-6">
                                <div>
                                    <SectionTitle>Contact Information</SectionTitle>
                                    <div className="grid grid-cols-2 gap-6">
                                        <Field label="Email Address" value={m.contact?.email || m.email} />
                                        <Field label="Phone Number" value={m.contact?.phone_no} />
                                        <Field label="Facebook" value={m.contact?.facebook} />
                                    </div>
                                </div>

                                <div>
                                    <SectionTitle>Government ID Numbers</SectionTitle>
                                    <div className="grid grid-cols-1 gap-3">
                                        {ID_ROWS.map(row => {
                                            const fieldVal = m.member_ids?.[row.key];
                                            const photoVal = m.member_ids?.[row.photo];
                                            return (
                                                <div key={row.key} className="grid grid-cols-2 gap-4 items-center p-4 bg-gray-50 rounded-lg">
                                                    <div>
                                                        <span className="text-sm font-medium text-gray-600">{row.label}:</span>
                                                        <p className="text-gray-800 font-mono font-medium mt-1">
                                                            {fieldVal ? (canViewIds ? fieldVal : maskedId(fieldVal)) : <span className="text-gray-400 font-normal">N/A</span>}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center justify-end">
                                                        {canViewIds && photoVal ? (
                                                            <img
                                                                src={photoVal}
                                                                alt={`${row.label} photo`}
                                                                onClick={() => setLightbox(photoVal)}
                                                                className="h-14 w-24 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity shadow-sm"
                                                            />
                                                        ) : canViewIds ? (
                                                            <div className="h-14 w-24 rounded-lg bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center">
                                                                <i className="fas fa-id-card text-gray-300 text-xl"></i>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {m.employment && (
                                    <div>
                                        <SectionTitle>Employment Information</SectionTitle>
                                        <div className="grid grid-cols-2 gap-6">
                                            <Field label="Employer Name" value={m.employment.company} />
                                            <Field label="Position" value={m.employment.position} />
                                            <Field label="Employment Type" value={m.employment.employment_type ? m.employment.employment_type.charAt(0).toUpperCase() + m.employment.employment_type.slice(1) : 'N/A'} />
                                            <Field label="Monthly Income" value={m.employment.monthly_income != null ? peso(m.employment.monthly_income) : 'N/A'} />
                                            {m.employment.other_income_source && (
                                                <Field label="Other Income Source" value={`${m.employment.other_income_source}${m.employment.other_income_amount != null ? ` (${peso(m.employment.other_income_amount)})` : ''}`} />
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {activeTab === 'financial' && (
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <AccountCard
                                        title="Savings Account"
                                        icon={
                                            <svg className="w-8 h-8 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                                <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd" />
                                            </svg>
                                        }
                                        gradient="from-cyan-50 to-cyan-100"
                                        border="border-cyan-200"
                                        text="text-cyan-700"
                                        account={savingsAccount}
                                        balanceLabel="Available Balance"
                                        hideBalance={!canViewFinancial}
                                    />
                                    <AccountCard
                                        title="Share Capital"
                                        icon={
                                            <svg className="w-8 h-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" />
                                            </svg>
                                        }
                                        gradient="from-emerald-50 to-emerald-100"
                                        border="border-emerald-200"
                                        text="text-emerald-700"
                                        account={shareCapitalAccount}
                                        balanceLabel="Share Capital Balance"
                                        hideBalance={!canViewFinancial}
                                    />
                                </div>

                                <div>
                                    <SectionTitle>Current & Past Loans</SectionTitle>
                                    {activeLoans.length > 0 ? (
                                        <div className="space-y-4">
                                            {activeLoans.map((l: any) => (
                                                <div key={l.id} className="bg-white border-2 border-gray-200 rounded-lg p-5 hover:shadow-md transition-shadow">
                                                    <div className="flex items-start justify-between mb-3">
                                                        <div>
                                                            <h6 className="font-semibold text-gray-800">{l.loan_type ? l.loan_type.charAt(0).toUpperCase() + l.loan_type.slice(1) : ''} Loan</h6>
                                                            <p className="text-sm text-gray-600">Loan No: <span className="font-mono">{l.loan_number}</span></p>
                                                        </div>
                                                        <span className={`px-3 py-1 text-xs font-semibold rounded-full ${l.loan_status === 'approved' || l.loan_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
                                                            {l.loan_status.charAt(0).toUpperCase() + l.loan_status.slice(1)}
                                                        </span>
                                                    </div>
                                                    <div className="grid grid-cols-3 gap-4 mt-4">
                                                        <div>
                                                            <span className="text-xs text-gray-600">Principal Amount</span>
                                                            <p className="text-lg font-bold text-gray-800">{canViewLoans ? peso(l.loan_amount) : '₱***.**'}</p>
                                                        </div>
                                                        <div>
                                                            <span className="text-xs text-gray-600">Interest Rate</span>
                                                            <p className="text-lg font-bold text-gray-800">{l.interest_rate}%</p>
                                                        </div>
                                                        <div>
                                                            <span className="text-xs text-gray-600">Term</span>
                                                            <p className="text-lg font-bold text-gray-800">{l.term_months} months</p>
                                                        </div>
                                                    </div>
                                                    {(l.loan_status === 'approved' || l.loan_status === 'active') && (
                                                        <div className="mt-4 pt-4 border-t border-gray-200">
                                                            <div className="flex justify-between items-center">
                                                                <div>
                                                                    <span className="text-xs text-gray-600">Remaining Balance</span>
                                                                    <p className="text-xl font-bold text-red-600">{peso(l.remaining_balance)}</p>
                                                                </div>
                                                                <div className="text-right">
                                                                    <span className="text-xs text-gray-600">Due Date</span>
                                                                    <p className="text-sm font-semibold text-gray-800">{fmtDate(l.due_date)}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-12 bg-gray-50 rounded-lg">
                                            <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p className="text-gray-600">No active loans</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {activeTab === 'beneficiaries' && (
                            <div>
                                <SectionTitle>Beneficiaries</SectionTitle>
                                {m.beneficiaries?.length > 0 ? (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {m.beneficiaries.map((b: any) => (
                                            <div key={b.id} className="bg-gradient-to-r from-cyan-50 to-emerald-50 p-5 rounded-lg border-2 border-cyan-200 hover:shadow-md transition-shadow">
                                                <div className="flex items-start justify-between mb-4">
                                                    <div className="flex-1">
                                                        <h6 className="text-lg font-bold text-gray-800">
                                                            {[b.first_name, b.mid_name ? b.mid_name[0] + '.' : '', b.last_name].filter(Boolean).join(' ')}
                                                        </h6>
                                                        <span className="inline-block mt-1 px-2 py-1 text-xs font-semibold bg-cyan-100 text-cyan-800 rounded">{b.relationship?.charAt(0).toUpperCase() + b.relationship?.slice(1)}</span>
                                                    </div>
                                                </div>
                                                <div className="space-y-3">
                                                    <BeneField label="Date of Birth" value={fmtDate(b.birth_date)} />
                                                    <BeneField label="Age" value={calcAge(b.birth_date)} />
                                                    <BeneField label="Contact Number" value={b.phone_no} />
                                                    <div className="pt-3 border-t border-cyan-200">
                                                        <span className="text-xs font-medium text-gray-600">Address:</span>
                                                        <p className="text-sm font-semibold text-gray-800">{beneficiaryAddress(b)}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-center py-16 bg-gray-50 rounded-lg">
                                        <svg className="w-20 h-20 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <p className="text-gray-600 text-lg font-medium">No beneficiaries listed</p>
                                        <p className="text-gray-500 text-sm mt-2">This member has not added any beneficiaries yet</p>
                                    </div>
                                )}
                            </div>
                        )}

                        {activeTab === 'documents' && (
                            <div className="space-y-6">
                                <div>
                                    <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Profile Photo</h4>
                                    {pd.profile_picture ? (
                                        <div className="flex items-center gap-4">
                                            <img src={pd.profile_picture} alt="Profile photo" className="w-32 h-32 rounded-full object-cover border-2 border-gray-200 shadow-sm" />
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

                                {canViewIds && (
                                    <div>
                                        <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Government ID Photos</h4>
                                        {ID_ROWS.some(row => m.member_ids?.[row.photo]) ? (
                                            <div className="grid grid-cols-1 gap-3">
                                                {ID_ROWS.filter(row => m.member_ids?.[row.photo]).map(row => (
                                                    <div key={row.key} className="grid grid-cols-2 gap-4 items-center p-3 bg-gray-50 rounded-lg">
                                                        <p className="text-sm font-medium text-gray-700">{row.label}</p>
                                                        <div className="flex items-center gap-3">
                                                            <img
                                                                src={m.member_ids[row.photo]}
                                                                alt={`${row.label} photo`}
                                                                onClick={() => setLightbox(m.member_ids[row.photo])}
                                                                className="h-14 w-24 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity shadow-sm"
                                                            />
                                                            <a href={m.member_ids[row.photo]} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:text-blue-800">
                                                                <i className="fas fa-external-link-alt"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-3 text-gray-400 py-4">
                                                <div className="w-20 h-14 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center"><i className="fas fa-id-card text-2xl"></i></div>
                                                <p className="text-sm">No ID photos uploaded</p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div>
                                    <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Other Member Documents</h4>
                                    {(() => {
                                        const docs = [
                                            { label: 'Proof of Registration', path: m.member_ids?.agreement_url },
                                            { label: 'Other Supporting Document', path: m.member_ids?.other_document },
                                        ].filter(d => d.path);
                                        return docs.length > 0 ? (
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                {docs.map(doc => (
                                                    <div key={doc.label} className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                                        <div className="flex items-start gap-4">
                                                            {isImageUrl(doc.path!) ? (
                                                                <img
                                                                    src={doc.path}
                                                                    alt={doc.label}
                                                                    onClick={() => setLightbox(doc.path!)}
                                                                    className="h-20 w-20 rounded-lg object-cover border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity"
                                                                />
                                                            ) : (
                                                                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-500">
                                                                    <svg className="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z" />
                                                                    </svg>
                                                                </div>
                                                            )}
                                                            <div className="min-w-0 flex-1">
                                                                <p className="text-sm font-medium text-gray-800">{doc.label}</p>
                                                                <p className="mt-1 truncate text-xs text-gray-500">{doc.path!.split('/').pop()}</p>
                                                                <a href={doc.path} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                                                    <i className="fas fa-external-link-alt text-xs"></i> Open document
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-3 text-gray-400 py-4">
                                                <div className="w-20 h-14 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center"><i className="fas fa-file-alt text-2xl"></i></div>
                                                <p className="text-sm">No additional documents uploaded</p>
                                            </div>
                                        );
                                    })()}
                                </div>
                            </div>
                        )}

                        {activeTab === 'activity' && (
                            <div>
                                <SectionTitle>Recent Activity</SectionTitle>
                                {m.recent_logs?.length > 0 ? (
                                    <div className="space-y-3">
                                        {m.recent_logs.map((log: any) => (
                                            <div key={log.id} className="flex items-start justify-between p-4 bg-gray-50 rounded-lg">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-medium text-gray-800">{log.description}</p>
                                                    {log.event && (
                                                        <span className="inline-block mt-1 px-2 py-1 text-xs font-semibold bg-cyan-100 text-cyan-800 rounded">{log.event}</span>
                                                    )}
                                                </div>
                                                <span className="ml-3 whitespace-nowrap text-xs text-gray-500">{fmtDate(log.created_at)}</span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-gray-500 text-sm">No recent activity.</p>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between p-4 space-x-2 border-t border-gray-200 bg-gray-50 shrink-0">
                        <div className="flex items-center">
                            {m.archived_at ? (
                                <span className="text-sm text-gray-500"><i className="fas fa-archive mr-1"></i>Archived member</span>
                            ) : (
                                canEdit && (
                                    <label className="inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={!!m.special_loan_eligible}
                                            onChange={() => toggleMutation.mutate()}
                                            disabled={toggleMutation.isPending}
                                            className="sr-only peer"
                                        />
                                        <div className="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                                        <span className="ms-3 text-sm font-medium text-gray-700">Special Loan Eligible</span>
                                    </label>
                                )
                            )}
                        </div>

                        <div className="flex items-center space-x-2">
                            {m.archived_at && isAdmin && (
                                <>
                                    <button
                                        onClick={onRestore}
                                        disabled={isRestoring}
                                        className="inline-flex items-center text-white bg-emerald-600 hover:bg-emerald-700 focus:ring-4 focus:outline-none focus:ring-emerald-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors disabled:opacity-50"
                                    >
                                        <i className="fas fa-undo mr-2"></i>{isRestoring ? 'Restoring...' : 'Restore'}
                                    </button>
                                    <button
                                        onClick={onForceDelete}
                                        disabled={isForceDeleting}
                                        className="inline-flex items-center text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors disabled:opacity-50"
                                    >
                                        <i className="fas fa-trash mr-2"></i>{isForceDeleting ? 'Deleting...' : 'Permanent Delete'}
                                    </button>
                                </>
                            )}
                            {canEdit && onEdit && !m.archived_at && (
                                <button
                                    onClick={onEdit}
                                    className="inline-flex items-center text-white bg-cyan-600 hover:bg-cyan-700 focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors"
                                >
                                    <i className="fas fa-edit mr-2"></i>Edit Member
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

            {lightbox && (
                <div className="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4" onClick={() => setLightbox(null)}>
                    <img src={lightbox} alt="Preview full" className="max-w-full max-h-full rounded-lg shadow-2xl" onClick={e => e.stopPropagation()} />
                    <button onClick={() => setLightbox(null)} className="absolute top-4 right-4 text-white/80 hover:text-white text-2xl"><i className="fas fa-times"></i></button>
                </div>
            )}
        </div>
    );
}

function SectionTitle({ children }: { children: React.ReactNode }) {
    return <h5 className="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">{children}</h5>;
}

function Field({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <span className="text-sm font-medium text-gray-600">{label}:</span>
            <p className="text-gray-800 font-medium">{value || 'N/A'}</p>
        </div>
    );
}

function BeneField({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <span className="text-xs font-medium text-gray-600">{label}:</span>
            <p className="text-sm font-semibold text-gray-800">{value}</p>
        </div>
    );
}

function AccountCard({ title, icon, gradient, border, text, account, balanceLabel, hideBalance }: { title: string; icon: React.ReactNode; gradient: string; border: string; text: string; account: any; balanceLabel: string; hideBalance?: boolean }) {
    return (
        <div className={`bg-gradient-to-br ${gradient} p-6 rounded-xl shadow-md border-2 ${border}`}>
            <div className="flex items-center justify-between mb-4">
                <h5 className="text-lg font-semibold text-gray-800">{title}</h5>
                {icon}
            </div>
            {account ? (
                <>
                    <p className="text-sm text-gray-600 mb-2">Account Number: <span className="font-mono font-medium">{account.account_number}</span></p>
                    {hideBalance ? (
                        <p className={`text-3xl font-bold ${text}`}>₱***.**</p>
                    ) : (
                        <p className={`text-3xl font-bold ${text}`}>₱{Number(account.balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    )}
                    <p className="text-xs text-gray-600 mt-2">{balanceLabel}</p>
                </>
            ) : (
                <p className="text-gray-600 italic">No {title.toLowerCase()} account</p>
            )}
        </div>
    );
}