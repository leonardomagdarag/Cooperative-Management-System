import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    getPayMongoSettings,
    updatePayMongoSettings,
    updatePayMongoCredentials,
    clearPayMongoCredentials,
    testPayMongoConnection,
    type PayMongoSummary,
} from '../../api/paymongo';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import ConfirmDialog from '../../components/ui/ConfirmDialog';

export default function PayMongoPage() {
    const { addToast } = useToast();
    const { user } = useAuth();
    const queryClient = useQueryClient();

    const isAdmin = user?.primary_role === 'admin';
    const canManage = isAdmin || user?.permissions?.includes('settings.update');

    const { data: settingsRes, isLoading } = useQuery({
        queryKey: ['paymongo-settings'],
        queryFn: getPayMongoSettings,
    });

    const summary: PayMongoSummary | undefined = settingsRes?.data;

    const [generalForm, setGeneralForm] = useState({
        enabled: false,
        mode: 'sandbox' as 'sandbox' | 'live',
        enabled_methods: [] as string[],
        pass_on_fees: false,
        send_email_receipt: true,
    });

    const [credsForm, setCredsForm] = useState({
        test_public_key: '',
        test_secret_key: '',
        test_webhook_secret: '',
        live_public_key: '',
        live_secret_key: '',
        live_webhook_secret: '',
    });

    const [showSecret, setShowSecret] = useState<Record<string, boolean>>({});
    const [confirmClearCreds, setConfirmClearCreds] = useState(false);
    const [confirmLiveMode, setConfirmLiveMode] = useState(false);
    const [activeCredsTab, setActiveCredsTab] = useState<'sandbox' | 'live'>('sandbox');

    useEffect(() => {
        if (summary) {
            setGeneralForm({
                enabled: summary.enabled,
                mode: summary.mode,
                enabled_methods: summary.enabled_methods || [],
                pass_on_fees: summary.pass_on_fees,
                send_email_receipt: summary.send_email_receipt,
            });
            setActiveCredsTab(summary.mode);
        }
    }, [summary]);

    const saveSettingsMutation = useMutation({
        mutationFn: updatePayMongoSettings,
        onSuccess: (res) => {
            addToast(res.message || 'Settings updated successfully.', 'success');
            queryClient.invalidateQueries({ queryKey: ['paymongo-settings'] });
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to save settings.', 'error');
        },
    });

    const saveCredsMutation = useMutation({
        mutationFn: () => {
            const payload: Record<string, string> = {};
            Object.entries(credsForm).forEach(([key, val]) => {
                if (val && val.trim() !== '') {
                    payload[key] = val.trim();
                }
            });
            return updatePayMongoCredentials(payload);
        },
        onSuccess: (res) => {
            addToast(res.message || 'Credentials saved securely.', 'success');
            setCredsForm({
                test_public_key: '',
                test_secret_key: '',
                test_webhook_secret: '',
                live_public_key: '',
                live_secret_key: '',
                live_webhook_secret: '',
            });
            queryClient.invalidateQueries({ queryKey: ['paymongo-settings'] });
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to save credentials.', 'error');
        },
    });

    const clearCredsMutation = useMutation({
        mutationFn: clearPayMongoCredentials,
        onSuccess: (res) => {
            addToast(res.message || 'Stored credentials removed.', 'info');
            setConfirmClearCreds(false);
            queryClient.invalidateQueries({ queryKey: ['paymongo-settings'] });
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to clear credentials.', 'error');
        },
    });

    const testConnectionMutation = useMutation({
        mutationFn: testPayMongoConnection,
        onSuccess: (res) => {
            addToast(res.message || 'Connected to PayMongo successfully!', 'success');
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Connection test failed.', 'error');
        },
    });

    const toggleMethod = (key: string) => {
        setGeneralForm(prev => {
            const exists = prev.enabled_methods.includes(key);
            const next = exists
                ? prev.enabled_methods.filter(m => m !== key)
                : [...prev.enabled_methods, key];
            return { ...prev, enabled_methods: next };
        });
    };

    const webhookUrl = typeof window !== 'undefined'
        ? `${window.location.origin}/api/webhooks/paymongo`
        : '/api/webhooks/paymongo';

    const copyWebhookUrl = () => {
        navigator.clipboard.writeText(webhookUrl);
        addToast('Webhook URL copied to clipboard!', 'info');
    };

    if (isLoading) {
        return (
            <div className="p-8 space-y-4">
                <div className="h-20 bg-gray-100 animate-pulse rounded-xl" />
                <div className="h-64 bg-gray-100 animate-pulse rounded-xl" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Status Banner */}
            <div className={`p-5 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-4 ${
                !summary?.enabled
                    ? 'bg-gray-50 border-gray-200 text-gray-700'
                    : summary?.mode === 'live'
                        ? summary?.configured
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                            : 'bg-red-50 border-red-200 text-red-800'
                        : summary?.configured
                            ? 'bg-blue-50 border-blue-200 text-blue-800'
                            : 'bg-amber-50 border-amber-200 text-amber-800'
            }`}>
                <div className="flex items-center gap-3">
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center text-lg ${
                        !summary?.enabled ? 'bg-gray-200 text-gray-600' :
                        summary?.mode === 'live' ? 'bg-emerald-600 text-white' : 'bg-blue-600 text-white'
                    }`}>
                        <i className="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="font-semibold text-base">PayMongo Payment Gateway</h3>
                            <span className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${
                                !summary?.enabled ? 'bg-gray-200 text-gray-700' :
                                summary?.mode === 'live' ? 'bg-emerald-200 text-emerald-900' : 'bg-blue-200 text-blue-900'
                            }`}>
                                {!summary?.enabled ? 'Disabled' : summary.mode === 'live' ? 'Live Production' : 'Sandbox (Test Mode)'}
                            </span>
                            {summary?.configured ? (
                                <span className="text-xs px-2.5 py-0.5 rounded-full font-medium bg-green-100 text-green-800">
                                    <i className="fas fa-check-circle mr-1"></i>Configured
                                </span>
                            ) : (
                                <span className="text-xs px-2.5 py-0.5 rounded-full font-medium bg-amber-100 text-amber-800">
                                    <i className="fas fa-exclamation-triangle mr-1"></i>Keys Missing
                                </span>
                            )}
                        </div>
                        <p className="text-xs mt-0.5 opacity-80">
                            {!summary?.enabled
                                ? 'PayMongo integration is disabled. Transactions will use OTC or manual verification.'
                                : summary.mode === 'live'
                                    ? 'Live mode is active. Real transactions will be processed and settled to the cooperative merchant account.'
                                    : 'Sandbox mode is active. Safe for testing payment flows without real monetary movement.'}
                        </p>
                    </div>
                </div>

                {canManage && (
                    <div className="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            onClick={() => testConnectionMutation.mutate()}
                            disabled={testConnectionMutation.isPending || !summary?.configured}
                            className="px-3.5 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <i className={`fas fa-plug ${testConnectionMutation.isPending ? 'animate-spin' : ''}`}></i>
                            {testConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                        </button>
                    </div>
                )}
            </div>

            {/* General Settings */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-5 space-y-5">
                <div className="border-b border-gray-100 pb-3">
                    <h4 className="font-semibold text-gray-900">Integration Configuration</h4>
                    <p className="text-xs text-gray-500">Configure gateway state and operating mode</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={generalForm.enabled}
                                onChange={e => setGeneralForm(f => ({ ...f, enabled: e.target.checked }))}
                                disabled={!canManage}
                                className="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500"
                            />
                            <div>
                                <span className="text-sm font-medium text-gray-900">Enable PayMongo Integration</span>
                                <p className="text-xs text-gray-500">Allow members and officers to make online payments via PayMongo</p>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Operating Mode</label>
                        <select
                            value={generalForm.mode}
                            onChange={e => {
                                const nextMode = e.target.value as 'sandbox' | 'live';
                                if (nextMode === 'live' && !summary?.live_configured) {
                                    addToast('Please configure Live Secret Key and Public Key before switching to Live mode.', 'warning');
                                    setConfirmLiveMode(true);
                                }
                                setGeneralForm(f => ({ ...f, mode: nextMode }));
                            }}
                            disabled={!canManage}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="sandbox">Sandbox / Test Mode (Safe)</option>
                            <option value="live">Live Production Mode</option>
                        </select>
                    </div>

                    <div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={generalForm.pass_on_fees}
                                onChange={e => setGeneralForm(f => ({ ...f, pass_on_fees: e.target.checked }))}
                                disabled={!canManage}
                                className="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500"
                            />
                            <div>
                                <span className="text-sm font-medium text-gray-900">Pass-on Processing Fees</span>
                                <p className="text-xs text-gray-500">Charge PayMongo gateway processing fees to the customer instead of deducting from cooperative</p>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={generalForm.send_email_receipt}
                                onChange={e => setGeneralForm(f => ({ ...f, send_email_receipt: e.target.checked }))}
                                disabled={!canManage}
                                className="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500"
                            />
                            <div>
                                <span className="text-sm font-medium text-gray-900">Send PayMongo Email Receipts</span>
                                <p className="text-xs text-gray-500">Automatically send official payment receipts from PayMongo to the customer's email</p>
                            </div>
                        </label>
                    </div>
                </div>

                {/* Enabled Payment Methods Selection */}
                <div className="pt-3 border-t border-gray-100">
                    <div className="flex items-center justify-between mb-3">
                        <div>
                            <h5 className="text-sm font-medium text-gray-900">Enabled Payment Channels</h5>
                            <p className="text-xs text-gray-500">Only enabled channels will appear on customer checkout</p>
                        </div>
                        {canManage && (
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setGeneralForm(f => ({ ...f, enabled_methods: Object.keys(summary?.supported_methods || {}) }))}
                                    className="text-xs text-emerald-600 hover:underline"
                                >
                                    Select All
                                </button>
                                <span className="text-gray-300">|</span>
                                <button
                                    type="button"
                                    onClick={() => setGeneralForm(f => ({ ...f, enabled_methods: [] }))}
                                    className="text-xs text-gray-500 hover:underline"
                                >
                                    Deselect All
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {Object.entries(summary?.supported_methods || {}).map(([key, item]) => {
                            const isSelected = generalForm.enabled_methods.includes(key);
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    disabled={!canManage}
                                    onClick={() => toggleMethod(key)}
                                    className={`p-3 rounded-lg border text-left flex items-start gap-3 transition-all ${
                                        isSelected
                                            ? 'border-emerald-500 bg-emerald-50/50'
                                            : 'border-gray-200 hover:border-gray-300 bg-white'
                                    }`}
                                >
                                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${
                                        isSelected ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500'
                                    }`}>
                                        <i className={`fas ${item.icon} text-sm`}></i>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-semibold text-gray-900">{item.name}</span>
                                            {isSelected && <i className="fas fa-check-circle text-emerald-600 text-xs"></i>}
                                        </div>
                                        <p className="text-xs text-gray-500 truncate">{item.description}</p>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {canManage && (
                    <div className="flex justify-end pt-3">
                        <button
                            type="button"
                            onClick={() => saveSettingsMutation.mutate(generalForm)}
                            disabled={saveSettingsMutation.isPending}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <i className="fas fa-save"></i>
                            {saveSettingsMutation.isPending ? 'Saving...' : 'Save Configuration'}
                        </button>
                    </div>
                )}
            </section>

            {/* API Credentials Management */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-5 space-y-5">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <div>
                        <h4 className="font-semibold text-gray-900">API Credentials & Secrets</h4>
                        <p className="text-xs text-gray-500">Stored credentials are encrypted at rest with authenticated AES-256 encryption</p>
                    </div>

                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => setActiveCredsTab('sandbox')}
                            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                                activeCredsTab === 'sandbox'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            }`}
                        >
                            <i className="fas fa-flask mr-1.5"></i>Sandbox Keys
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveCredsTab('live')}
                            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                                activeCredsTab === 'live'
                                    ? 'bg-emerald-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            }`}
                        >
                            <i className="fas fa-key mr-1.5"></i>Live Keys
                        </button>
                    </div>
                </div>

                {activeCredsTab === 'sandbox' ? (
                    <div className="space-y-4">
                        <div className="p-3 bg-blue-50/70 border border-blue-200 rounded-lg text-xs text-blue-800 flex items-center gap-2">
                            <i className="fas fa-info-circle text-blue-600 text-sm"></i>
                            <span>Obtain your test keys from the PayMongo Dashboard under <strong>Developers → API Keys</strong>. Test keys start with <code>pk_test_</code> and <code>sk_test_</code>.</span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <span>Test Public Key</span>
                                    {summary?.configured_keys.test_public_key && (
                                        <span className="text-emerald-600 flex items-center gap-1 font-normal"><i className="fas fa-check"></i> Configured</span>
                                    )}
                                </label>
                                <input
                                    type="text"
                                    value={credsForm.test_public_key}
                                    onChange={e => setCredsForm(f => ({ ...f, test_public_key: e.target.value }))}
                                    placeholder={summary?.stored.test_public_key ? '•••••••••••• (Leave blank to keep)' : 'pk_test_...'}
                                    disabled={!canManage}
                                    autoComplete="off"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label className="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <span>Test Secret Key</span>
                                    {summary?.configured_keys.test_secret_key && (
                                        <span className="text-emerald-600 flex items-center gap-1 font-normal"><i className="fas fa-check"></i> Configured</span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showSecret.test_secret_key ? 'text' : 'password'}
                                        value={credsForm.test_secret_key}
                                        onChange={e => setCredsForm(f => ({ ...f, test_secret_key: e.target.value }))}
                                        placeholder={summary?.stored.test_secret_key ? '•••••••••••••••••••• (Leave blank to keep)' : 'sk_test_...'}
                                        disabled={!canManage}
                                        autoComplete="new-password"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono pr-10 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret(s => ({ ...s, test_secret_key: !s.test_secret_key }))}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i className={`fas ${showSecret.test_secret_key ? 'fa-eye-slash' : 'fa-eye'} text-xs`}></i>
                                    </button>
                                </div>
                            </div>

                            <div className="md:col-span-2">
                                <label className="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <span>Test Webhook Signing Secret</span>
                                    {summary?.configured_keys.test_webhook_secret && (
                                        <span className="text-emerald-600 flex items-center gap-1 font-normal"><i className="fas fa-check"></i> Configured</span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showSecret.test_webhook_secret ? 'text' : 'password'}
                                        value={credsForm.test_webhook_secret}
                                        onChange={e => setCredsForm(f => ({ ...f, test_webhook_secret: e.target.value }))}
                                        placeholder={summary?.stored.test_webhook_secret ? '•••••••••••••••••••• (Leave blank to keep)' : 'whsk_...'}
                                        disabled={!canManage}
                                        autoComplete="new-password"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono pr-10 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret(s => ({ ...s, test_webhook_secret: !s.test_webhook_secret }))}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i className={`fas ${showSecret.test_webhook_secret ? 'fa-eye-slash' : 'fa-eye'} text-xs`}></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="p-3 bg-amber-50/70 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-center gap-2">
                            <i className="fas fa-exclamation-triangle text-amber-600 text-sm"></i>
                            <span><strong>Production Keys:</strong> Live keys process real money. Keys start with <code>pk_live_</code> and <code>sk_live_</code>. Live Mode will only activate when both live keys are saved.</span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <span>Live Public Key</span>
                                    {summary?.configured_keys.live_public_key && (
                                        <span className="text-emerald-600 flex items-center gap-1 font-normal"><i className="fas fa-check"></i> Configured</span>
                                    )}
                                </label>
                                <input
                                    type="text"
                                    value={credsForm.live_public_key}
                                    onChange={e => setCredsForm(f => ({ ...f, live_public_key: e.target.value }))}
                                    placeholder={summary?.stored.live_public_key ? '•••••••••••• (Leave blank to keep)' : 'pk_live_...'}
                                    disabled={!canManage}
                                    autoComplete="off"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <span>Live Secret Key</span>
                                    {summary?.configured_keys.live_secret_key && (
                                        <span className="text-emerald-600 flex items-center gap-1 font-normal"><i className="fas fa-check"></i> Configured</span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showSecret.live_secret_key ? 'text' : 'password'}
                                        value={credsForm.live_secret_key}
                                        onChange={e => setCredsForm(f => ({ ...f, live_secret_key: e.target.value }))}
                                        placeholder={summary?.stored.live_secret_key ? '•••••••••••••••••••• (Leave blank to keep)' : 'sk_live_...'}
                                        disabled={!canManage}
                                        autoComplete="new-password"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono pr-10 focus:border-emerald-500 focus:ring-emerald-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret(s => ({ ...s, live_secret_key: !s.live_secret_key }))}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i className={`fas ${showSecret.live_secret_key ? 'fa-eye-slash' : 'fa-eye'} text-xs`}></i>
                                    </button>
                                </div>
                            </div>

                            <div className="md:col-span-2">
                                <label className="flex items-center justify-between text-xs font-medium text-gray-700 mb-1">
                                    <span>Live Webhook Signing Secret</span>
                                    {summary?.configured_keys.live_webhook_secret && (
                                        <span className="text-emerald-600 flex items-center gap-1 font-normal"><i className="fas fa-check"></i> Configured</span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showSecret.live_webhook_secret ? 'text' : 'password'}
                                        value={credsForm.live_webhook_secret}
                                        onChange={e => setCredsForm(f => ({ ...f, live_webhook_secret: e.target.value }))}
                                        placeholder={summary?.stored.live_webhook_secret ? '•••••••••••••••••••• (Leave blank to keep)' : 'whsk_...'}
                                        disabled={!canManage}
                                        autoComplete="new-password"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono pr-10 focus:border-emerald-500 focus:ring-emerald-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret(s => ({ ...s, live_webhook_secret: !s.live_webhook_secret }))}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i className={`fas ${showSecret.live_webhook_secret ? 'fa-eye-slash' : 'fa-eye'} text-xs`}></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {canManage && (
                    <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            onClick={() => setConfirmClearCreds(true)}
                            disabled={clearCredsMutation.isPending || !Object.values(summary?.stored || {}).some(Boolean)}
                            className="text-xs text-red-600 hover:text-red-700 font-medium disabled:opacity-40 flex items-center gap-1"
                        >
                            <i className="fas fa-trash-alt"></i>
                            Remove Stored Credentials
                        </button>

                        <button
                            type="button"
                            onClick={() => saveCredsMutation.mutate()}
                            disabled={saveCredsMutation.isPending}
                            className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <i className="fas fa-key"></i>
                            {saveCredsMutation.isPending ? 'Saving...' : 'Save Credentials'}
                        </button>
                    </div>
                )}
            </section>

            {/* Webhook Endpoint Info */}
            <section className="bg-white border border-gray-200 rounded-xl shadow-sm p-5 space-y-4">
                <div className="border-b border-gray-100 pb-3">
                    <h4 className="font-semibold text-gray-900">Webhook Integration</h4>
                    <p className="text-xs text-gray-500">PayMongo sends real-time HTTP callbacks when payments succeed or fail</p>
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Your Webhook URL</label>
                    <div className="flex items-center gap-2">
                        <input
                            type="text"
                            readOnly
                            value={webhookUrl}
                            className="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-800 select-all"
                        />
                        <button
                            type="button"
                            onClick={copyWebhookUrl}
                            className="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium flex items-center gap-1.5"
                        >
                            <i className="fas fa-copy"></i>
                            Copy
                        </button>
                    </div>
                </div>

                <div className="text-xs text-gray-600 space-y-1.5 bg-gray-50 p-3.5 rounded-lg border border-gray-200">
                    <p className="font-semibold text-gray-900">How to setup webhooks in PayMongo:</p>
                    <ol className="list-decimal pl-4 space-y-1">
                        <li>Log in to your <strong>PayMongo Dashboard</strong>.</li>
                        <li>Navigate to <strong>Developers → Webhooks</strong>.</li>
                        <li>Click <strong>Add Webhook</strong> and paste the URL above.</li>
                        <li>Subscribe to: <code>checkout_session.payment.paid</code>, <code>payment.paid</code>, <code>payment.failed</code>.</li>
                        <li>Copy the generated <strong>Signing Secret</strong> and paste it into the Webhook Signing Secret field above.</li>
                    </ol>
                </div>
            </section>

            {/* Confirmation Dialogs */}
            <ConfirmDialog
                isOpen={confirmClearCreds}
                title="Remove Stored PayMongo Credentials?"
                message="This will delete all encrypted PayMongo keys stored in the database. The integration will fall back to environment variables if available."
                confirmText="Remove Credentials"
                cancelText="Cancel"
                confirmVariant="danger"
                onConfirm={() => clearCredsMutation.mutate()}
                onCancel={() => setConfirmClearCreds(false)}
            />

            <ConfirmDialog
                isOpen={confirmLiveMode}
                title="Configure Live Mode?"
                message="Live Mode processes real financial transactions. Please ensure you have tested all payment flows in Sandbox Mode before switching."
                confirmText="Understood"
                cancelText="Stay in Sandbox"
                confirmVariant="primary"
                onConfirm={() => {
                    setActiveCredsTab('live');
                    setConfirmLiveMode(false);
                }}
                onCancel={() => {
                    setGeneralForm(f => ({ ...f, mode: 'sandbox' }));
                    setConfirmLiveMode(false);
                }}
            />
        </div>
    );
}
