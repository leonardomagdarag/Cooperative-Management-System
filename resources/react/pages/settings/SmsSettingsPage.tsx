import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import SettingsTabHeader from './SettingsTabHeader';

interface SmsSettings {
    sms_service_enabled: boolean;
    textbee_api_key_configured: boolean;
    textbee_device_id: string;
}

type SmsSettingsUpdate = Partial<Pick<SmsSettings, 'sms_service_enabled' | 'textbee_device_id'>> & {
    textbee_api_key?: string;
};

export default function SmsSettingsPage() {
    const { addToast } = useToast();
    const queryClient = useQueryClient();

    const { data: settingsData, isLoading } = useQuery({
        queryKey: ['settings', 'sms'],
        queryFn: async () => {
            const res = await api.get('/api/settings/sms');
            return res.data.data as SmsSettings;
        },
    });

    const updateMutation = useMutation({
        mutationFn: async (data: SmsSettingsUpdate) => {
            return api.put('/api/settings/sms', data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settings', 'sms'] });
            addToast('SMS settings updated successfully.', 'success');
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to update SMS settings', 'error');
        }
    });

    const [testPhone, setTestPhone] = useState('');

    const testSmsMutation = useMutation({
        mutationFn: async (phone: string) => {
            return api.post('/api/settings/sms/test', { phone_number: phone });
        },
        onSuccess: (res) => {
            if (res.data.success) {
                addToast('Test SMS sent successfully.', 'success');
            } else {
                addToast(res.data.message || 'SMS test failed.', 'error');
            }
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to send test SMS.', 'error');
        }
    });

    if (isLoading) {
        return <div className="animate-pulse h-32 rounded-xl bg-gray-100" />;
    }

    const settings = settingsData || {
        sms_service_enabled: false,
        textbee_api_key_configured: false,
        textbee_device_id: ''
    };

    const isApiKeyConfigured = settings.textbee_api_key_configured;
    const isActive = settings.sms_service_enabled && isApiKeyConfigured;

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                eyebrow="Integrations"
                title="SMS Service (TextBee)"
                description="Configure TextBee for sending SMS OTPs and security alerts."
                icon="fas fa-comment-sms"
            />

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
                <div className="flex items-center justify-between pb-6 border-b border-gray-100">
                    <div>
                        <h3 className="font-semibold text-gray-900">Service Status</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Enable or disable global SMS delivery.
                        </p>
                    </div>
                    <div className="flex items-center gap-4">
                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${isActive ? 'bg-emerald-500' : 'bg-gray-400'}`}></span>
                            {isActive ? 'ACTIVE' : 'INACTIVE'}
                        </span>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                className="peer sr-only"
                                checked={settings.sms_service_enabled}
                                disabled={!isApiKeyConfigured || updateMutation.isPending}
                                onChange={(e) => updateMutation.mutate({ sms_service_enabled: e.target.checked })}
                            />
                            <div className="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-emerald-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 peer-disabled:opacity-50"></div>
                        </label>
                    </div>
                </div>

                {!isApiKeyConfigured && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div className="flex items-center gap-3 text-amber-800">
                            <i className="fas fa-triangle-exclamation"></i>
                            <p className="text-sm font-medium">TextBee API key is not configured. SMS delivery is disabled.</p>
                        </div>
                    </div>
                )}

                <ApiKeyForm
                    settings={settings}
                    onSave={(data) => updateMutation.mutate(data)}
                    isSaving={updateMutation.isPending}
                />

                <div className="pt-6 border-t border-gray-100">
                    <h3 className="font-semibold text-gray-900 mb-2">Test SMS</h3>
                    <p className="text-sm text-gray-500 mb-4">
                        Send a test SMS to verify the configuration.
                    </p>
                    <div className="flex gap-3 max-w-md">
                        <input
                            type="text"
                            placeholder="Enter phone number (e.g. 0917...)"
                            value={testPhone}
                            onChange={(e) => setTestPhone(e.target.value)}
                            className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                        <button
                            onClick={() => testSmsMutation.mutate(testPhone)}
                            disabled={!isActive || testSmsMutation.isPending || !testPhone.trim()}
                            className="rounded-lg bg-white border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors whitespace-nowrap"
                        >
                            <i className="fas fa-paper-plane mr-2"></i>
                            {testSmsMutation.isPending ? 'Sending...' : 'Send Test'}
                        </button>
                    </div>
                </div>
            </div>

            <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-6">
                <h4 className="text-sm font-semibold text-blue-900 flex items-center gap-2">
                    <i className="fas fa-circle-info text-blue-600"></i>
                    How TextBee works
                </h4>
                <p className="mt-2 text-sm text-blue-800">
                    TextBee uses a physical Android phone running the TextBee app to send SMS messages.
                    To use this feature, you must have an active Android phone with a SIM card, register it at
                    <a href="https://app.textbee.dev" target="_blank" rel="noreferrer" className="text-blue-600 hover:underline mx-1">
                        app.textbee.dev
                    </a>, and generate an API key.
                </p>
            </div>
        </div>
    );
}

function ApiKeyForm({ settings, onSave, isSaving }: { settings: SmsSettings, onSave: (data: SmsSettingsUpdate) => void, isSaving: boolean }) {
    const [apiKey, setApiKey] = useState('');
    const [deviceId, setDeviceId] = useState(settings.textbee_device_id);
    const hasChanges = apiKey.trim() !== '' || deviceId !== settings.textbee_device_id;

    const save = () => {
        const data: SmsSettingsUpdate = { textbee_device_id: deviceId };
        if (apiKey.trim()) {
            data.textbee_api_key = apiKey.trim();
        }
        onSave(data);
    };

    return (
        <div className="space-y-4">
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">TextBee API Key</label>
                <div className="flex gap-2">
                    <input
                        type="password"
                        value={apiKey}
                        onChange={(e) => setApiKey(e.target.value)}
                        placeholder={settings.textbee_api_key_configured ? 'Configured — enter a new key to replace' : 'Enter your API key'}
                        className="w-full max-w-md rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                    <button
                        onClick={save}
                        disabled={isSaving || !hasChanges}
                        className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        Save
                    </button>
                </div>
            </div>
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">
                    Device ID <span className="text-gray-400 font-normal">(Optional)</span>
                </label>
                <input
                    type="text"
                    value={deviceId}
                    onChange={(e) => setDeviceId(e.target.value)}
                    placeholder="Specific Android device ID (leave blank to use any available)"
                    className="w-full max-w-md rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                />
            </div>
        </div>
    );
}
