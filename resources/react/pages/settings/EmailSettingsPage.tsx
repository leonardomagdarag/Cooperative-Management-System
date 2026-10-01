import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import SettingsTabHeader from './SettingsTabHeader';

export default function EmailSettingsPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const [recipient, setRecipient] = useState(user?.email ?? '');

    const testEmailMutation = useMutation({
        mutationFn: async (email: string) => api.post('/api/settings/email/test', { email }),
        onSuccess: (response) => addToast(response.data.message || 'Test email sent.', 'success'),
        onError: (error: any) => addToast(error.response?.data?.message || 'Failed to send test email.', 'error'),
    });

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                eyebrow="Integrations"
                title="Email Service"
                description="Send a test email using the server's current mail configuration."
                icon="fas fa-envelope"
            />

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Test Email Delivery</h3>
                <p className="mt-1 text-sm text-gray-500">
                    This sends immediately to the address below. Mail configuration stays on the server.
                </p>
                <div className="mt-4 flex max-w-xl gap-3">
                    <input
                        type="email"
                        value={recipient}
                        onChange={(event) => setRecipient(event.target.value)}
                        placeholder="name@example.com"
                        className="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                    <button
                        type="button"
                        onClick={() => testEmailMutation.mutate(recipient.trim())}
                        disabled={testEmailMutation.isPending || !recipient.trim()}
                        className="rounded-lg bg-white border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors whitespace-nowrap"
                    >
                        <i className="fas fa-paper-plane mr-2"></i>
                        {testEmailMutation.isPending ? 'Sending...' : 'Send Test'}
                    </button>
                </div>
                <p className="mt-3 text-xs text-gray-500">
                    For security, only users with permission to update settings can send a test, and requests are rate limited.
                </p>
            </div>
        </div>
    );
}
