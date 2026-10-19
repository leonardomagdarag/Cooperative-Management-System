import { createContext, useContext, useState, useCallback, useEffect, useRef, ReactNode } from 'react';

export interface Toast {
    id: string;
    message: string;
    type: 'success' | 'error' | 'info' | 'warning';
    duration?: number;
}

interface ToastContextType {
    toasts: Toast[];
    addToast: (message: string, type?: Toast['type'], duration?: number) => void;
    removeToast: (id: string) => void;
}

const ToastContext = createContext<ToastContextType | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());

    useEffect(() => {
        return () => {
            timersRef.current.forEach(timer => clearTimeout(timer));
            timersRef.current.clear();
        };
    }, []);

    const removeToast = useCallback((id: string) => {
        const existingTimer = timersRef.current.get(id);
        if (existingTimer) {
            clearTimeout(existingTimer);
            timersRef.current.delete(id);
        }
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    const addToast = useCallback((message: string, type: Toast['type'] = 'info', duration?: number) => {
        let msg = message;
        let toastType = type;
        if (['success', 'error', 'info', 'warning'].includes(message) && typeof type === 'string' && !['success', 'error', 'info', 'warning'].includes(type)) {
            msg = type;
            toastType = message as Toast['type'];
        }
        const id = crypto.randomUUID();
        setToasts(prev => [...prev, { id, message: msg, type: toastType, duration }]);

        // Countersign notices or dual-custody approval requests need a decent amount of time to be read
        const isCountersignNotice = /countersign|awaiting approval|queued for|requires countersign|pending approval/i.test(msg);
        const resolvedDuration = duration ?? (isCountersignNotice ? 8000 : 5000);

        const timer = setTimeout(() => {
            setToasts(prev => prev.filter(t => t.id !== id));
            timersRef.current.delete(id);
        }, resolvedDuration);
        timersRef.current.set(id, timer);
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
