import { lazy, Suspense, useEffect } from 'react';
import { Routes, Route, Navigate, useParams, useNavigate, useLocation, useSearchParams } from 'react-router';
import { useAuth } from './contexts/AuthContext';
import DashboardLayout from './layouts/DashboardLayout';
import PublicLayout from './layouts/PublicLayout';
const LoginPage = lazy(() => import('./pages/auth/LoginPage'));
const LoginApprovalPage = lazy(() => import('./pages/auth/LoginApprovalPage'));
const ForgotPasswordPage = lazy(() => import('./pages/auth/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('./pages/auth/ResetPasswordPage'));
const VerifyEmailPage = lazy(() => import('./pages/auth/VerifyEmailPage'));
const HomePage = lazy(() => import('./pages/public/HomePage'));
const AboutPage = lazy(() => import('./pages/public/AboutPage'));
const NewsPage = lazy(() => import('./pages/public/NewsPage'));
const StoriesPage = lazy(() => import('./pages/public/StoriesPage'));
const ContactPage = lazy(() => import('./pages/public/ContactPage'));
const NotificationsPage = lazy(() => import('./pages/notifications/NotificationsPage'));
const HistoryPage = lazy(() => import('./pages/history/HistoryPage'));
const LogsPage = lazy(() => import('./pages/logs/LogsPage'));
const ProfilePage = lazy(() => import('./pages/profile/ProfilePage'));
const ProfileCompletionPage = lazy(() => import('./pages/profile/ProfileCompletionPage'));
const SettingsPage = lazy(() => import('./pages/settings/SettingsPage'));
const PublicContentPage = lazy(() => import('./pages/public-content/PublicContentPage'));
const LoanProductsPage = lazy(() => import('./pages/loan-products/LoanProductsPage'));
const RolePermissionsPage = lazy(() => import('./pages/role-permissions/RolePermissionsPage'));
const ServiceAvailabilityPage = lazy(() => import('./pages/settings/ServiceAvailabilityPage'));
const FinancialAccountsPage = lazy(() => import('./pages/settings/FinancialAccountsPage'));
const IntegrationsSettingsPage = lazy(() => import('./pages/settings/IntegrationsSettingsPage'));
const BackupPage = lazy(() => import('./pages/settings/BackupPage'));
const AnnouncementsPage = lazy(() => import('./pages/announcements/AnnouncementsPage'));
const LedgerPage = lazy(() => import('./pages/ledger/LedgerPage'));
const ExpensesPage = lazy(() => import('./pages/expenses/ExpensesPage'));
const OfficersPage = lazy(() => import('./pages/officers/OfficersPage'));
const BranchesPage = lazy(() => import('./pages/branches/BranchesPage'));
const LoansPage = lazy(() => import('./pages/loans/LoansPage'));
const ReportsPage = lazy(() => import('./pages/reports/ReportsPage'));
const ServicesPage = lazy(() => import('./pages/services/ServicesPage'));
const MemberOverview = lazy(() => import('./pages/overviews/MemberOverview'));
const AdminOverview = lazy(() => import('./pages/overviews/AdminOverview'));
const ManagerOverview = lazy(() => import('./pages/overviews/ManagerOverview'));
const TreasurerOverview = lazy(() => import('./pages/overviews/TreasurerOverview'));
const OfficerOverview = lazy(() => import('./pages/overviews/OfficerOverview'));
const MemberLoansPage = lazy(() => import('./pages/member-loans/MemberLoansPage'));
const MembersPage = lazy(() => import('./pages/members/MembersPage'));
const OversightApprovalsPage = lazy(() => import('./pages/oversight/OversightApprovalsPage'));
const ChairmanOverview = lazy(() => import('./pages/overviews/ChairmanOverview'));
const TicketsPage = lazy(() => import('./pages/tickets/TicketsPage'));
import LoadingScreen from './components/LoadingScreen';
import EmptyState from './components/ui/EmptyState';
import ToastContainer from './components/ui/ToastContainer';
import ScrollToTop from './components/ScrollToTop';
import ErrorBoundary from './components/ErrorBoundary';
import { useLanguage } from './contexts/LanguageContext';

function IsolatedPage({ children }: { children: React.ReactNode }) {
    const location = useLocation();

    return <ErrorBoundary resetKey={location.pathname}>{children}</ErrorBoundary>;
}

function ProtectedRoute({ children }: { children: React.ReactNode }) {
    const { user, isLoading } = useAuth();
    if (isLoading) return <LoadingScreen />;
    if (!user) return <Navigate to="/login" replace />;
    if (user.email_verified === false) return <VerifyEmailPage />;
    if (user.profile_completion_required) return <ProfileCompletionPage />;
    return <>{children}</>;
}

function GuestRoute({ children }: { children: React.ReactNode }) {
    const { user, isLoading } = useAuth();
    if (isLoading) return <LoadingScreen />;
    if (user) return <Navigate to="/dashboard" replace />;
    return <>{children}</>;
}

function PermissionGate({ permission, role, multiPermission, children }: { permission?: string; role?: string; multiPermission?: string[]; children: React.ReactNode }) {
    const { user } = useAuth();
    if (!user) return null;
    if (role && user.primary_role === role) return <>{children}</>;
    if (permission && user.permissions?.includes(permission)) return <>{children}</>;
    if (multiPermission && multiPermission.some(p => user.permissions?.includes(p))) return <>{children}</>;
    return <UnauthorizedPlaceholder permission={permission || role || multiPermission?.join(' or ')} />;
}

function RedirectToSettingsTab({ tab, subTab }: { tab: string; subTab?: string }) {
    const navigate = useNavigate();
    useEffect(() => {
        const destination = settingsTabDestination(tab, subTab);
        navigate(destination, { replace: true, state: { tab, subTab } });
    }, [navigate, tab, subTab]);
    return null;
}

function settingsTabDestination(tab: string, subTab?: string): string {
    const destinations: Record<string, string> = {
        'public-content': '/dashboard/public-content',
        'loan-products': '/dashboard/loan-products',
        'role-permissions': '/dashboard/role-permissions',
        'service-availability': '/dashboard/service-availability',
        'financial-accounts': '/dashboard/financial-accounts',
        integrations: '/dashboard/integrations',
        sms: '/dashboard/integrations',
        email: '/dashboard/integrations',
        paymongo: '/dashboard/integrations',
        xendit: '/dashboard/integrations',
        backups: '/dashboard/backups',
    };
    const destination = destinations[tab];
    if (!destination) {
        const query = subTab ? `?tab=${tab}&subTab=${subTab}` : `?tab=${tab}`;
        return `/dashboard/settings${query}`;
    }
    const integrationTab = tab === 'integrations' ? subTab : tab;
    return integrationTab ? `${destination}?subTab=${integrationTab}` : destination;
}

function SettingsPageRoute() {
    const [searchParams] = useSearchParams();
    const tab = searchParams.get('tab');
    if (tab && ['public-content', 'loan-products', 'role-permissions', 'service-availability', 'financial-accounts', 'integrations', 'sms', 'email', 'paymongo', 'xendit', 'backups'].includes(tab)) {
        return <Navigate to={settingsTabDestination(tab, searchParams.get('subTab') || undefined)} replace />;
    }
    return <SettingsPage />;
}

function UnauthorizedPlaceholder({ permission }: { permission?: string }) {
    const { t } = useLanguage();
    return (
        <EmptyState icon="fa-lock" title={t('Access restricted')} description={
                permission === 'admin' ? t('This section requires administrator access.') :
                 permission ? t('You need the "{permission}" permission to access this section.').replace('{permission}', permission) :
                 t('You do not have permission to access this section.')} />
    );
}

function DashboardRouter() {
    const { section } = useParams();
    const { user } = useAuth();

    if (!section) return <DefaultOverview role={user?.primary_role || 'member'} isOfficer={!!user?.is_officer} />;

    if (section === 'collections') return <Navigate to="/dashboard/loans" replace />;

    const sectionMap: Record<string, React.ReactNode> = {
        'notifications': <NotificationsPage />,
        'history': <HistoryPage />,
        'profile': <ProfilePage />,
        'settings': <SettingsPageRoute />,
        'member-loans': <MemberLoansPage />,
        'approvals': <PermissionGate permission="oversight.approve"><OversightApprovalsPage /></PermissionGate>,
        'public-content': <PermissionGate permission="settings.update"><PublicContentPage /></PermissionGate>,
        'loan-products': <PermissionGate permission="settings.update"><LoanProductsPage /></PermissionGate>,
        'role-permissions': <PermissionGate permission="officers.manage-permissions"><RolePermissionsPage /></PermissionGate>,
        'service-availability': <PermissionGate permission="settings.update"><ServiceAvailabilityPage /></PermissionGate>,
        'financial-accounts': <PermissionGate permission="financial_settings.update"><FinancialAccountsPage /></PermissionGate>,
        'integrations': <PermissionGate permission="settings.update" role="admin"><IntegrationsSettingsPage /></PermissionGate>,
        'backups': <PermissionGate multiPermission={['backups.view', 'backups.manage']} role="admin"><BackupPage /></PermissionGate>,
    };

    const permissionMap: Record<string, { permission?: string; role?: string; multiPermission?: string[] }> = {
        'logs': { permission: 'logs.view' },
        'announcements': { permission: 'announcements.view' },
        'ledger': { permission: 'transactions.view' },
        'expenses': { permission: 'transactions.view' },
        'officers': { permission: 'officers.view' },
        'branches': { permission: 'branches.view' },
        'loans': { permission: 'loans.view' },
        'reports': { permission: 'reports.view' },
        'services': { permission: 'services.view' },
        'members': { permission: 'members.view' },
    };

    if (sectionMap[section]) return sectionMap[section];

    const isSupportStaff = !!user?.permissions?.includes('tickets.view');

    if (section === 'tickets') {
        if (isSupportStaff) {
            return <TicketsPage />;
        }
        return <RedirectToSettingsTab tab="help-support" subTab="tickets" />;
    }

    if (section === 'messages') {
        if (isSupportStaff) {
            return <Navigate to="/dashboard/tickets" replace />;
        }
        return <RedirectToSettingsTab tab="help-support" subTab="tickets" />;
    }

    const gate = permissionMap[section];
    if (gate) {
        return (
            <PermissionGate permission={gate.permission} role={gate.role} multiPermission={gate.multiPermission}>
                {section === 'logs' ? <LogsPage /> :
                 section === 'announcements' ? <AnnouncementsPage /> :
                 section === 'ledger' ? <LedgerPage /> :
                 section === 'expenses' ? <ExpensesPage /> :
                 section === 'officers' ? <OfficersPage /> :
                 section === 'branches' ? <BranchesPage /> :
                 section === 'loans' ? <LoansPage /> :
                 section === 'reports' ? <ReportsPage /> :
                 section === 'services' ? <ServicesPage /> :
                 section === 'members' ? <MembersPage /> :
                 <DefaultOverview role={user?.primary_role || 'member'} isOfficer={!!user?.is_officer} />}
            </PermissionGate>
        );
    }

    return (
        <EmptyState icon="fa-hammer" title="Coming soon" description="This page will be built in a later phase." />
    );
}

function DefaultOverview({ role, isOfficer }: { role: string; isOfficer?: boolean }) {
    switch (role) {
        case 'admin': return <AdminOverview />;
        case 'chairman': return <ChairmanOverview />;
        case 'manager': return <ManagerOverview />;
        case 'accountant': case 'treasurer': return <TreasurerOverview />;
        default:
            if (isOfficer) return <OfficerOverview />;
            return <MemberOverview />;
    }
}

export default function App() {
    const { sessionEnded, clearSessionEnded, pendingLoginHandoff, decideLoginHandoff, logout } = useAuth();
    const navigate = useNavigate();

    const returnToLogin = () => {
        clearSessionEnded();
        navigate('/login', { replace: true, state: { sessionEnded: true } });
    };

    const resetPassword = () => {
        clearSessionEnded();
        navigate('/forgot-password', { replace: true });
    };

    const rejectAndResetPassword = async () => {
        if (!pendingLoginHandoff) return;
        await decideLoginHandoff(pendingLoginHandoff.id, 'reject');
        await logout();
        navigate('/forgot-password', { replace: true });
    };

    return (
        <>
            <ScrollToTop />
            <ToastContainer />
            {pendingLoginHandoff && !sessionEnded && (
                <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/60 p-4" role="presentation">
                    <section role="dialog" aria-modal="true" aria-labelledby="login-handoff-title" className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700"><i className="fas fa-shield-halved" aria-hidden="true" /></div>
                        <h2 id="login-handoff-title" className="mt-4 text-xl font-bold text-slate-900">Sign-in approval requested</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">A sign-in attempt from <strong>{pendingLoginHandoff.device}</strong>{pendingLoginHandoff.ip_address ? ` (${pendingLoginHandoff.ip_address})` : ''} is waiting. While you decide, account actions are paused.</p>
                        {pendingLoginHandoff.status === 'approved' ? (
                            <p className="mt-5 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-800">Approved. Waiting for the other device to finish security verification.</p>
                        ) : (
                            <div className="mt-6 grid gap-3 sm:grid-cols-2">
                                <button type="button" onClick={() => void decideLoginHandoff(pendingLoginHandoff.id, 'approve')} className="rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800">It was me — approve</button>
                                <button type="button" onClick={() => void rejectAndResetPassword()} className="rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-50">Not me — reject and reset password</button>
                            </div>
                        )}
                    </section>
                </div>
            )}
            {sessionEnded ? (
                <main className="flex min-h-screen items-center justify-center bg-slate-50 p-6">
                    <section role="alert" aria-live="assertive" className="w-full max-w-lg rounded-2xl border border-amber-200 bg-white p-8 text-center shadow-sm">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                            <i className="fas fa-right-from-bracket text-xl" aria-hidden="true"></i>
                        </div>
                        <h1 className="mt-5 text-xl font-medium text-slate-900">This session has ended</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            This session ended because it was inactive too long or your account was signed in on another device.
                        </p>
                        <div className="mt-6 flex flex-wrap justify-center gap-3">
                        <button type="button" onClick={resetPassword} className="rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                            This wasn’t me — reset password
                        </button>
                        <button type="button" onClick={returnToLogin} className="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                            Return to login now
                        </button>
                        </div>
                    </section>
                </main>
            ) : (
            <Suspense fallback={<LoadingScreen />}>
                <Routes>
                    <Route element={<PublicLayout />}>
                        <Route path="/" element={<IsolatedPage><HomePage /></IsolatedPage>} />
                        <Route path="/about" element={<IsolatedPage><AboutPage /></IsolatedPage>} />
                        <Route path="/news" element={<IsolatedPage><NewsPage /></IsolatedPage>} />
                        <Route path="/stories" element={<IsolatedPage><StoriesPage /></IsolatedPage>} />
                        <Route path="/contact" element={<IsolatedPage><ContactPage /></IsolatedPage>} />
                    </Route>

                    <Route path="/login" element={<IsolatedPage><GuestRoute><LoginPage /></GuestRoute></IsolatedPage>} />
                        <Route path="/login/approval" element={<IsolatedPage><LoginApprovalPage /></IsolatedPage>} />
                    <Route path="/forgot-password" element={<IsolatedPage><GuestRoute><ForgotPasswordPage /></GuestRoute></IsolatedPage>} />
                    <Route path="/reset-password" element={<IsolatedPage><GuestRoute><ResetPasswordPage /></GuestRoute></IsolatedPage>} />
                    <Route path="/verify-email" element={<IsolatedPage><ProtectedRoute><VerifyEmailPage /></ProtectedRoute></IsolatedPage>} />

                    <Route path="/dashboard" element={<ProtectedRoute><DashboardLayout /></ProtectedRoute>}>
                        <Route index element={<IsolatedPage><DashboardRouter /></IsolatedPage>} />
                        <Route path=":section" element={<IsolatedPage><DashboardRouter /></IsolatedPage>} />
                    </Route>

                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </Suspense>
            )}
        </>
    );
}
