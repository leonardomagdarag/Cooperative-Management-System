import { NavLink, useLocation } from 'react-router';
import { Fragment } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useSidebar } from '../contexts/SidebarContext';

interface NavItem {
    label: string;
    path: string;
    icon: string;
    permission?: string;
    multiPermission?: string[];
    roles?: string[];
    group?: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', path: '/dashboard', icon: 'fas fa-tachometer-alt' },
    { label: 'Members', path: '/dashboard/members', icon: 'fas fa-users', permission: 'members.view' },
    { label: 'Approvals', path: '/dashboard/approvals', icon: 'fas fa-clipboard-check', permission: 'oversight.approve' },
    { label: 'Officers', path: '/dashboard/officers', icon: 'fas fa-user-tie', permission: 'officers.view' },
    { label: 'Branches', path: '/dashboard/branches', icon: 'fas fa-building', permission: 'branches.view' },
    { label: 'Services', path: '/dashboard/services', icon: 'fas fa-briefcase', permission: 'services.view' },
    { label: 'My Loans', path: '/dashboard/member-loans', icon: 'fas fa-hand-holding-usd', roles: ['member'] },
    { label: 'Loans', path: '/dashboard/loans', icon: 'fas fa-hand-holding-usd', permission: 'loans.view' },
    { label: 'Collections', path: '/dashboard/collections', icon: 'fas fa-wallet', multiPermission: ['collections.view-all', 'collections.view-own', 'collections.review', 'collections.approve'] },
    { label: 'Ledger', path: '/dashboard/ledger', icon: 'fas fa-book', permission: 'transactions.view' },
    { label: 'Expenses', path: '/dashboard/expenses', icon: 'fas fa-receipt', permission: 'transactions.view' },
    { label: 'History', path: '/dashboard/history', icon: 'fas fa-history', roles: ['member'] },
    { label: 'Announcements', path: '/dashboard/announcements', icon: 'fas fa-bullhorn', permission: 'announcements.view' },
    { label: 'Notifications', path: '/dashboard/notifications', icon: 'fas fa-bell' },
    { label: 'Tickets', path: '/dashboard/tickets', icon: 'fas fa-ticket-alt', permission: 'tickets.view' },
    { label: 'Reports', path: '/dashboard/reports', icon: 'fas fa-chart-bar', permission: 'reports.view' },
    { label: 'Logs', path: '/dashboard/logs', icon: 'fas fa-clipboard-list', permission: 'logs.view' },
    { label: 'Settings', path: '/dashboard/settings', icon: 'fas fa-cog' },
    { label: 'Public Page', path: '/dashboard/public-content', icon: 'fas fa-globe', permission: 'settings.update', group: 'System Settings' },
    { label: 'Loan Products', path: '/dashboard/loan-products', icon: 'fas fa-hand-holding-usd', permission: 'settings.update', group: 'System Settings' },
    { label: 'Roles & Permissions', path: '/dashboard/role-permissions', icon: 'fas fa-user-shield', permission: 'officers.manage-permissions', group: 'System Settings' },
    { label: 'Service Availability', path: '/dashboard/service-availability', icon: 'fas fa-toggle-on', permission: 'settings.update', group: 'System Settings' },
    { label: 'Financial Accounts', path: '/dashboard/financial-accounts', icon: 'fas fa-piggy-bank', permission: 'financial_settings.update', group: 'System Settings' },
    { label: 'Integrations', path: '/dashboard/integrations', icon: 'fas fa-plug', permission: 'settings.update', roles: ['admin'], group: 'System Settings' },
    { label: 'Backup & Restore', path: '/dashboard/backups', icon: 'fas fa-hard-drive', multiPermission: ['backups.view', 'backups.manage'], roles: ['admin'], group: 'System Settings' },
];

const ADMIN_NAV_PATHS = new Set([
    '/dashboard',
    '/dashboard/officers',
    '/dashboard/notifications',
    '/dashboard/tickets',
    '/dashboard/reports',
    '/dashboard/logs',
    '/dashboard/settings',
    '/dashboard/public-content',
    '/dashboard/loan-products',
    '/dashboard/role-permissions',
    '/dashboard/service-availability',
    '/dashboard/financial-accounts',
    '/dashboard/integrations',
    '/dashboard/backups',
]);

export default function Sidebar() {
    const { user, logout } = useAuth();
    const { isOpen, isMobileOpen, closeMobile } = useSidebar();
    const location = useLocation();

    const labelClass = isOpen
        ? 'max-w-64 opacity-100'
        : 'max-w-64 opacity-100 lg:max-w-0 lg:opacity-0 lg:group-hover:max-w-64 lg:group-hover:opacity-100';

    const filteredItems = NAV_ITEMS.filter(item => {
        if (user?.primary_role === 'admin' && !ADMIN_NAV_PATHS.has(item.path)) {
            return false;
        }
        if (item.roles && user && item.roles.includes(user.primary_role)) return true;
        if (item.multiPermission) {
            return item.multiPermission.some(p => user?.permissions?.includes(p)) || false;
        }
        if (!item.permission) return !item.roles;
        return user?.permissions?.includes(item.permission);
    });

    return (
        <aside
            className={`fixed inset-y-0 left-0 z-40 flex flex-col overflow-hidden border-r border-gray-200 bg-white shadow-lg transition-all duration-300 group
                ${isOpen ? 'w-64' : 'w-64 lg:w-20 lg:hover:w-64'}
                ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'}
                lg:relative lg:translate-x-0`}
        >
            <div className="flex items-center justify-center border-b border-gray-200 px-4 py-4 shrink-0" style={{ height: '4rem' }}>
                <div className="flex items-center gap-3">
                    <div className="rounded-full overflow-hidden flex items-center justify-center w-10 h-10 shrink-0 ring-2 ring-emerald-500/20">
                        <img src="/images/Logo.png" alt="Settlers Logo" className="w-full h-full object-cover" />
                    </div>
                    <div className={`flex flex-col overflow-hidden whitespace-nowrap transition-all duration-300 ${labelClass}`}>
                        <h1 className="font-bold text-lg bg-linear-to-r from-emerald-600 to-cyan-600 bg-clip-text text-transparent whitespace-nowrap">Settlers</h1>
                        <p className="text-xs text-gray-600 whitespace-nowrap">Credit Cooperative</p>
                    </div>
                </div>
            </div>

            <nav className="flex-1 overflow-y-auto py-4">
                <ul className="space-y-1 px-3">
                    {filteredItems.map((item, index) => (
                        <Fragment key={item.path}>
                        {item.group && filteredItems[index - 1]?.group !== item.group && (
                            <li className={`overflow-hidden whitespace-nowrap px-3.5 pb-1 pt-4 text-[10px] font-semibold uppercase tracking-wider text-gray-400 transition-all duration-300 ${labelClass}`}>
                                {item.group}
                            </li>
                        )}
                        <li>
                            <NavLink
                                to={item.path}
                                end={item.path === '/dashboard'}
                                onClick={() => {
                                    closeMobile();
                                    if (location.pathname === item.path) {
                                        document.querySelector('main')?.scrollTo({ top: 0, behavior: 'smooth' });
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    }
                                }}
                                title={item.label}
                                className={({ isActive }) =>
                                    `flex items-center gap-3 rounded-lg px-3.5 py-3 transition-all duration-200 ${
                                        isActive
                                            ? 'bg-linear-to-r from-emerald-500 to-cyan-500 text-white shadow-md'
                                            : 'text-gray-700 hover:bg-emerald-50 hover:text-emerald-600'
                                    }`
                                }
                            >
                                <i className={`${item.icon} text-lg shrink-0 w-5 text-center`}></i>
                                <span className={`flex flex-1 min-w-0 overflow-hidden whitespace-nowrap transition-all duration-300 ${labelClass}`}>
                                    <span className="flex-1 text-left font-medium whitespace-nowrap truncate">{item.label}</span>
                                </span>
                            </NavLink>
                        </li>
                        </Fragment>
                    ))}
                </ul>
            </nav>

            <div className="border-t border-gray-200 p-3">
                <button
                    onClick={logout}
                    title="Logout"
                    className="w-full flex items-center gap-3 px-3.5 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200"
                >
                    <i className="fas fa-sign-out-alt text-lg shrink-0 w-5 text-center"></i>
                    <span className={`flex flex-1 min-w-0 overflow-hidden whitespace-nowrap transition-all duration-300 ${labelClass}`}>
                        <span className="flex-1 text-left font-medium whitespace-nowrap">Logout</span>
                    </span>
                </button>
            </div>
        </aside>
    );
}
