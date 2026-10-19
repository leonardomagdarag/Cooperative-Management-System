import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
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
    login: (email: string, password: string, remember?: boolean, turnstileToken?: string | null) => Promise<{ requiresOtp: boolean; redirect?: string }>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [sessionEnded, setSessionEnded] = useState(false);

    useEffect(() => {
        const handleSessionEnded = () => {
            setUser(null);
            setSessionEnded(true);
        };

        window.addEventListener('auth:session-ended', handleSessionEnded);
        return () => window.removeEventListener('auth:session-ended', handleSessionEnded);
    }, []);

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
        }, 30_000);

        return () => window.clearInterval(interval);
    }, [user?.id, sessionEnded]);

    const login = async (email: string, password: string, remember: boolean = false, turnstileToken: string | null = null) => {
        await api.get('/sanctum/csrf-cookie');
        const { data } = await api.post('/api/login', { email, password, remember, 'cf-turnstile-response': turnstileToken });
        if (data.requires_otp) {
            return { requiresOtp: true, redirect: data.redirect };
        }
        await fetchUser();
        return { requiresOtp: false, redirect: data.redirect };
    };

    const logout = async () => {
        await api.post('/api/logout');
        setUser(null);
    };

    const refreshUser = async () => {
        await fetchUser();
    };

    return (
        <AuthContext.Provider value={{ user, isLoading, sessionEnded, clearSessionEnded, login, logout, refreshUser }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) throw new Error('useAuth must be used within AuthProvider');
    return context;
}
