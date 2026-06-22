import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useSidebar } from '../contexts/SidebarContext';

interface NavItem {
    label: string;
    path: string;
    icon: string;
    permission?: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', path: '/dashboard', icon: 'fas fa-th-large' },
    { label: 'Members', path: '/dashboard/members', icon: 'fas fa-users', permission: 'members.view' },
    { label: 'Officers', path: '/dashboard/officers', icon: 'fas fa-user-tie', permission: 'officers.view' },
    { label: 'Services', path: '/dashboard/services', icon: 'fas fa-hand-holding-usd', permission: 'services.view' },
    { label: 'Loans', path: '/dashboard/loans', icon: 'fas fa-file-invoice-dollar', permission: 'loans.view' },
    { label: 'Ledger', path: '/dashboard/ledger', icon: 'fas fa-book', permission: 'transactions.view' },
    { label: 'Reports', path: '/dashboard/reports', icon: 'fas fa-chart-bar', permission: 'reports.view' },
    { label: 'Announcements', path: '/dashboard/announcements', icon: 'fas fa-bullhorn', permission: 'announcements.view' },
    { label: 'Notifications', path: '/dashboard/notifications', icon: 'fas fa-bell' },
    { label: 'History', path: '/dashboard/history', icon: 'fas fa-history' },
    { label: 'Logs', path: '/dashboard/logs', icon: 'fas fa-file-alt', permission: 'logs.view' },
    { label: 'Settings', path: '/dashboard/settings', icon: 'fas fa-cog' },
];

export default function Sidebar() {
    const { user } = useAuth();
    const { isOpen, isMobileOpen, closeMobile } = useSidebar();
    const location = useLocation();

    const filteredItems = NAV_ITEMS.filter(item => {
        if (!item.permission) return true;
        return user?.permissions?.includes(item.permission);
    });

    return (
        <aside
            className={`fixed inset-y-0 left-0 z-40 flex flex-col bg-slate-900 text-white transition-all duration-300
                ${isOpen ? 'w-64' : 'w-20'}
                ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'}
                lg:relative lg:translate-x-0`}
        >
            {/* Logo */}
            <div className="flex h-16 items-center justify-center border-b border-slate-700 px-4">
                {isOpen ? (
                    <div className="flex items-center gap-3">
                        <img src="/images/Logo.png" alt="Logo" className="h-8 w-8 rounded-full" />
                        <span className="text-lg font-bold">SCC Admin</span>
                    </div>
                ) : (
                    <img src="/images/Logo.png" alt="Logo" className="h-8 w-8 rounded-full" />
                )}
            </div>

            {/* Navigation */}
            <nav className="flex-1 overflow-y-auto py-4">
                <ul className="space-y-1 px-2">
                    {filteredItems.map(item => (
                        <li key={item.path}>
                            <NavLink
                                to={item.path}
                                onClick={closeMobile}
                                className={({ isActive }) =>
                                    `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                                        isActive
                                            ? 'bg-emerald-600 text-white'
                                            : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                    }`
                                }
                            >
                                <i className={`${item.icon} w-5 text-center`}></i>
                                {isOpen && <span>{item.label}</span>}
                            </NavLink>
                        </li>
                    ))}
                </ul>
            </nav>

            {/* User info */}
            <div className="border-t border-slate-700 p-3">
                <div className={`flex items-center gap-3 ${isOpen ? '' : 'justify-center'}`}>
                    <div className="h-8 w-8 rounded-full bg-emerald-600 flex items-center justify-center text-xs font-bold">
                        {user?.name?.charAt(0)}
                    </div>
                    {isOpen && (
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{user?.name}</p>
                            <p className="truncate text-xs text-slate-400 capitalize">{user?.primary_role}</p>
                        </div>
                    )}
                </div>
            </div>
        </aside>
    );
}
