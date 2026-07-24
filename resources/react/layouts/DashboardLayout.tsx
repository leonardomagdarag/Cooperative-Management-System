import { Outlet } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import Header from '../components/Header';
import { useSidebar } from '../contexts/SidebarContext';
import { useToast } from '../contexts/ToastContext';

export default function DashboardLayout() {
    const { isMobileOpen, closeMobile } = useSidebar();

    return (
        <div className="flex h-screen overflow-hidden bg-slate-100 dark:bg-slate-900">
            {/* Mobile overlay */}
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/50 lg:hidden"
                    onClick={closeMobile}
                />
            )}

            {/* Sidebar */}
            <Sidebar />

            {/* Main content */}
            <div className="flex flex-1 flex-col overflow-hidden">
                <Header />
                <main className="flex-1 overflow-y-auto p-4 md:p-6">
                    <Outlet />
                </main>
            </div>

            {/* Toast container */}
            <ToastContainer />
        </div>
    );
}

function ToastContainer() {
    const { toasts, removeToast } = useToast();
    return (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            {toasts.map(toast => (
                <div
                    key={toast.id}
                    className={`flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg transition-all ${
                        toast.type === 'success' ? 'bg-emerald-600 text-white' :
                        toast.type === 'error' ? 'bg-red-600 text-white' :
                        toast.type === 'warning' ? 'bg-amber-500 text-white' :
                        'bg-slate-700 text-white'
                    }`}
                >
                    <span>{toast.message}</span>
                    <button onClick={() => removeToast(toast.id)} className="ml-2 text-white/70 hover:text-white">&times;</button>
                </div>
            ))}
        </div>
    );
}
