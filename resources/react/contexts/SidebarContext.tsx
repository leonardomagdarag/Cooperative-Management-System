import { createContext, useContext, useState, ReactNode } from 'react';

interface SidebarContextType {
    isOpen: boolean;
    isMobileOpen: boolean;
    toggle: () => void;
    toggleMobile: () => void;
    closeMobile: () => void;
}

const SidebarContext = createContext<SidebarContextType | null>(null);

export function SidebarProvider({ children }: { children: ReactNode }) {
    const [isOpen, setIsOpen] = useState(true);
    const [isMobileOpen, setIsMobileOpen] = useState(false);

    const toggle = () => setIsOpen(prev => !prev);

    const toggleMobile = () => setIsMobileOpen(prev => !prev);
    const closeMobile = () => setIsMobileOpen(false);

    return (
        <SidebarContext.Provider value={{ isOpen, isMobileOpen, toggle, toggleMobile, closeMobile }}>
            {children}
        </SidebarContext.Provider>
    );
}

export function useSidebar() {
    const context = useContext(SidebarContext);
    if (!context) throw new Error('useSidebar must be used within SidebarProvider');
    return context;
}
