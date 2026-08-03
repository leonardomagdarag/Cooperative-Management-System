import { createContext, useContext, useState, useCallback, useEffect, useRef, ReactNode } from 'react';

export interface Toast {
    id: string;
    message: string;
    type: 'success' | 'error' | 'info' | 'warning';
}

interface ToastContextType {
    toasts: Toast[];
    addToast: (message: string, type?: Toast['type']) => void;
    removeToast: (id: string) => void;
}

const ToastContext = createContext<ToastContextType | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const timersRef = useRef<ReturnType<typeof setTimeout>[]>([]);

    useEffect(() => () => timersRef.current.forEach(clearTimeout), []);

    const addToast = useCallback((message: string, type: Toast['type'] = 'info') => {
        const id = crypto.randomUUID();
        setToasts(prev => [...prev, { id, message, type }]);
        const timer = setTimeout(() => {
            setToasts(prev => prev.filter(t => t.id !== id));
        }, 4000);
        timersRef.current.push(timer);
    }, []);

    const removeToast = useCallback((id: string) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    return (
        <ToastContext.Provider value={{ toasts, addToast, removeToast }}>
            {children}
        </ToastContext.Provider>
    );
}

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) throw new Error('useToast must be used within ToastProvider');
    return context;
}
