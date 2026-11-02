import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useToast } from '../../contexts/ToastContext';
import { getServiceAvailability, updateServiceAvailability, type SettingsServiceAvailabilityData, type ServiceAvailabilityFlags } from '../../api/settings';
import SettingsTabHeader from './SettingsTabHeader';
import LoadingIndicator from '../../components/ui/LoadingIndicator';

type ServiceKey = keyof SettingsServiceAvailabilityData;
type AvailabilityFlag = keyof ServiceAvailabilityFlags;

const serviceConfig: Record<ServiceKey, { label: string; description: string; icon: string; flags: Array<{ key: AvailabilityFlag; label: string; description: string }> }> = {
    registration: { label: 'Member Registration', description: 'Control whether visitors can create member accounts from the public website.', icon: 'fas fa-user-plus', flags: [] },
    login: { label: 'Login', description: 'Control whether users can log in to the system.', icon: 'fas fa-sign-in-alt', flags: [
        { key: 'member', label: 'Members', description: 'Allow members to log in.' },
        { key: 'officer', label: 'Officers', description: 'Allow officers to log in.' },
    ] },
    deposit: { label: 'Deposits', description: 'Members and officers adding money to cooperative accounts.', icon: 'fas fa-plus-circle', flags: [
        { key: 'member', label: 'Members', description: 'Allow members to make deposits.' }, { key: 'officer', label: 'Officers', description: 'Allow officers to process deposits.' },
        { key: 'counter', label: 'Counter (OTC)', description: 'Allow over-the-counter deposits.' }, { key: 'online', label: 'Online', description: 'Allow online deposits.' },
    ] },
    loan_application: { label: 'Loan Applications', description: 'Control access to new loan applications.', icon: 'fas fa-file-contract', flags: [
        { key: 'member', label: 'Members', description: 'Allow members to apply for loans.' }, { key: 'officer', label: 'Officers', description: 'Allow officers to process applications.' },
    ] },
    loan_payment: { label: 'Loan Payments', description: 'Members making payments toward existing loans.', icon: 'fas fa-credit-card', flags: [
        { key: 'member', label: 'Members', description: 'Allow members to make loan payments.' }, { key: 'officer', label: 'Officers', description: 'Allow officers to process loan payments.' },
        { key: 'counter', label: 'Counter (OTC)', description: 'Allow over-the-counter loan payments.' }, { key: 'online', label: 'Online', description: 'Allow online loan payments.' },
    ] },
};

const flagLabel: Record<string, string> = { global: 'Global access', member: 'Member access', officer: 'Officer access', counter: 'Counter (OTC)', online: 'Online', bank_transfer: 'Bank Transfer' };

function StatusPill({ enabled, onClick }: { enabled: boolean; onClick: () => void }) {
    return <button type="button" onClick={onClick} aria-pressed={enabled} className={`rounded-full px-3 py-1 text-xs font-semibold transition ${enabled ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}>{enabled ? 'Enabled' : 'Disabled'}</button>;
}

export default function ServiceAvailabilityPage() {
    const { addToast } = useToast();
    const queryClient = useQueryClient();
    const [originalFlags, setOriginalFlags] = useState<Partial<SettingsServiceAvailabilityData>>({});
    const [editedFlags, setEditedFlags] = useState<Partial<SettingsServiceAvailabilityData>>({});
    const [notifyAffected, setNotifyAffected] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const { data, isLoading, isError, error } = useQuery({ queryKey: ['service-availability'], queryFn: getServiceAvailability });
    const loadedFlags = data?.data?.data as SettingsServiceAvailabilityData | undefined;

    useEffect(() => {
        if (loadedFlags) { setOriginalFlags(loadedFlags); setEditedFlags(loadedFlags); }
    }, [data]);

    const changes = useMemo(() => {
        const result: Array<{ service: ServiceKey; label: string; flag: string; enabled: boolean }> = [];
        (Object.keys(serviceConfig) as ServiceKey[]).forEach(service => {
            const before = originalFlags[service]; const after = editedFlags[service];
            if (!before || !after) return;
            const config = serviceConfig[service];
            if (config.flags.length === 0) {
                const previous = before.global ?? true;
                const current = after.global ?? true;
                if (previous !== current) {
                    result.push({ service, label: config.label, flag: '', enabled: current });
                }
                return;
            }
            new Set([...Object.keys(before), ...Object.keys(after)]).forEach(key => {
                const flag = key as AvailabilityFlag; const previous = before[flag] ?? true; const current = after[flag] ?? true;
                if (previous !== current) result.push({ service, label: config.label, flag: flagLabel[flag] || flag, enabled: current });
            });
        });
        return result;
    }, [editedFlags, originalFlags]);

    const saveMutation = useMutation({
        mutationFn: (payload: { services: SettingsServiceAvailabilityData; notify_affected: boolean }) => updateServiceAvailability(payload),
        onSuccess: (response) => {
            queryClient.invalidateQueries({ queryKey: ['service-availability'] });
            setShowConfirm(false);
            setNotifyAffected(false);
            const requestId = response.data?.request_id;
            const isPending = response.data?.status === 'pending' || response?.status === 202;
            addToast(
                requestId
                    ? `Service availability update requested and queued for countersign approval (Request #${requestId}).`
                    : (response.data?.message || 'Service availability updated successfully.'),
                isPending ? 'info' : 'success',
                isPending ? 8000 : undefined,
            );
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update service availability.', 'error'),
    });

    const updateFlag = (service: ServiceKey, flag: AvailabilityFlag, value: boolean) => {
        setEditedFlags(previous => {
            const currentService = previous[service] || {};
            const nextService = { ...currentService, [flag]: value };
            if (service === 'registration' && flag === 'global') {
                nextService.member = value;
            }
            return { ...previous, [service]: nextService };
        });
    };
    const cancelChanges = () => { setEditedFlags(originalFlags); setNotifyAffected(false); };
    const confirmSave = () => { if (changes.length > 0) saveMutation.mutate({ services: editedFlags as SettingsServiceAvailabilityData, notify_affected: notifyAffected }); };

    if (isLoading) return <LoadingIndicator message="Loading service settings..." />;
    if (isError) return <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">Failed to load service availability: {(error as any)?.message || 'Unknown error'}</div>;

    return <div className={`space-y-4 ${changes.length > 0 ? 'pb-20' : ''}`}>
        <SettingsTabHeader eyebrow="System operations" title="Service Availability" description="Control which services, audiences, and transaction channels are currently available." icon="fas fa-toggle-on" helpText="The global switch controls the whole service. Audience switches control member or officer access; channel switches control Counter, Online, or Bank Transfer. Disabled services cannot be used until re-enabled." />

        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            {(Object.keys(serviceConfig) as ServiceKey[]).map(service => {
                const config = serviceConfig[service]; const flags = editedFlags[service]; if (!flags) return null;
                const hasSubFlags = config.flags.length > 0;
                return <section key={service} className="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className={`flex items-start justify-between gap-4 px-5 py-4 ${hasSubFlags ? 'border-b border-gray-100' : ''}`}>
                        <div className="flex min-w-0 items-center gap-3"><div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className={config.icon}></i></div><div className="min-w-0"><h3 className="font-semibold text-gray-900">{config.label}</h3><p className="mt-0.5 text-xs text-gray-500">{config.description}</p></div></div>
                        <StatusPill enabled={flags.global ?? true} onClick={() => updateFlag(service, 'global', !(flags.global ?? true))} />
                    </div>
                    {hasSubFlags && (
                        <div className="divide-y divide-gray-100">{config.flags.map(item => { const enabled = flags[item.key] ?? true; return <div key={item.key} className="flex items-center justify-between gap-4 px-5 py-3"><div className="min-w-0"><p className="text-sm font-medium text-gray-800">{item.label}</p><p className="mt-0.5 text-xs text-gray-500">{item.description}</p></div><StatusPill enabled={enabled} onClick={() => updateFlag(service, item.key, !enabled)} /></div>; })}</div>
                    )}
                </section>;
            })}
        </div>

        {changes.length > 0 && <div className="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(15,23,42,0.08)] backdrop-blur sm:px-6"><div className="mx-auto flex max-w-7xl items-center justify-between gap-3"><p className="text-sm font-medium text-amber-800">{changes.length} unsaved change{changes.length === 1 ? '' : 's'}</p><div className="flex gap-2"><button type="button" onClick={cancelChanges} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button><button type="button" onClick={() => setShowConfirm(true)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save Changes</button></div></div></div>}

        {showConfirm && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => setShowConfirm(false)}><div className="w-full max-w-lg rounded-xl bg-white shadow-2xl" onClick={event => event.stopPropagation()}><div className="flex items-center justify-between border-b border-gray-100 px-5 py-4"><div><h3 className="font-semibold text-gray-900">Apply availability changes?</h3><p className="mt-0.5 text-sm text-gray-500">Review the changes before they take effect.</p></div><button type="button" onClick={() => setShowConfirm(false)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100" aria-label="Close"><i className="fas fa-times"></i></button></div><div className="max-h-72 space-y-2 overflow-y-auto p-5">{changes.map((change, index) => <div key={`${change.service}-${change.flag}-${index}`} className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm"><span className="text-gray-700">{change.label}{change.flag ? ` · ${change.flag}` : ''}</span><span className={`font-semibold ${change.enabled ? 'text-emerald-600' : 'text-red-600'}`}>{change.enabled ? 'Enable' : 'Disable'}</span></div>)}<label className="flex cursor-pointer items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><input type="checkbox" checked={notifyAffected} onChange={event => setNotifyAffected(event.target.checked)} className="mt-0.5 rounded border-amber-300 text-emerald-600 focus:ring-emerald-500" /><span>Automatically message affected users when a service or channel becomes unavailable.</span></label></div><div className="flex justify-end gap-2 border-t border-gray-100 px-5 py-4"><button type="button" onClick={() => setShowConfirm(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button><button type="button" onClick={confirmSave} disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{saveMutation.isPending ? 'Applying...' : 'Confirm Changes'}</button></div></div></div>}
    </div>;
}
