import { useAuth } from '../contexts/AuthContext';
import { useSidebar } from '../contexts/SidebarContext';
import { useTheme } from '../contexts/ThemeContext';
import NotificationDropdown from './NotificationDropdown';

export default function Header() {
    const { user, logout } = useAuth();
    const { toggle, toggleMobile } = useSidebar();
    const { isDark, toggle: toggleTheme } = useTheme();

    return (
        <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 dark:border-slate-700 dark:bg-slate-800">
            {/* Left: hamburger + toggle */}
            <div className="flex items-center gap-3">
                <button
                    onClick={toggleMobile}
                    className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                >
                    <i className="fas fa-bars"></i>
                </button>
                <button
                    onClick={toggle}
                    className="hidden rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:block"
                >
                    <i className={`fas fa-${true ? 'chevron-left' : 'chevron-right'}`}></i>
                </button>
            </div>

            {/* Right: notifications, theme, user */}
            <div className="flex items-center gap-2">
                <NotificationDropdown />

                <button
                    onClick={toggleTheme}
                    className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700"
                >
                    <i className={`fas fa-${isDark ? 'sun' : 'moon'}`}></i>
                </button>

                <div className="relative group">
                    <button className="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <div className="h-8 w-8 rounded-full bg-emerald-600 flex items-center justify-center text-xs font-bold text-white">
                            {user?.name?.charAt(0)}
                        </div>
                    </button>
                    <div className="absolute right-0 top-full z-50 mt-1 hidden w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg group-hover:block dark:border-slate-700 dark:bg-slate-800">
                        <div className="border-b border-slate-100 px-4 py-2 dark:border-slate-700">
                            <p className="text-sm font-medium">{user?.name}</p>
                            <p className="text-xs text-slate-500">{user?.email}</p>
                        </div>
                        <button
                            onClick={logout}
                            className="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-slate-50 dark:hover:bg-slate-700"
                        >
                            <i className="fas fa-sign-out-alt"></i>
                            Sign Out
                        </button>
                    </div>
                </div>
            </div>
        </header>
    );
}
