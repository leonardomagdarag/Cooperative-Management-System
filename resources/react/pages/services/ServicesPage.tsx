import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { getServiceAvailability, type ServiceAvailabilityData } from '../../api/settings';
import DepositWizard from './DepositWizard';
import WithdrawalWizard from './WithdrawalWizard';
import LoanApplicationWizard from './LoanApplicationWizard';
import LoanPaymentWizard from './LoanPaymentWizard';
import TransactionApproval from './TransactionApproval';
import ServiceMaintenanceModal from '../../components/ServiceMaintenanceModal';

type ServiceType = 'deposit' | 'withdraw' | 'loan' | 'payment' | 'approve' | 'loan-review' | null;

const serviceLabelMap: Record<string, string> = {
    deposit: 'Deposits', withdrawal: 'Withdrawals', loan_application: 'Loan Applications', loan_payment: 'Loan Payments',
};

export default function ServicesPage() {
    const queryClient = useQueryClient();
    const { user } = useAuth();
    const perms = user?.permissions || [];
    const isOfficer = user?.primary_role !== 'member' && user?.primary_role !== null;
    const canApprove = perms.includes('transactions.approve') || perms.includes('deposits.approve') || perms.includes('withdrawals.approve');
    const canReviewLoans = perms.includes('loans.update');

    const [activeService, setActiveService] = useState<ServiceType>(null);
    const [memberSearch, setMemberSearch] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [selectedMember, setSelectedMember] = useState<any>(null);
    const [wizardDirty, setWizardDirty] = useState(false);
    const [maintenanceService, setMaintenanceService] = useState<string | null>(null);
    const { requestClose, renderDialog } = useDiscardGuard(wizardDirty);

    const searchContainerRef = useRef<HTMLDivElement>(null);

    // Fetch service availability
    const { data: availabilityData } = useQuery({
        queryKey: ['service-availability'],
        queryFn: getServiceAvailability,
    });
    const availability = availabilityData?.data?.data as ServiceAvailabilityData | undefined;

    useEffect(() => {
        const t = setTimeout(() => {
            const q = memberSearch.trim();
            setDebouncedQuery(q);
            if (q.length < 2) {
                setSearchOpen(false);
                setActiveIndex(-1);
            }
        }, 250);
        return () => clearTimeout(t);
    }, [memberSearch]);

    useEffect(() => {
        function onClickOutside(e: MouseEvent) {
            if (searchContainerRef.current && !searchContainerRef.current.contains(e.target as Node)) {
                setSearchOpen(false);
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const { data: servicesData } = useQuery({
        queryKey: ['services'],
        queryFn: () => api.get('/api/services'),
        enabled: isOfficer,
    });
    const svc = servicesData?.data || {};

    const { data: searchResults, isFetching: searchLoading } = useQuery({
        queryKey: ['member-search', debouncedQuery],
        queryFn: () => api.get('/api/members/search', { params: { q: debouncedQuery } }),
        enabled: debouncedQuery.length >= 2,
    });
    const members = searchResults?.data?.data ?? searchResults?.data ?? [];

    const { data: memberData } = useQuery({
        queryKey: ['selected-member', selectedMember?.id],
        queryFn: () => api.get(`/api/members/${selectedMember?.id}`),
        enabled: !!selectedMember?.id && isOfficer,
    });
    const selectedMemberDetail = memberData?.data;

    const { data: pendingData } = useQuery({
        queryKey: ['services-pending'],
        queryFn: () => api.get('/api/services/pending'),
        enabled: isOfficer && canApprove,
    });
    const pendingCount = pendingData?.data?.length ?? pendingData?.data?.data?.length ?? 0;

    const { data: myPendingData } = useQuery({
        queryKey: ['my-pending-requests'],
        queryFn: () => api.get('/api/history', { params: { status: 'pending', per_page: 5 } }),
        enabled: !isOfficer,
    });
    const myPendingRequests = myPendingData?.data?.data ?? [];

    const getPendingTypeColor = (t: string) => {
        const c: Record<string, string> = { deposit: 'bg-emerald-100 text-emerald-700', withdrawal: 'bg-red-100 text-red-700', loan_payment: 'bg-blue-100 text-blue-700' };
        return c[t] || 'bg-gray-100 text-gray-700';
    };

    const financialData = isOfficer ? selectedMemberDetail : null;
    const savingsBalance = financialData?.accounts?.find((a: any) => a.account_type === 'savings')?.balance ?? 0;
    const shareCapitalBalance = financialData?.accounts?.find((a: any) => a.account_type === 'share-capital')?.balance ?? 0;
    const activeLoans = financialData?.loans?.filter((l: any) => ['approved', 'active'].includes(l.loan_status)) ?? [];
    const hasSelectedMember = !!selectedMember;
    const needsMember = isOfficer;

    const handleSelectMember = (m: any) => {
        setSelectedMember(m);
        setMemberSearch('');
        setDebouncedQuery('');
        setSearchOpen(false);
        setActiveIndex(-1);
    };

    const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (members.length > 0) {
                setSearchOpen(true);
                setActiveIndex(i => (i + 1) % members.length);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (members.length > 0) {
                setActiveIndex(i => (i - 1 + members.length) % members.length);
            }
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && members[activeIndex]) {
                handleSelectMember(members[activeIndex]);
            }
        } else if (e.key === 'Escape') {
            setSearchOpen(false);
        }
    };

    const isServiceAvailable = (service: ServiceType): boolean => {
        if (!service || !availability) return true;

        const availKey = service === 'withdraw' ? 'withdrawal' :
                         service === 'payment' ? 'loan_payment' :
                         service === 'loan' ? 'loan_application' : service;

        const flags = availability[availKey as keyof ServiceAvailabilityData];
        if (!flags) return true;

        const userType = isOfficer ? 'officer' : 'member';

        // Check global
        if (!flags.global) return false;
        // Check user type
        if (!flags[userType as keyof typeof flags]) return false;

        return true;
    };

    const openService = (svc: ServiceType) => {
        if (!isServiceAvailable(svc)) {
            const availKey = svc === 'withdraw' ? 'withdrawal' :
                             svc === 'payment' ? 'loan_payment' :
                             svc === 'loan' ? 'loan_application' : svc;
            setMaintenanceService(serviceLabelMap[availKey] || availKey);
            return;
        }
        setWizardDirty(false);
        setActiveService(svc);
    };

    const handleWizardComplete = () => {
        setActiveService(null);
        setWizardDirty(false);
        if (isOfficer) {
            queryClient.invalidateQueries({ queryKey: ['services-pending'] });
            queryClient.invalidateQueries({ queryKey: ['selected-member', selectedMember?.id] });
        } else {
            queryClient.invalidateQueries({ queryKey: ['my-pending-requests'] });
        }
    };

    const renderWizard = () => {
        switch (activeService) {
            case 'deposit': return <DepositWizard onComplete={handleWizardComplete} selectedMember={selectedMember} onDirtyChange={setWizardDirty} availability={availability?.deposit} />;
            case 'withdraw': return <WithdrawalWizard onComplete={handleWizardComplete} selectedMember={selectedMember} memberDetail={selectedMemberDetail} onDirtyChange={setWizardDirty} availability={availability?.withdrawal} />;
            case 'loan': return <LoanApplicationWizard onComplete={handleWizardComplete} selectedMember={selectedMember} onDirtyChange={setWizardDirty} availability={availability?.loan_application} />;
            case 'payment': return <LoanPaymentWizard onComplete={handleWizardComplete} selectedMember={selectedMember} onDirtyChange={setWizardDirty} availability={availability?.loan_payment} />;
            case 'approve': return <TransactionApproval onComplete={() => setActiveService(null)} />;
            case 'loan-review': return <TransactionApproval onComplete={() => setActiveService(null)} />;
            default: return null;
        }
    };

    const activeLabelMap: Record<string, string> = {
        deposit: 'Deposit Money', withdraw: 'Withdraw Money', loan: 'Apply for Loan',
        payment: 'Make Payment', approve: 'Approve Transactions', 'loan-review': 'Loan Applications',
    };
    const activeLabel = activeService ? (activeLabelMap[activeService] || '') : '';

    return (
        <div className="space-y-6">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h1 className="text-2xl font-bold text-gray-900">Services</h1>
                <p className="mt-1 text-sm text-gray-500">Select a service to get started</p>
            </div>

            {needsMember && (
                <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-cyan-500">
                    <h3 className="text-lg font-bold text-gray-900 mb-4">
                        <i className="fas fa-user-tie text-cyan-600 mr-2"></i>
                        {canApprove ? 'Approval Mode - Select Member' : 'Service Officer Mode - Select Member'}
                    </h3>

                    {selectedMember ? (
                        <div className="flex items-center justify-between p-4 bg-cyan-50 rounded-lg border border-cyan-200">
                            <div className="flex items-center gap-3">
                                <div className="w-12 h-12 rounded-full bg-cyan-500 flex items-center justify-center text-white font-bold text-lg">
                                    {selectedMember.name?.charAt(0) || selectedMember.personal_data?.first_name?.charAt(0) || 'M'}
                                </div>
                                <div>
                                    <p className="font-semibold text-gray-900">
                                        {selectedMember.name || `${selectedMember.personal_data?.first_name ?? ''} ${selectedMember.personal_data?.last_name ?? ''}`}
                                    </p>
                                    <p className="text-sm text-gray-600">{selectedMember.user_number || ''}</p>
                                </div>
                            </div>
                            <button onClick={() => setSelectedMember(null)} className="px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <i className="fas fa-times mr-2"></i>Change Member
                            </button>
                        </div>
                    ) : (
                        <div className="relative" ref={searchContainerRef}>
                            <div className="relative">
                                <input
                                    type="text"
                                    value={memberSearch}
                                    onChange={e => { setMemberSearch(e.target.value); setSearchOpen(true); setActiveIndex(-1); }}
                                    onKeyDown={handleSearchKeyDown}
                                    onFocus={() => memberSearch.trim().length >= 2 && setSearchOpen(true)}
                                    placeholder="Search member by name or member number..."
                                    className="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                                />
                                <i className="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                {searchLoading && (
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-cyan-500" />
                                )}
                            </div>

                            {searchOpen && (
                                <div className="absolute left-0 right-0 top-[calc(100%+8px)] z-20 max-h-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                                    <div className="max-h-72 overflow-y-auto p-2">
                                        {memberSearch.trim().length < 2 ? (
                                            <p className="px-3 py-4 text-center text-sm text-gray-500">Start typing to search for a member</p>
                                        ) : searchLoading && members.length === 0 ? (
                                            <div className="flex items-center gap-2 px-3 py-3 text-sm text-gray-500">
                                                <span className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-cyan-500" />
                                                Searching...
                                            </div>
                                        ) : members.length === 0 ? (
                                            <p className="px-3 py-4 text-center text-sm text-gray-500">No members found</p>
                                        ) : (
                                            members.map((m: any, idx: number) => (
                                                <button
                                                    key={m.id}
                                                    type="button"
                                                    onMouseDown={e => e.preventDefault()}
                                                    onClick={() => handleSelectMember(m)}
                                                    onMouseEnter={() => setActiveIndex(idx)}
                                                    className={`flex w-full items-center gap-3 rounded-lg p-3 text-left transition-colors ${idx === activeIndex ? 'bg-cyan-50' : 'hover:bg-gray-50'}`}
                                                >
                                                    <div className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold shrink-0 ${idx === activeIndex ? 'bg-cyan-100 text-cyan-700' : 'bg-gray-200 text-gray-600'}`}>
                                                        {m.name?.charAt(0) || m.personal_data?.first_name?.charAt(0) || 'M'}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate font-medium text-gray-900">{m.name || `${m.personal_data?.first_name ?? ''} ${m.personal_data?.last_name ?? ''}`}</p>
                                                        <p className="truncate text-sm text-gray-500">{m.user_number}</p>
                                                    </div>
                                                    <i className="fas fa-chevron-right shrink-0 text-gray-400"></i>
                                                </button>
                                            ))
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {hasSelectedMember && (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-5 border-l-4 border-green-500">
                        <p className="text-sm font-medium text-green-700 mb-1">Savings Balance</p>
                        <p className="text-2xl font-bold text-green-900">₱{Number(savingsBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border-l-4 border-blue-500">
                        <p className="text-sm font-medium text-blue-700 mb-1">Share Capital</p>
                        <p className="text-2xl font-bold text-blue-900">₱{Number(shareCapitalBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div className="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-5 border-l-4 border-purple-500">
                        <p className="text-sm font-medium text-purple-700 mb-1">Active Loans</p>
                        <p className="text-2xl font-bold text-purple-900">{activeLoans.length}</p>
                    </div>
                    <div className="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-5 border-l-4 border-orange-500">
                        <p className="text-sm font-medium text-orange-700 mb-1">Pending Requests</p>
                        <p className="text-2xl font-bold text-orange-900">{pendingCount}</p>
                    </div>
                </div>
            )}

            <div className="bg-white rounded-xl shadow-sm p-6">
                <h2 className="text-2xl font-bold text-gray-900 mb-6">Available Services</h2>

                {needsMember && (
                    <>
                        {(canApprove || canReviewLoans) && (
                            <div className={`grid grid-cols-1 ${canApprove && canReviewLoans ? 'md:grid-cols-2' : 'md:grid-cols-1'} gap-4 mb-4`}>
                                {canApprove && (
                                    <button onClick={() => openService('approve')} className="group bg-gradient-to-br from-yellow-50 to-amber-100 hover:from-yellow-100 hover:to-amber-200 rounded-xl p-5 text-left transition-all hover:shadow-lg relative">
                                        {pendingCount > 0 && (
                                            <span className="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">{pendingCount}</span>
                                        )}
                                        <div className="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                            <i className="fas fa-clipboard-check text-white text-xl"></i>
                                        </div>
                                        <h3 className="text-base font-bold text-gray-900 mb-1">Approve Transactions</h3>
                                        <p className="text-xs text-gray-600">Review and approve pending member requests</p>
                                    </button>
                                )}
                                {canReviewLoans && (
                                    <button onClick={() => openService('loan-review')} className="group bg-gradient-to-br from-indigo-50 to-violet-100 hover:from-indigo-100 hover:to-violet-200 rounded-xl p-5 text-left transition-all hover:shadow-lg relative">
                                        <div className="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                            <i className="fas fa-file-alt text-white text-xl"></i>
                                        </div>
                                        <h3 className="text-base font-bold text-gray-900 mb-1">Loan Applications</h3>
                                        <p className="text-xs text-gray-600">Review and endorse pending loan applications</p>
                                    </button>
                                )}
                            </div>
                        )}

                        <div className="border-t border-gray-100 mb-4 pt-2">
                            <p className="text-xs text-gray-400 font-medium uppercase tracking-wide">
                                <i className="fas fa-user-circle mr-1"></i>Member Services
                                {!hasSelectedMember && <span className="ml-2 text-amber-500 normal-case font-normal">— select a member above to enable</span>}
                            </p>
                        </div>
                    </>
                )}

                <div className={`grid grid-cols-1 ${needsMember ? 'md:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-3'} gap-4`}>
                    <ServiceCard
                        icon={
                            <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clipRule="evenodd"/>
                            </svg>
                        }
                        color="green"
                        label="Deposit Money"
                        desc={needsMember ? 'Add funds to savings or share capital' : 'Add funds to your savings or share capital account'}
                        locked={needsMember && !hasSelectedMember}
                        maintenance={availability?.deposit?.global === false || availability?.deposit?.[isOfficer ? 'officer' : 'member'] === false}
                        onClick={() => openService('deposit')}
                    />
                    <ServiceCard
                        icon={
                            <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clipRule="evenodd" transform="rotate(180 10 10)"/>
                            </svg>
                        }
                        color="orange"
                        label="Withdraw Money"
                        desc={needsMember ? 'Request withdrawal from savings account' : 'Request withdrawal from your savings account'}
                        locked={needsMember && !hasSelectedMember}
                        maintenance={availability?.withdrawal?.global === false || availability?.withdrawal?.[isOfficer ? 'officer' : 'member'] === false}
                        onClick={() => openService('withdraw')}
                    />
                    <ServiceCard
                        icon={
                            <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd"/>
                            </svg>
                        }
                        color="purple"
                        label="Apply for Loan"
                        desc={needsMember ? 'Apply for personal, housing, business or emergency loans' : 'Apply for personal, housing, business or emergency loans'}
                        locked={needsMember && !hasSelectedMember}
                        maintenance={availability?.loan_application?.global === false || availability?.loan_application?.[isOfficer ? 'officer' : 'member'] === false}
                        onClick={() => openService('loan')}
                    />
                    <ServiceCard
                        icon={
                            <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clipRule="evenodd"/>
                            </svg>
                        }
                        color="blue"
                        label="Make Payment"
                        desc={needsMember ? "Pay a member's active loan installments" : 'Pay your active loan installments'}
                        locked={needsMember && !hasSelectedMember}
                        maintenance={availability?.loan_payment?.global === false || availability?.loan_payment?.[isOfficer ? 'officer' : 'member'] === false}
                        onClick={() => openService('payment')}
                    />
                </div>
            </div>

            {!isOfficer && myPendingRequests.length > 0 && (
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">
                        <i className="fas fa-hourglass-half mr-2 text-amber-500"></i>Your Pending Requests
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">Transactions awaiting approval by a treasurer</p>
                    <div className="mt-4 space-y-3">
                        {myPendingRequests.map((t: any) => (
                            <div key={t.id} className="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${getPendingTypeColor(t.transaction_type)}`}>{t.transaction_type.replace('_', ' ')}</span>
                                        <span className="text-sm font-medium text-gray-900">{t.transaction_number}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-gray-500">
                                        {t.account?.account_type ? `${t.account.account_type.replace('_', ' ')} · ` : ''}{new Date(t.created_at).toLocaleDateString()}
                                        {t.loan?.loan_number ? ` · Loan ${t.loan.loan_number}` : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <p className="font-bold text-gray-900">&#8369;{Number(t.amount).toLocaleString()}</p>
                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Pending approval</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {activeService && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => requestClose(() => setActiveService(null))}>
                    <div className="w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-green-500 to-emerald-600 p-6 text-white">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">{activeLabel}</h2>
                                <button onClick={() => requestClose(() => setActiveService(null))} className="rounded-full p-1 text-white/80 hover:bg-white/20 hover:text-white">
                                    <i className="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div className="bg-white p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                            {renderWizard()}
                        </div>
                    </div>
                </div>
            )}

            {maintenanceService && (
                <ServiceMaintenanceModal
                    isOpen={true}
                    onClose={() => setMaintenanceService(null)}
                    serviceLabel={maintenanceService}
                />
            )}

            {renderDialog()}
        </div>
    );
}

function ServiceCard({ icon, color, label, desc, locked, maintenance, onClick }: { icon: React.ReactNode; color: string; label: string; desc: string; locked?: boolean; maintenance?: boolean; onClick: () => void }) {
    const bgMap: Record<string, string> = {
        green: 'bg-green-500', orange: 'bg-orange-500', purple: 'bg-purple-500', blue: 'bg-blue-500',
    };
    const gradMap: Record<string, string> = {
        green: 'from-green-50 to-emerald-100 hover:from-green-100 hover:to-emerald-200',
        orange: 'from-orange-50 to-red-100 hover:from-orange-100 hover:to-red-200',
        purple: 'from-purple-50 to-pink-100 hover:from-purple-100 hover:to-pink-200',
        blue: 'from-blue-50 to-cyan-100 hover:from-blue-100 hover:to-cyan-200',
    };

    const isDisabled = locked || maintenance;

    return (
        <div className="relative group/card">
            <button
                onClick={isDisabled ? undefined : onClick}
                className={`group w-full bg-gradient-to-br ${gradMap[color] || ''} rounded-xl p-5 text-left transition-all hover:shadow-lg ${isDisabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                disabled={isDisabled}
            >
                <div className={`w-12 h-12 ${bgMap[color] || 'bg-gray-500'} rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform`}>
                    {icon}
                </div>
                <h3 className="text-base font-bold text-gray-900 mb-1">{label}</h3>
                <p className="text-xs text-gray-600">{desc}</p>
            </button>
            {locked && (
                <div className="absolute bottom-2 left-0 right-0 flex justify-center pointer-events-none">
                    <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                        <i className="fas fa-lock text-amber-500 mr-1 text-xs"></i>Select member
                    </span>
                </div>
            )}
            {maintenance && !locked && (
                <div className="absolute inset-0 flex items-center justify-center pointer-events-none rounded-xl">
                    <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                        <i className="fas fa-tools text-amber-500 mr-1 text-xs"></i>Under Maintenance
                    </span>
                </div>
            )}
        </div>
    );
}
