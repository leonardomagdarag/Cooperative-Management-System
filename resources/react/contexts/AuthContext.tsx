import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';

interface User {
    id: number;
    name: string;
    email: string;
    email_verified?: boolean;
    primary_role: string;
    account_status?: string;
    first_name?: string;
    permissions: string[];
    profile_photo_url?: string;
    profile_completion_required?: boolean;
    branch_id?: number | null;
    branch_name?: string | null;
}

interface AuthContextType {
    user: User | null;
    isLoading: boolean;
    sessionEnded: boolean;
    clearSessionEnded: () => void;
    pendingLoginHandoff: { id: number; device: string; ip_address: string | null; status: string } | null;
    decideLoginHandoff: (id: number, decision: 'approve' | 'reject') => Promise<void>;
    login: (email: string, password: string, remember?: boolean, turnstileToken?: string | null) => Promise<{ requiresOtp: boolean; requiresApproval: boolean; redirect?: string }>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [sessionEnded, setSessionEnded] = useState(false);
    const [pendingLoginHandoff, setPendingLoginHandoff] = useState<AuthContextType['pendingLoginHandoff']>(null);

    useEffect(() => {
        const handleSessionEnded = () => {
            queryClient.clear();
            setUser(null);
            setSessionEnded(true);
        };

        window.addEventListener('auth:session-ended', handleSessionEnded);
        return () => window.removeEventListener('auth:session-ended', handleSessionEnded);
    }, [queryClient]);

    const clearSessionEnded = useCallback(() => setSessionEnded(false), []);

    const fetchUser = useCallback(async () => {
        try {
            const { data } = await api.get('/api/user');
            setUser(data);
        } catch {
            setUser(null);
        }
    }, []);

    useEffect(() => {
        fetchUser().finally(() => setIsLoading(false));
    }, [fetchUser]);

    useEffect(() => {
        if (!user || sessionEnded) return;

        const interval = window.setInterval(() => {
            void api.get('/api/user').catch(() => {});
            void api.get('/api/login-handoffs').then(({ data }) => setPendingLoginHandoff(data.request)).catch(() => {});
        }, 5_000);

        return () => window.clearInterval(interval);
    }, [user?.id, sessionEnded]);

    const decideLoginHandoff = async (id: number, decision: 'approve' | 'reject') => {
        await api.post(`/api/login-handoffs/${id}/decision`, { decision });
        if (decision === 'reject') setPendingLoginHandoff(null);
        else setPendingLoginHandoff((current) => current ? { ...current, status: 'approved' } : current);
    };

    const login = async (email: string, password: string, remember: boolean = false, turnstileToken: string | null = null) => {
        await api.get('/sanctum/csrf-cookie');
        const { data } = await api.post('/api/login', { email, password, remember, 'cf-turnstile-response': turnstileToken });
        if (data.requires_approval) return { requiresOtp: false, requiresApproval: true };
        if (data.requires_otp) {
            return { requiresOtp: true, requiresApproval: false, redirect: data.redirect };
        }
        await fetchUser();
        return { requiresOtp: false, requiresApproval: false, redirect: data.redirect };
    };

    const logout = async () => {
        await api.post('/api/logout');
        queryClient.clear();
        setUser(null);
    };

    const refreshUser = async () => {
        await fetchUser();
    };

    return (
        <AuthContext.Provider value={{ user, isLoading, sessionEnded, clearSessionEnded, pendingLoginHandoff, decideLoginHandoff, login, logout, refreshUser }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) throw new Error('useAuth must be used within AuthProvider');
    return context;
}
