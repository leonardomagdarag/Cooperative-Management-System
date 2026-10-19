import { lazy, Suspense, useEffect } from 'react';
import { Routes, Route, Navigate, useParams, useNavigate, useLocation } from 'react-router';
import { useAuth } from './contexts/AuthContext';
import DashboardLayout from './layouts/DashboardLayout';
import PublicLayout from './layouts/PublicLayout';
const LoginPage = lazy(() => import('./pages/auth/LoginPage'));
const ForgotPasswordPage = lazy(() => import('./pages/auth/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('./pages/auth/ResetPasswordPage'));
const VerifyEmailPage = lazy(() => import('./pages/auth/VerifyEmailPage'));
const HomePage = lazy(() => import('./pages/public/HomePage'));
const AboutPage = lazy(() => import('./pages/public/AboutPage'));
const NewsPage = lazy(() => import('./pages/public/NewsPage'));
const StoriesPage = lazy(() => import('./pages/public/StoriesPage'));
const RegisterPage = lazy(() => import('./pages/public/RegisterPage'));
const ContactPage = lazy(() => import('./pages/public/ContactPage'));
const NotificationsPage = lazy(() => import('./pages/notifications/NotificationsPage'));
const HistoryPage = lazy(() => import('./pages/history/HistoryPage'));
const LogsPage = lazy(() => import('./pages/logs/LogsPage'));
const ProfilePage = lazy(() => import('./pages/profile/ProfilePage'));
const ProfileCompletionPage = lazy(() => import('./pages/profile/ProfileCompletionPage'));
const SettingsPage = lazy(() => import('./pages/settings/SettingsPage'));
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
const LoanOverview = lazy(() => import('./pages/overviews/LoanOverview'));
const OfficerOverview = lazy(() => import('./pages/overviews/OfficerOverview'));
const MemberLoansPage = lazy(() => import('./pages/member-loans/MemberLoansPage'));
const CollectionsPage = lazy(() => import('./pages/collections/CollectionsPage'));
const MembersPage = lazy(() => import('./pages/members/MembersPage'));
const OversightApprovalsPage = lazy(() => import('./pages/oversight/OversightApprovalsPage'));
const ChairmanOverview = lazy(() => import('./pages/overviews/ChairmanOverview'));
const TicketsPage = lazy(() => import('./pages/tickets/TicketsPage'));
import LoadingScreen from './components/LoadingScreen';
import EmptyState from './components/ui/EmptyState';
import ToastContainer from './components/ui/ToastContainer';
import ScrollToTop from './components/ScrollToTop';
import ErrorBoundary from './components/ErrorBoundary';

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
        const query = subTab ? `?tab=${tab}&subTab=${subTab}` : `?tab=${tab}`;
        navigate(`/dashboard/settings${query}`, { replace: true, state: { tab, subTab } });
    }, [navigate, tab, subTab]);
    return null;
}

function UnauthorizedPlaceholder({ permission }: { permission?: string }) {
    return (
        <EmptyState icon="fa-lock" title="Access restricted" description={
                permission === 'admin' ? 'This section requires administrator access.' :
                 permission ? `You need the "${permission}" permission to access this section.` :
                 'You do not have permission to access this section.'} />
    );
}

function DashboardRouter() {
    const { section } = useParams();
    const { user } = useAuth();

    if (!section) return <DefaultOverview role={user?.primary_role || 'member'} isOfficer={!!user?.is_officer} />;

    const sectionMap: Record<string, React.ReactNode> = {
        'notifications': <NotificationsPage />,
        'history': <HistoryPage />,
        'profile': <ProfilePage />,
        'settings': <SettingsPage />,
        'member-loans': <MemberLoansPage />,
        'collections': <CollectionsPage />,
        'approvals': <PermissionGate role="chairman"><OversightApprovalsPage /></PermissionGate>,
    };

    const permissionMap: Record<string, { permission?: string; role?: string; multiPermission?: string[] }> = {
        'logs': { permission: 'logs.view' },
        'announcements': { permission: 'announcements.view' },
        'loan-products': { permission: 'settings.update' },
        'ledger': { permission: 'transactions.view' },
        'expenses': { permission: 'transactions.view' },
        'public-content': { permission: 'settings.update' },
        'role-permissions': { role: 'admin' },
        'officers': { permission: 'officers.view' },
        'branches': { permission: 'branches.view' },
        'loans': { permission: 'loans.view' },
        'reports': { permission: 'reports.view' },
        'services': { permission: 'services.view' },
        'members': { permission: 'members.view' },
        'collections': { multiPermission: ['collections.view-all', 'collections.view-own', 'collections.review', 'collections.approve'] },
    };

    const tabRedirects: Record<string, string> = {
        'public-content': 'public-content',
        'loan-products': 'loan-products',
        'role-permissions': 'role-permissions',
    };

    if (sectionMap[section]) return sectionMap[section];

    const isSupportStaff = ['admin', 'manager', 'accountant'].includes(user?.primary_role || '');

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
                 tabRedirects[section] ? <RedirectToSettingsTab tab={tabRedirects[section]} /> :
                 section === 'ledger' ? <LedgerPage /> :
                 section === 'expenses' ? <ExpensesPage /> :
                 section === 'officers' ? <OfficersPage /> :
                 section === 'branches' ? <BranchesPage /> :
                 section === 'loans' ? <LoansPage /> :
                 section === 'reports' ? <ReportsPage /> :
                 section === 'services' ? <ServicesPage /> :
                 section === 'members' ? <MembersPage /> :
                 section === 'collections' ? <CollectionsPage /> :
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
        case 'loan': return <LoanOverview />;
        default:
            if (isOfficer) return <OfficerOverview />;
            return <MemberOverview />;
    }
}

export default function App() {
    return (
        <>
            <ScrollToTop />
            <ToastContainer />
            <Suspense fallback={<LoadingScreen />}>
                <Routes>
                    <Route element={<PublicLayout />}>
                        <Route path="/" element={<IsolatedPage><HomePage /></IsolatedPage>} />
                        <Route path="/about" element={<IsolatedPage><AboutPage /></IsolatedPage>} />
                        <Route path="/news" element={<IsolatedPage><NewsPage /></IsolatedPage>} />
                        <Route path="/stories" element={<IsolatedPage><StoriesPage /></IsolatedPage>} />
                        <Route path="/register" element={<IsolatedPage><RegisterPage /></IsolatedPage>} />
                        <Route path="/contact" element={<IsolatedPage><ContactPage /></IsolatedPage>} />
                    </Route>

                    <Route path="/login" element={<IsolatedPage><GuestRoute><LoginPage /></GuestRoute></IsolatedPage>} />
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
        </>
    );
}
