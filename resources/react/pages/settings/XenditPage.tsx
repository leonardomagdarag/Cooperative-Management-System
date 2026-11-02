import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    clearXenditCredentials,
    getXenditSettings,
    testXenditConnection,
    updateXenditCredentials,
    updateXenditSettings,
    type XenditSummary,
} from '../../api/xendit';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import LoadingIndicator from '../../components/ui/LoadingIndicator';

const credentialFields = [
    ['test_secret_key', 'Test Secret API Key'],
    ['test_webhook_token', 'Test Webhook Callback Token'],
    ['live_secret_key', 'Live Secret API Key'],
    ['live_webhook_token', 'Live Webhook Callback Token'],
] as const;

export default function XenditPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const queryClient = useQueryClient();
    const canManageSettings = !!user?.permissions?.includes('settings.update');
    const [form, setForm] = useState({ enabled: false, mode: 'test' as 'test' | 'live' });
    const [secrets, setSecrets] = useState<Record<string, string>>({});
    const { data, isLoading } = useQuery({ queryKey: ['xendit-settings'], queryFn: getXenditSettings, enabled: canManageSettings });
    const summary: XenditSummary | undefined = data?.data;

    useEffect(() => {
        if (summary) setForm({ enabled: summary.enabled, mode: summary.mode });
    }, [summary]);

    const refresh = () => queryClient.invalidateQueries({ queryKey: ['xendit-settings'] });
    const saveSettings = useMutation({
        mutationFn: () => updateXenditSettings(form),
        onSuccess: () => { addToast('Xendit settings saved.', 'success'); refresh(); },
        onError: (error: any) => addToast(error.response?.data?.message || 'Unable to save settings.', 'error'),
    });
    const saveCredentials = useMutation({
        mutationFn: () => updateXenditCredentials(secrets),
        onSuccess: () => { setSecrets({}); addToast('Xendit credentials saved securely.', 'success'); refresh(); },
        onError: (error: any) => addToast(error.response?.data?.message || 'Unable to save credentials.', 'error'),
    });
    const clearCredentials = useMutation({
        mutationFn: clearXenditCredentials,
        onSuccess: () => { addToast('Stored Xendit credentials cleared.', 'success'); refresh(); },
        onError: (error: any) => addToast(error.response?.data?.message || 'Unable to clear credentials.', 'error'),
    });
    const testConnection = useMutation({
        mutationFn: testXenditConnection,
        onSuccess: (response: any) => addToast(response.message, 'success'),
        onError: (error: any) => addToast(error.response?.data?.message || 'Xendit connection test failed.', 'error'),
    });

    if (!canManageSettings) return <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">You need the settings.update permission to configure the Xendit payment gateway.</p>;
    if (isLoading) return <LoadingIndicator message="Loading payment settings..." />;

    const webhookUrl = `${window.location.origin}/api/webhooks/xendit`;

    return (
        <div className="space-y-6">
            <section className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="font-semibold text-gray-900">Xendit payment gateway</h3>
                        <p className="mt-1 text-sm text-gray-600">Use Xendit hosted checkout for online deposits and loan payments.</p>
                    </div>
                    <span className={`rounded-full px-3 py-1 text-xs font-medium ${summary?.configured ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                        {summary?.configured ? 'Ready to accept payments' : 'Setup incomplete'}
                    </span>
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <label className="flex items-center gap-3 text-sm">
                        <input type="checkbox" checked={form.enabled} onChange={event => setForm(current => ({ ...current, enabled: event.target.checked }))} className="rounded border-gray-300" />
                        Enable Xendit online payments
                    </label>
                    <label className="text-sm font-medium text-gray-700">
                        Environment
                        <select value={form.mode} onChange={event => setForm(current => ({ ...current, mode: event.target.value as 'test' | 'live' }))} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                            <option value="test">Test Mode</option>
                            <option value="live">Live Mode</option>
                        </select>
                    </label>
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                    <button type="button" onClick={() => saveSettings.mutate()} disabled={saveSettings.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Save settings</button>
                    <button type="button" onClick={() => testConnection.mutate()} disabled={form.mode !== summary?.mode || !summary?.configured_keys?.[form.mode === 'live' ? 'live_secret_key' : 'test_secret_key'] || testConnection.isPending} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:opacity-50">{testConnection.isPending ? 'Testing…' : 'Test connection'}</button>
                </div>
            </section>

            <section className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h4 className="font-semibold text-gray-900">API and webhook credentials</h4>
                <p className="mt-1 text-sm text-gray-600">Create a secret API key in Xendit Dashboard → Settings → Developers → API Keys with Payment Sessions and Payments permissions. Use test keys while developing. Register this endpoint for Payment Session events and save that mode’s callback token here.</p>
                <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                    <span className="font-medium text-gray-700">Webhook URL</span>
                    <code className="mt-1 block break-all text-gray-800">{webhookUrl}</code>
                </div>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                    {credentialFields.map(([key, label]) => (
                        <label key={key} className="text-sm font-medium text-gray-700">
                            {label}
                            <input type="password" autoComplete="new-password" value={secrets[key] ?? ''} onChange={event => setSecrets(current => ({ ...current, [key]: event.target.value }))} placeholder={summary?.configured_keys[key] ? 'Saved — leave blank to keep current value' : 'Paste credential'} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" />
                            <span className="mt-1 block text-xs font-normal text-gray-500">{summary?.configured_keys[key] ? 'Configured' : 'Not configured'}</span>
                        </label>
                    ))}
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                    <button type="button" onClick={() => saveCredentials.mutate()} disabled={saveCredentials.isPending || !Object.values(secrets).some(value => value.trim())} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Save credentials</button>
                    <button type="button" onClick={() => { if (window.confirm('Clear stored Xendit credentials?')) clearCredentials.mutate(); }} disabled={clearCredentials.isPending} className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 disabled:opacity-50">Clear stored credentials</button>
                </div>
                <p className="mt-4 text-xs text-gray-500">Test Mode lets you develop with virtual payments. Xendit requires business and identity documents before enabling live transactions.</p>
            </section>
        </div>
    );
}
