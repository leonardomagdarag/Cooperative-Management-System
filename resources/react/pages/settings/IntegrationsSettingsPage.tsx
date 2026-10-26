import { useEffect } from 'react';
import { useSearchParams } from 'react-router';
import { useAuth } from '../../contexts/AuthContext';
import EmailSettingsPage from './EmailSettingsPage';
import XenditPage from './XenditPage';
import SmsSettingsPage from './SmsSettingsPage';

type IntegrationKey = 'sms' | 'email' | 'xendit';

export default function IntegrationsSettingsPage() {
    const { user } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();
    const isAdmin = user?.primary_role === 'admin';
    const canManageSettings = isAdmin || user?.permissions?.includes('settings.update');
    const integrations: { key: IntegrationKey; label: string; icon: string; visible: boolean }[] = [
        { key: 'sms', label: 'SMS', icon: 'fas fa-comment-sms', visible: isAdmin },
        { key: 'email', label: 'Email', icon: 'fas fa-envelope', visible: Boolean(canManageSettings) },
        { key: 'xendit', label: 'Payments', icon: 'fas fa-credit-card', visible: isAdmin },
    ];
    const visibleIntegrations = integrations.filter(integration => integration.visible);
    const requested = searchParams.get('subTab');
    const requestedSection: IntegrationKey | null = requested === 'paymongo' ? 'xendit' : requested as IntegrationKey | null;
    const activeSection = visibleIntegrations.some(integration => integration.key === requestedSection)
        ? requestedSection!
        : visibleIntegrations[0]?.key;

    useEffect(() => {
        if (activeSection && searchParams.get('subTab') !== activeSection) {
            setSearchParams(previous => {
                const next = new URLSearchParams(previous);
                next.set('tab', 'integrations');
                next.set('subTab', activeSection);
                return next;
            }, { replace: true });
        }
    }, [activeSection, searchParams, setSearchParams]);

    const selectSection = (key: IntegrationKey) => {
        setSearchParams(previous => {
            const next = new URLSearchParams(previous);
            next.set('tab', 'integrations');
            next.set('subTab', key);
            return next;
        }, { replace: true });
    };

    return (
        <div className="space-y-5">
            <div className="flex min-w-0 gap-2 overflow-x-auto border-b border-gray-200">
                {visibleIntegrations.map(integration => (
                    <button
                        key={integration.key}
                        type="button"
                        onClick={() => selectSection(integration.key)}
                        aria-current={activeSection === integration.key ? 'page' : undefined}
                        className={`flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors ${
                            activeSection === integration.key
                                ? 'border-emerald-600 text-emerald-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        <i className={`${integration.icon} text-xs`} aria-hidden="true" />
                        {integration.label}
                    </button>
                ))}
            </div>

            {activeSection === 'sms' ? <SmsSettingsPage /> :
                activeSection === 'email' ? <EmailSettingsPage /> :
                    activeSection === 'xendit' ? <XenditPage /> : null}
        </div>
    );
}
