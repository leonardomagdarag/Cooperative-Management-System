import { Routes, Route, Navigate, useParams } from 'react-router-dom';
import { useAuth } from './contexts/AuthContext';
import DashboardLayout from './layouts/DashboardLayout';
import PublicLayout from './layouts/PublicLayout';
import LoginPage from './pages/auth/LoginPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';
import ResetPasswordPage from './pages/auth/ResetPasswordPage';
import HomePage from './pages/public/HomePage';
import AboutPage from './pages/public/AboutPage';
import NewsPage from './pages/public/NewsPage';
import RegisterPage from './pages/public/RegisterPage';
import ContactPage from './pages/public/ContactPage';
import NotificationsPage from './pages/notifications/NotificationsPage';
import HistoryPage from './pages/history/HistoryPage';
import LogsPage from './pages/logs/LogsPage';
import ProfilePage from './pages/profile/ProfilePage';
import SettingsPage from './pages/settings/SettingsPage';
import AnnouncementsPage from './pages/announcements/AnnouncementsPage';
import MessagesPage from './pages/messages/MessagesPage';
import TicketsPage from './pages/tickets/TicketsPage';
import LoanProductsPage from './pages/loan-products/LoanProductsPage';
import LedgerPage from './pages/ledger/LedgerPage';
import PublicContentPage from './pages/public-content/PublicContentPage';
import RolePermissionsPage from './pages/role-permissions/RolePermissionsPage';
import OfficersPage from './pages/officers/OfficersPage';
import LoansPage from './pages/loans/LoansPage';
import ReportsPage from './pages/reports/ReportsPage';
import ServicesPage from './pages/services/ServicesPage';
import MemberOverview from './pages/overviews/MemberOverview';
import AdminOverview from './pages/overviews/AdminOverview';
import ManagerOverview from './pages/overviews/ManagerOverview';
import TreasurerOverview from './pages/overviews/TreasurerOverview';
import SecretaryOverview from './pages/overviews/SecretaryOverview';
import LoanOverview from './pages/overviews/LoanOverview';
import OfficerOverview from './pages/overviews/OfficerOverview';
import MemberLoansPage from './pages/member-loans/MemberLoansPage';
import MembersPage from './pages/members/MembersPage';
import LoadingScreen from './components/LoadingScreen';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
    const { user, isLoading } = useAuth();
    if (isLoading) return <LoadingScreen />;
    if (!user) return <Navigate to="/login" replace />;
    return <>{children}</>;
}

function GuestRoute({ children }: { children: React.ReactNode }) {
    const { user, isLoading } = useAuth();
    if (isLoading) return <LoadingScreen />;
    if (user) return <Navigate to="/dashboard" replace />;
    return <>{children}</>;
}

function DashboardRouter() {
    const { section } = useParams();
    const { user } = useAuth();

    if (!section) return <DefaultOverview role={user?.primary_role || 'member'} />;

    const sectionMap: Record<string, React.ReactNode> = {
        'notifications': <NotificationsPage />,
        'history': <HistoryPage />,
        'logs': <LogsPage />,
        'profile': <ProfilePage />,
        'settings': <SettingsPage />,
        'announcements': <AnnouncementsPage />,
        'messages': <MessagesPage />,
        'tickets': <TicketsPage />,
        'loan-products': <LoanProductsPage />,
        'ledger': <LedgerPage />,
        'public-content': <PublicContentPage />,
        'role-permissions': <RolePermissionsPage />,
        'officers': <OfficersPage />,
        'loans': <LoansPage />,
        'reports': <ReportsPage />,
        'services': <ServicesPage />,
        'members': <MembersPage />,
        'member-loans': <MemberLoansPage />,
        'overviews': <DefaultOverview role={user?.primary_role || 'member'} />,
    };

    return sectionMap[section] || (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
            <i className="fas fa-hammer text-4xl text-slate-300 dark:text-slate-600"></i>
            <p className="mt-4 text-sm text-slate-500">This page will be built in a later phase.</p>
        </div>
    );
}

function DefaultOverview({ role }: { role: string }) {
    switch (role) {
        case 'admin': return <AdminOverview />;
        case 'manager': return <ManagerOverview />;
        case 'accountant': case 'treasurer': return <TreasurerOverview />;
        case 'secretary': return <SecretaryOverview />;
        case 'loan': return <LoanOverview />;
        default: return <MemberOverview />;
    }
}

export default function App() {
    return (
        <Routes>
            {/* Public routes */}
            <Route element={<PublicLayout />}>
                <Route path="/" element={<HomePage />} />
                <Route path="/about" element={<AboutPage />} />
                <Route path="/news" element={<NewsPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/contact" element={<ContactPage />} />
            </Route>

            {/* Auth routes (no public nav) */}
            <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
            <Route path="/forgot-password" element={<GuestRoute><ForgotPasswordPage /></GuestRoute>} />
            <Route path="/reset-password" element={<GuestRoute><ResetPasswordPage /></GuestRoute>} />

            {/* Protected dashboard routes */}
            <Route path="/dashboard" element={<ProtectedRoute><DashboardLayout /></ProtectedRoute>}>
                <Route index element={<DashboardRouter />} />
                <Route path=":section" element={<DashboardRouter />} />
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
