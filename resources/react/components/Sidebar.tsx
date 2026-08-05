import { NavLink } from 'react-router';
import { useAuth } from '../contexts/AuthContext';
import { useSidebar } from '../contexts/SidebarContext';

interface NavItem {
    label: string;
    path: string;
    icon: string;
    permission?: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', path: '/dashboard', icon: 'fas fa-tachometer-alt' },
    { label: 'Members', path: '/dashboard/members', icon: 'fas fa-users', permission: 'members.view' },
    { label: 'Officers', path: '/dashboard/officers', icon: 'fas fa-user-tie', permission: 'officers.view' },
    { label: 'Services', path: '/dashboard/services', icon: 'fas fa-briefcase', permission: 'services.view' },
    { label: 'Loans', path: '/dashboard/loans', icon: 'fas fa-hand-holding-usd', permission: 'loans.view' },
    { label: 'Ledger', path: '/dashboard/ledger', icon: 'fas fa-book', permission: 'transactions.view' },
    { label: 'History', path: '/dashboard/history', icon: 'fas fa-history' },
    { label: 'Announcements', path: '/dashboard/announcements', icon: 'fas fa-bullhorn', permission: 'announcements.view' },
    { label: 'Notifications', path: '/dashboard/notifications', icon: 'fas fa-bell' },
    { label: 'Reports', path: '/dashboard/reports', icon: 'fas fa-chart-bar', permission: 'reports.view' },
    { label: 'Logs', path: '/dashboard/logs', icon: 'fas fa-clipboard-list', permission: 'logs.view' },
    { label: 'Settings', path: '/dashboard/settings', icon: 'fas fa-cog' },
];

export default function Sidebar() {
    const { user, logout } = useAuth();
    const { isOpen, isMobileOpen, closeMobile } = useSidebar();

    const filteredItems = NAV_ITEMS.filter(item => {
        if (!item.permission) return true;
        return user?.permissions?.includes(item.permission);
    });

    return (
        <aside
            className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-gray-200 bg-white shadow-lg transition-all duration-300 group
                ${isOpen ? 'w-64' : 'w-20 lg:w-64 hover:w-64'}
                ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'}
                lg:relative lg:translate-x-0`}
        >
            <div className="flex items-center justify-center border-b border-gray-200 px-4 py-4 shrink-0" style={{ height: '4rem' }}>
                <div className="flex items-center gap-3">
                    <div className="rounded-full overflow-hidden flex items-center justify-center w-10 h-10 shrink-0">
                        <img src="/images/Logo.png" alt="Settlers Logo" className="w-full h-full object-cover" />
                    </div>
                    <div className={isOpen ? 'block' : 'hidden lg:block group-hover:block'}>
                        <h1 className="font-bold text-lg bg-linear-to-r from-emerald-600 to-cyan-600 bg-clip-text text-transparent whitespace-nowrap">Settlers</h1>
                        <p className="text-xs text-gray-600 whitespace-nowrap">Credit Cooperative</p>
                    </div>
                </div>
            </div>

            <nav className="flex-1 overflow-y-auto py-4">
                <ul className="space-y-1 px-3">
                    {filteredItems.map(item => (
                        <li key={item.path}>
                            <NavLink
                                to={item.path}
                                onClick={closeMobile}
                                className={({ isActive }) =>
                                    `flex items-center justify-center lg:justify-start gap-3 rounded-lg px-4 py-3 transition-all duration-200 ${
                                        isActive
                                            ? 'bg-gradient-to-r from-emerald-500 to-cyan-500 text-white shadow-md'
                                            : 'text-gray-700 hover:bg-emerald-50 hover:text-emerald-600'
                                    }`
                                }
                            >
                                <i className={`${item.icon} text-lg shrink-0`}></i>
                                <span className={isOpen ? 'flex' : 'hidden lg:flex group-hover:flex'}>
                                    <span className="flex-1 text-left font-medium whitespace-nowrap truncate">{item.label}</span>
                                </span>
                            </NavLink>
                        </li>
                    ))}
                </ul>
            </nav>

            <div className="border-t border-gray-200 p-4">
                <button
                    onClick={logout}
                    className="w-full flex items-center justify-center lg:justify-start gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200"
                >
                    <i className="fas fa-sign-out-alt text-lg"></i>
                    <span className={isOpen ? 'flex' : 'hidden lg:flex group-hover:flex'}>
                        <span className="flex-1 text-left font-medium whitespace-nowrap">Logout</span>
                    </span>
                </button>
            </div>
        </aside>
    );
}
