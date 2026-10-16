import { useEffect } from 'react';
import { Routes, Route, Navigate, useParams, useNavigate } from 'react-router';
import { useAuth } from './contexts/AuthContext';
import DashboardLayout from './layouts/DashboardLayout';
import PublicLayout from './layouts/PublicLayout';
import LoginPage from './pages/auth/LoginPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';
import ResetPasswordPage from './pages/auth/ResetPasswordPage';
import VerifyEmailPage from './pages/auth/VerifyEmailPage';
import HomePage from './pages/public/HomePage';
import AboutPage from './pages/public/AboutPage';
import NewsPage from './pages/public/NewsPage';
import StoriesPage from './pages/public/StoriesPage';
import RegisterPage from './pages/public/RegisterPage';
import ContactPage from './pages/public/ContactPage';
import NotificationsPage from './pages/notifications/NotificationsPage';
import HistoryPage from './pages/history/HistoryPage';
import LogsPage from './pages/logs/LogsPage';
import ProfilePage from './pages/profile/ProfilePage';
import ProfileCompletionPage from './pages/profile/ProfileCompletionPage';
import SettingsPage from './pages/settings/SettingsPage';
import AnnouncementsPage from './pages/announcements/AnnouncementsPage';
import LedgerPage from './pages/ledger/LedgerPage';
import ExpensesPage from './pages/expenses/ExpensesPage';
import OfficersPage from './pages/officers/OfficersPage';
import BranchesPage from './pages/branches/BranchesPage';
import LoansPage from './pages/loans/LoansPage';
import ReportsPage from './pages/reports/ReportsPage';
import ServicesPage from './pages/services/ServicesPage';
import MemberOverview from './pages/overviews/MemberOverview';
import AdminOverview from './pages/overviews/AdminOverview';
import ManagerOverview from './pages/overviews/ManagerOverview';
import TreasurerOverview from './pages/overviews/TreasurerOverview';

import LoanOverview from './pages/overviews/LoanOverview';
import OfficerOverview from './pages/overviews/OfficerOverview';
import MemberLoansPage from './pages/member-loans/MemberLoansPage';
import CollectionsPage from './pages/collections/CollectionsPage';
import MembersPage from './pages/members/MembersPage';
import OversightApprovalsPage from './pages/oversight/OversightApprovalsPage';
import ChairmanOverview from './pages/overviews/ChairmanOverview';
import TicketsPage from './pages/tickets/TicketsPage';
import LoadingScreen from './components/LoadingScreen';
import EmptyState from './components/ui/EmptyState';
import ToastContainer from './components/ui/ToastContainer';
import ScrollToTop from './components/ScrollToTop';

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
    useEffect(() => { navigate('/dashboard/settings', { replace: true, state: { tab, subTab } }); }, [navigate, tab, subTab]);
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
            <Routes>
                <Route element={<PublicLayout />}>
                    <Route path="/" element={<HomePage />} />
                    <Route path="/about" element={<AboutPage />} />
                    <Route path="/news" element={<NewsPage />} />
                    <Route path="/stories" element={<StoriesPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route path="/contact" element={<ContactPage />} />
                </Route>

                <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
                <Route path="/forgot-password" element={<GuestRoute><ForgotPasswordPage /></GuestRoute>} />
                <Route path="/reset-password" element={<GuestRoute><ResetPasswordPage /></GuestRoute>} />
                <Route path="/verify-email" element={<ProtectedRoute><VerifyEmailPage /></ProtectedRoute>} />

                <Route path="/dashboard" element={<ProtectedRoute><DashboardLayout /></ProtectedRoute>}>
                    <Route index element={<DashboardRouter />} />
                    <Route path=":section" element={<DashboardRouter />} />
                </Route>

                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </>
    );
}
