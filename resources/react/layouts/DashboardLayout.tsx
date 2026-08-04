import { Outlet } from 'react-router';
import Sidebar from '../components/Sidebar';
import Header from '../components/Header';
import { useSidebar } from '../contexts/SidebarContext';
import { useToast } from '../contexts/ToastContext';
import Button from '../components/ui/Button';

export default function DashboardLayout() {
    const { isMobileOpen, closeMobile } = useSidebar();

    return (
        <div className="dashboard-shell flex h-screen overflow-hidden bg-gray-50">
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/50 lg:hidden"
                    onClick={closeMobile}
                />
            )}

            <Sidebar />

            <div className="flex flex-1 flex-col overflow-hidden">
                <Header />
                <main className="flex-1 overflow-y-auto p-4 sm:p-6">
                    <Outlet />
                </main>
            </div>

            <ToastContainer />
        </div>
    );
}

function ToastContainer() {
    const { toasts, removeToast } = useToast();
    return (
        <div className="fixed bottom-4 right-4 z-50 flex max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite" aria-label="Notifications">
            {toasts.map(toast => (
                <div
                    key={toast.id}
                    role="status"
                    className={`flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium shadow-lg transition-all ${
                        toast.type === 'success' ? 'bg-emerald-600 text-white' :
                        toast.type === 'error' ? 'bg-red-600 text-white' :
                        toast.type === 'warning' ? 'bg-amber-500 text-white' :
                        'bg-slate-700 text-white'
                    }`}
                >
                    <span>{toast.message}</span>
                    <Button variant="ghost" size="sm" onClick={() => removeToast(toast.id)} aria-label="Dismiss notification" className="ml-2 min-h-0 p-0 text-white/70 hover:bg-transparent hover:text-white">&times;</Button>
                </div>
            ))}
        </div>
    );
}
