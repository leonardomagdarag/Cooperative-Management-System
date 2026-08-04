import { useState } from 'react';
import { useNavigate } from 'react-router';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';

export default function VerifyEmailPage() {
    const { user, logout } = useAuth();
    const { addToast } = useToast();
    const navigate = useNavigate();
    const [sending, setSending] = useState(false);

    const resend = async () => {
        setSending(true);
        try {
            const { data } = await api.post('/api/auth/resend-verification');
            addToast(data?.message || 'Verification email sent', 'success');
        } catch (err: any) {
            addToast(err.response?.data?.message || 'Failed to send verification email', 'error');
        } finally {
            setSending(false);
        }
    };

    const signOut = async () => {
        try {
            await logout();
        } finally {
            navigate('/');
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
            <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">
                <div className="text-center">
                    <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                        <i className="fas fa-envelope-open-text text-2xl text-amber-600"></i>
                    </div>
                    <h2 className="text-xl font-semibold text-gray-900">Verify your email address</h2>
                    <p className="mt-2 text-sm text-gray-500">
                        We sent a verification link to <strong className="text-gray-700">{user?.email}</strong>.
                        Click the link in the email to activate your account and access cooperative services.
                    </p>
                </div>

                <div className="mt-6 rounded-lg bg-amber-50 p-4 text-xs text-amber-800">
                    <i className="fas fa-clock mr-1"></i>
                    The link expires after 60 minutes.
                </div>

                <div className="mt-6 space-y-3">
                    <button
                        onClick={resend}
                        disabled={sending}
                        className="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {sending ? 'Sending...' : 'Resend verification email'}
                    </button>
                    <button
                        onClick={signOut}
                        className="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Sign out
                    </button>
                </div>
            </div>
        </div>
    );
}