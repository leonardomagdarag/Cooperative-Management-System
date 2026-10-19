import { useRef, useEffect } from 'react';
import { Outlet, useLocation } from 'react-router';
import Sidebar from '../components/Sidebar';
import Header from '../components/Header';
import { useSidebar } from '../contexts/SidebarContext';

export default function DashboardLayout() {
    const { isMobileOpen, closeMobile } = useSidebar();
    const location = useLocation();
    const mainRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const htmlOverflow = document.documentElement.style.overflow;
        const bodyOverflow = document.body.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        return () => {
            document.documentElement.style.overflow = htmlOverflow;
            document.body.style.overflow = bodyOverflow;
        };
    }, []);

    useEffect(() => {
        if (mainRef.current) {
            mainRef.current.scrollTop = 0;
            mainRef.current.scrollLeft = 0;
        }
    }, [location.pathname]);

    return (
        <div className="dashboard-shell fixed inset-0 flex overflow-hidden bg-gray-50">
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/50 lg:hidden"
                    onClick={closeMobile}
                />
            )}

            <Sidebar />

            <div className="flex flex-1 flex-col overflow-hidden">
                <Header />
                <main ref={mainRef} className="flex-1 overflow-y-auto p-4 sm:p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}

