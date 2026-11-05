import HelpTip from '../../components/ui/HelpTip';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../contexts/LanguageContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { getServiceStatus, type ServiceAvailabilityData } from '../../api/settings';
import DepositWizard from './DepositWizard';
import WithdrawalWizard from './WithdrawalWizard';
import LoanApplicationWizard from './LoanApplicationWizard';
import LoanPaymentWizard from './LoanPaymentWizard';
import TransactionApproval from './TransactionApproval';
import LoanReviewModal from './LoanReviewModal';
import ServiceMaintenanceModal from '../../components/ServiceMaintenanceModal';
import PageHeader from '../../components/ui/PageHeader';

type ServiceType = 'deposit' | 'withdraw' | 'loan' | 'payment' | 'approve' | 'loan-review' | null;

const serviceLabelMap: Record<string, string> = {
    deposit: 'Deposits', withdrawal: 'Withdrawals', loan_application: 'Loan Applications', loan_payment: 'Loan Payments',
};

const pendingTypeLabel = (t: string): string => t.replace(/_/g, ' ');

const formatPendingDate = (iso: string): string => {
    const d = new Date(iso);
    const date = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const time = d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    return `Requested on ${date} at ${time}`;
};

export default function ServicesPage() {
    const { t } = useLanguage();
    const queryClient = useQueryClient();
    const { user } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();
    
    const perms = user?.permissions || [];
    const isOfficer = user?.primary_role !== 'member' && user?.primary_role !== null;
    const isAdmin = user?.primary_role === 'admin';
    const canApprove = perms.includes('transactions.approve') || perms.includes('deposits.approve') || perms.includes('withdrawals.approve');
    const canReviewLoans = perms.includes('loans.update');
    const canDeposit = perms.includes('deposits.create') || perms.includes('services.create');
    const isLoanFocused = !!user && !isAdmin && user.primary_role === 'loan';

    const activeService = (searchParams.get('service') as ServiceType) || null;
    const setActiveService = (svc: ServiceType) => {
        setSearchParams(prev => {
            const next = new URLSearchParams(prev);
            if (svc) next.set('service', svc);
            else next.delete('service');
            return next;
        }, { replace: true });
    };
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
        queryKey: ['service-status'],
        queryFn: getServiceStatus,
        refetchOnMount: 'always',
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
    const myPendingCount = myPendingData?.data?.pagination?.total ?? myPendingRequests.length;

    const { data: dashboardData } = useQuery({
        queryKey: ['dashboard', user?.id],
        queryFn: () => api.get('/api/dashboard'),
        enabled: !isOfficer,
    });

    const { data: reviewableLoansData } = useQuery({
        queryKey: ['reviewable-loans'],
        queryFn: () => api.get('/api/loans?loan_status=pending&per_page=50'),
        enabled: canReviewLoans,
    });
    const reviewableLoans = reviewableLoansData?.data?.data ?? [];
    const pendingLoanReviews = reviewableLoans.filter((l: any) =>
        l.review_status === null || l.review_status === 'under_review' || l.review_status === 'endorsed_for_approval'
    ).length;

    const hasSelectedMember = isOfficer && !!selectedMember;

    const memberAccounts = hasSelectedMember ? (selectedMemberDetail?.accounts ?? []) : (isOfficer ? [] : (dashboardData?.data?.accounts ?? []));
    const savingsBalance = memberAccounts.find((a: any) => a.account_type === 'savings')?.balance ?? 0;
    const shareCapitalBalance = memberAccounts.find((a: any) => a.account_type === 'share-capital')?.balance ?? 0;
    const activeLoanCount = hasSelectedMember
        ? (selectedMemberDetail?.loans?.filter((l: any) => ['approved', 'active'].includes(l.loan_status)) ?? []).length
        : (isOfficer ? 0 : (dashboardData?.data?.active_loans_count ?? 0));

    const hideFinancials = isLoanFocused;
    const gridCols = hideFinancials ? 'sm:grid-cols-2' : isOfficer ? 'sm:grid-cols-2 xl:grid-cols-3' : 'sm:grid-cols-2 xl:grid-cols-4';

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

        if (!flags.global) return false;
        if (!flags[userType as keyof typeof flags]) return false;

        // Savings withdrawals can be switched off independently from the
        // service-availability toggles, from the Financial Accounts tab.
        if (availKey === 'withdrawal' && flags.savings_enabled === false) return false;

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

    const closeWizard = useCallback(() => {
        requestClose(() => {
            setActiveService(null);
            setWizardDirty(false);
        });
    }, [requestClose]);

    const handleWizardComplete = () => {
        setActiveService(null);
        setWizardDirty(false);
        if (isOfficer) {
            queryClient.invalidateQueries({ queryKey: ['services-pending'] });
            queryClient.invalidateQueries({ queryKey: ['selected-member', selectedMember?.id] });
        }
    };

    const renderWizard = () => {
        switch (activeService) {
            case 'deposit': return <DepositWizard onComplete={handleWizardComplete} onRequestClose={closeWizard} selectedMember={selectedMember} onDirtyChange={setWizardDirty} availability={availability?.deposit} />;
            case 'withdraw': return <WithdrawalWizard onComplete={handleWizardComplete} onRequestClose={closeWizard} selectedMember={selectedMember} memberDetail={hasSelectedMember ? selectedMemberDetail : undefined} onDirtyChange={setWizardDirty} availability={availability?.withdrawal} />;
            case 'loan': return <LoanApplicationWizard onComplete={handleWizardComplete} onRequestClose={closeWizard} selectedMember={selectedMember} onDirtyChange={setWizardDirty} />;
            case 'payment': return <LoanPaymentWizard onComplete={handleWizardComplete} onRequestClose={closeWizard} selectedMember={selectedMember} onDirtyChange={setWizardDirty} availability={availability?.loan_payment} />;
            case 'approve': return <TransactionApproval onComplete={() => setActiveService(null)} onRequestClose={closeWizard} />;
            case 'loan-review': return <LoanReviewModal onComplete={() => setActiveService(null)} onRequestClose={closeWizard} />;
            default: return null;
        }
    };

    return (
        <div className="services-page space-y-6">
            <PageHeader  title={<>{t(isOfficer ? 'Services & approvals' : 'How can we help you?')} <HelpTip text="Choose a service to start a deposit, withdrawal, loan application, or loan payment. Officers can review pending requests without selecting a member." /></>} description={t(isOfficer ? 'Review pending work or select a member to process a service.' : 'Choose a service to get started. You can check your account and request status below.')} icon="fas fa-hand-holding-heart" />

            {isOfficer && (canApprove || canReviewLoans) && (
                <section aria-labelledby="services-review-title" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 id="services-review-title" className="text-xl font-bold text-slate-900">Needs your review</h2>
                    <p className="mt-1 mb-5 text-sm text-slate-600">Open a queue to review requests across members. No member selection is needed.</p>
                    <div className={`grid grid-cols-1 ${canApprove && canReviewLoans ? 'md:grid-cols-2' : ''} gap-3`}>
                        {canApprove && (
                            <button type="button" onClick={() => openService('approve')} className="group relative rounded-xl border border-amber-200 bg-amber-50 p-4 text-left transition-colors hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                                {pendingCount > 0 && (
                                    <span className="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">{pendingCount}</span>
                                )}
                                <span className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500" aria-hidden="true"><i className="fas fa-clipboard-check text-white text-xl"></i></span>
                                <span className="block text-base font-bold text-gray-900">Approve Transactions</span>
                                <span className="mt-1 block text-sm text-gray-600">Review and approve pending member requests</span>
                            </button>
                        )}
                        {canReviewLoans && (
                            <button type="button" onClick={() => openService('loan-review')} className="group relative rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-left transition-colors hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                {pendingLoanReviews > 0 && (
                                    <span className="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">{pendingLoanReviews}</span>
                                )}
                                <span className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600" aria-hidden="true"><i className="fas fa-file-alt text-white text-xl"></i></span>
                                <span className="block text-base font-bold text-gray-900">Loan Applications</span>
                                <span className="mt-1 block text-sm text-gray-600">Review and endorse pending loan applications</span>
                            </button>
                        )}
                    </div>
                </section>
            )}

            {isOfficer && (
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="mb-4 flex items-center gap-3">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"><i className="fas fa-user-tie" aria-hidden="true"></i></span>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-700">Step 1</p>
                            <h2 className="text-lg font-semibold text-slate-900">Select a member</h2>
                        </div>
                    </div>

                    {selectedMember ? (
                        <div className="flex items-center justify-between p-4 bg-cyan-50 rounded-lg border border-cyan-200">
                            <div className="flex items-center gap-3">
                                <div className="w-12 h-12 rounded-full bg-cyan-500 flex items-center justify-center text-white font-bold text-lg uppercase">
                                    {selectedMember.name?.charAt(0) || 'M'}
                                </div>
                                <div>
                                    <p className="font-semibold text-gray-900">{selectedMember.name}</p>
                                    <p className="text-sm text-gray-600">{selectedMember.user_number}</p>
                                </div>
                            </div>
                            <button onClick={() => setSelectedMember(null)} className="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-cyan-800 transition-colors hover:bg-cyan-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600">
                                Change member
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <div className="relative" ref={searchContainerRef}>
                                <div className="relative">
                                    <input
                                        type="text"
                                        aria-label="Search members by name"
                                        value={memberSearch}
                                        onChange={e => { setMemberSearch(e.target.value); setSearchOpen(true); setActiveIndex(-1); }}
                                        onKeyDown={handleSearchKeyDown}
                                        onFocus={() => memberSearch.trim().length >= 2 && setSearchOpen(true)}
                                        placeholder="Search member by name..."
                                        className="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
                                    />
                                    <i className="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-600"></i>
                                    {searchLoading && (
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-cyan-500" />
                                    )}
                                </div>

                                {searchOpen && (
                                    <div className="absolute left-0 right-0 top-[calc(100%+8px)] z-20 max-h-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                                        <div className="max-h-72 overflow-y-auto p-2">
                                            {memberSearch.trim().length < 2 ? (
                                                <p className="px-3 py-4 text-center text-sm text-gray-700">Start typing to search for a member</p>
                                            ) : searchLoading && members.length === 0 ? (
                                                <div className="flex items-center gap-2 px-3 py-3 text-sm text-gray-700">
                                                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-cyan-500" />
                                                    Searching...
                                                </div>
                                            ) : members.length === 0 ? (
                                                <p className="px-3 py-4 text-center text-sm text-gray-700">No members found</p>
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
                                                        <div className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold shrink-0 uppercase ${idx === activeIndex ? 'bg-cyan-100 text-cyan-700' : 'bg-gray-200 text-gray-600'}`}>
                                                            {m.name?.charAt(0) || 'M'}
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate font-medium text-gray-900">{m.name}</p>
                                                            <p className="truncate text-sm text-gray-700">{m.user_number}</p>
                                                        </div>
                                                        <i className="fas fa-chevron-right shrink-0 text-gray-600"></i>
                                                    </button>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {!isOfficer && (
                <section aria-labelledby="services-overview-title" className="space-y-4">
                    <div>
                        <h2 id="services-overview-title" className="text-lg font-bold text-slate-900">{t('Your accounts at a glance')}</h2>
                        <p className="text-sm text-slate-600">{t('Your current balances and request activity.')}</p>
                    </div>
                    <div className={`grid grid-cols-1 ${gridCols} gap-4`}>
                        {!hideFinancials && (
                            <>
                                <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-5 border-l-4 border-green-500">
                                    <p className="text-sm font-medium text-green-700 mb-1">{t('Savings Balance')}</p>
                                    <p className="text-2xl font-bold text-green-900">{!dashboardData ? '\u2014' : `\u20B1${Number(savingsBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}`}</p>
                                </div>
                                <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border-l-4 border-blue-500">
                                    <p className="text-sm font-medium text-blue-700 mb-1">{t('Share Capital')}</p>
                                    <p className="text-2xl font-bold text-blue-900">{!dashboardData ? '\u2014' : `\u20B1${Number(shareCapitalBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}`}</p>
                                </div>
                            </>
                        )}
                        <div className="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-5 border-l-4 border-purple-500">
                            <p className="text-sm font-medium text-purple-700 mb-1">{t('Active Loans')}</p>
                            <p className="text-2xl font-bold text-purple-900">{!dashboardData ? '\u2014' : activeLoanCount}</p>
                        </div>
                        <div className="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-5 border-l-4 border-orange-500">
                            <p className="text-sm font-medium text-orange-700 mb-1">{t('Pending Requests')}</p>
                            <p className="text-2xl font-bold text-orange-900">{myPendingCount}</p>
                        </div>
                    </div>
                </section>
            )}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="mb-5">
                    {isOfficer && <p className="text-xs font-semibold uppercase tracking-wide text-slate-700">Step 2</p>}
                    <h2 className="text-xl font-bold text-slate-900 sm:text-2xl">{t('Choose a service')}</h2>
                    <p className="mt-1 text-sm text-slate-600">{t(isOfficer ? 'Process a service for the selected member.' : 'Start a request with the cooperative.')}</p>
                </div>

                {isOfficer && (
                    <>
                        <div className="mb-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <i className="fas fa-user-circle mr-1"></i>Member Services
                                {!hasSelectedMember && <span className="ml-2 normal-case font-normal text-amber-700">Select a member above to enable these services</span>}
                            </p>
                        </div>
                    </>
                )}

                {isOfficer ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <ServiceCard
                            icon={
                                <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clipRule="evenodd"/>
                                </svg>
                            }
                            color="green"
                            label="Deposit Money"
                            desc="Add funds to savings or share capital"
                            locked={!hasSelectedMember || !canDeposit}
                            maintenance={availability?.deposit?.global === false || availability?.deposit?.officer === false}
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
                            desc="Request withdrawal from savings account"
                            locked={!hasSelectedMember}
                            maintenance={availability?.withdrawal?.global === false || availability?.withdrawal?.officer === false}
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
                            desc="Apply for personal, housing, business or emergency loans"
                            locked={!hasSelectedMember}
                            maintenance={availability?.loan_application?.global === false || availability?.loan_application?.officer === false}
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
                            desc="Pay a member's active loan installments"
                            locked={!hasSelectedMember}
                            maintenance={availability?.loan_payment?.global === false || availability?.loan_payment?.officer === false}
                            onClick={() => openService('payment')}
                        />
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <ServiceCard
                            icon={
                                <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clipRule="evenodd"/>
                                </svg>
                            }
                            color="green"
                            label="Deposit Money"
                            desc="Add funds to your savings or share capital account"
                            maintenance={availability?.deposit?.global === false || availability?.deposit?.member === false}
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
                            desc="Request withdrawal from your savings account"
                            maintenance={availability?.withdrawal?.global === false || availability?.withdrawal?.member === false}
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
                            desc="Apply for personal, housing, business or emergency loans"
                            maintenance={availability?.loan_application?.global === false || availability?.loan_application?.member === false}
                            onClick={() => openService('loan')}
                        />
                        {canReviewLoans && (
                            <button onClick={() => openService('loan-review')} className="group bg-gradient-to-br from-indigo-50 to-violet-100 hover:from-indigo-100 hover:to-violet-200 rounded-xl p-5 text-left transition-all hover:shadow-lg relative">
                                {pendingLoanReviews > 0 && (
                                    <span className="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">{pendingLoanReviews}</span>
                                )}
                                <div className="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <i className="fas fa-file-alt text-white text-xl"></i>
                                </div>
                                <h3 className="text-base font-bold text-gray-900 mb-1">Loan Applications</h3>
                                <p className="text-xs text-gray-600">Review and endorse pending loan applications</p>
                            </button>
                        )}
                        <ServiceCard
                            icon={
                                <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clipRule="evenodd"/>
                                </svg>
                            }
                            color="blue"
                            label="Make Payment"
                            desc="Pay your active loan installments"
                            maintenance={availability?.loan_payment?.global === false || availability?.loan_payment?.member === false}
                            onClick={() => openService('payment')}
                        />
                    </div>
                )}
            </section>

            {isOfficer && hasSelectedMember && <section aria-labelledby="services-overview-title" className="space-y-4">
                <div>
                    <h2 id="services-overview-title" className="text-lg font-bold text-slate-900">{isOfficer ? `${selectedMember.name}'s overview` : t('Your accounts at a glance')}</h2>
                    <p className="text-sm text-slate-600">{t(isOfficer ? 'Balances and activity for the selected member.' : 'Your current balances and request activity.')}</p>
                </div>
                <div className={`grid grid-cols-1 ${gridCols} gap-4`}>
                {!hideFinancials && (
                    <>
                        <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-5 border-l-4 border-green-500">
                            <p className="text-sm font-medium text-green-700 mb-1">{t('Savings Balance')}</p>
                            <p className="text-2xl font-bold text-green-900">{hasSelectedMember ? (selectedMemberDetail ? `₱${Number(savingsBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}` : '—') : !isOfficer && !dashboardData ? '—' : `₱${Number(savingsBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}`}</p>
                        </div>
                        <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border-l-4 border-blue-500">
                            <p className="text-sm font-medium text-blue-700 mb-1">{t('Share Capital')}</p>
                            <p className="text-2xl font-bold text-blue-900">{hasSelectedMember ? (selectedMemberDetail ? `₱${Number(shareCapitalBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}` : '—') : !isOfficer && !dashboardData ? '—' : `₱${Number(shareCapitalBalance).toLocaleString(undefined, { minimumFractionDigits: 2 })}`}</p>
                        </div>
                    </>
                )}
                <div className="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-5 border-l-4 border-purple-500">
                    <p className="text-sm font-medium text-purple-700 mb-1">{t('Active Loans')}</p>
                    <p className="text-2xl font-bold text-purple-900">{hasSelectedMember ? (selectedMemberDetail ? activeLoanCount : '—') : !isOfficer && !dashboardData ? '—' : activeLoanCount}</p>
                </div>
                {!isOfficer && <div className="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-5 border-l-4 border-orange-500">
                    <p className="text-sm font-medium text-orange-700 mb-1">{t('Pending Requests')}</p>
                    <p className="text-2xl font-bold text-orange-900">{myPendingCount}</p>
                </div>}
                </div>
            </section>}

            {!isOfficer && myPendingRequests.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-bold text-gray-900">
                            <i className="fas fa-clock text-orange-600 mr-2"></i>
                            {t('Pending Requests')}
                        </h3>
                        <span className="px-3 py-1 bg-orange-100 text-orange-800 text-sm font-semibold rounded-full">
                            {myPendingRequests.length} {myPendingRequests.length === 1 ? 'Request' : 'Requests'}
                        </span>
                    </div>

                    <div className="space-y-3">
                        {myPendingRequests.map((request: any) => (
                            <div key={request.id} className="flex items-center justify-between p-4 bg-orange-50 rounded-lg border border-orange-200">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="font-semibold text-gray-900">₱{Number(request.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                        <span className="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs font-medium rounded">{t('Awaiting Approval')}</span>
                                    </div>
                                    <p className="text-sm text-gray-600 capitalize">
                                        {pendingTypeLabel(request.transaction_type)} · <span className="normal-case">{formatPendingDate(request.created_at)}</span>
                                    </p>
                                    {request.note && <p className="text-xs text-gray-700 mt-1">{request.note}</p>}
                                </div>
                                <div className="text-right">
                                    <span className="text-sm text-gray-700 capitalize">
                                        {request.account?.account_type ? pendingTypeLabel(request.account.account_type) : 'Transaction'}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div className="flex items-start gap-2">
                            <i className="fas fa-info-circle text-blue-600 mt-0.5"></i>
                            <p className="text-sm text-blue-800">
                                {t('Your request is being reviewed. You will receive a notification once it has been approved or if additional information is needed.')}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {activeService && renderWizard()}

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
    const { t } = useLanguage();
    const styleMap: Record<string, { icon: string; surface: string }> = {
        green: { icon: 'bg-emerald-600', surface: 'bg-emerald-50 text-emerald-800' },
        orange: { icon: 'bg-orange-600', surface: 'bg-orange-50 text-orange-800' },
        purple: { icon: 'bg-violet-600', surface: 'bg-violet-50 text-violet-800' },
        blue: { icon: 'bg-blue-600', surface: 'bg-blue-50 text-blue-800' },
    };
    const style = styleMap[color] || styleMap.blue;
    const isDisabled = locked || maintenance;

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={isDisabled}
            className={`group flex min-h-36 w-full flex-col justify-between rounded-xl border p-5 text-left transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-700 ${isDisabled ? 'cursor-not-allowed border-slate-200 bg-slate-50' : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md'}`}
        >
            <div className="flex w-full items-start gap-4">
                <span className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${style.icon}`} aria-hidden="true">
                    {icon}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-base font-bold text-slate-900">{t(label)}</span>
                    <span className="mt-1 block text-sm leading-relaxed text-slate-600">{t(desc)}</span>
                </span>
                {!isDisabled && <i className="fas fa-arrow-right mt-1 text-sm text-slate-600 transition-transform group-hover:translate-x-1" aria-hidden="true"></i>}
            </div>
            {isDisabled ? (
                <span className="mt-4 inline-flex self-start rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                    {t(maintenance ? 'Under maintenance' : 'Select a member to continue')}
                </span>
            ) : (
                <span className={`mt-4 inline-flex self-start rounded-full px-2.5 py-1 text-xs font-semibold ${style.surface}`}>
                    {t('Get started')}
                </span>
            )}
        </button>
    );
}
