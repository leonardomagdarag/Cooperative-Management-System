import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useToast } from '../../contexts/ToastContext';
import {
    getFinancialSettings,
    updateFinancialSettings,
    runInterestNow,
    type FinancialAccountConfig,
    type FinancialAccountType,
    type FinancialSettings,
    type FinancialSettingsPayload,
    type FinancialWithdrawalConfig,
    type InterestInterval,
} from '../../api/settings';
import SettingsTabHeader from './SettingsTabHeader';

const INTERVALS: Array<{ value: InterestInterval; label: string }> = [
    { value: 'daily', label: 'Daily' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
];

const formatDateTime = (iso?: string | null) => (iso ? new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Never');

function Toggle({ checked, onChange, disabled = false, label }: { checked: boolean; onChange: (v: boolean) => void; disabled?: boolean; label?: string }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => !disabled && onChange(!checked)}
            className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition ${checked ? 'bg-emerald-500' : 'bg-gray-300'} ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
        >
            <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition ${checked ? 'translate-x-6' : 'translate-x-1'}`} />
        </button>
    );
}

function NumberField({ label, value, onChange, suffix, min = 0, step = '0.01', hint }: { label: string; value: number; onChange: (v: number) => void; suffix?: string; min?: number; step?: string; hint?: string }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <div className="relative">
                <input
                    type="number"
                    min={min}
                    step={step}
                    value={value}
                    onChange={e => onChange(Number(e.target.value))}
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                />
                {suffix && <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">{suffix}</span>}
            </div>
            {hint && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
        </div>
    );
}

function AccountCard({ title, icon, config, onChange }: { title: string; icon: string; config: FinancialAccountConfig; onChange: (next: FinancialAccountConfig) => void }) {
    return (
        <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div className="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className={icon}></i></div>
                <h3 className="font-semibold text-gray-900">{title}</h3>
            </div>
            <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                <NumberField label="Annual interest rate" value={config.rate} onChange={v => onChange({ ...config, rate: v })} suffix="%" step="0.01" hint="Nominal per year, prorated by the interval." />
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">Crediting interval</label>
                    <select value={config.interval} onChange={e => onChange({ ...config, interval: e.target.value as InterestInterval })} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        {INTERVALS.map(i => <option key={i.value} value={i.value}>{i.label}</option>)}
                    </select>
                </div>
                <NumberField label="Minimum balance to earn" value={config.min_balance} onChange={v => onChange({ ...config, min_balance: v })} suffix="₱" hint="Accounts below this earn nothing." />
            </div>
        </section>
    );
}

export default function FinancialAccountsPage() {
    const { addToast } = useToast();
    const queryClient = useQueryClient();

    const { data, isLoading, isError, error } = useQuery({ queryKey: ['financial-settings'], queryFn: getFinancialSettings });
    const loaded = data?.data?.data as FinancialSettings | undefined;

    const [draft, setDraft] = useState<FinancialSettings | null>(null);
    const [notifyAffected, setNotifyAffected] = useState(false);

    useEffect(() => {
        if (loaded) { setDraft(loaded); setNotifyAffected(false); }
    }, [data]);

    const dirty = useMemo(() => {
        if (!loaded || !draft) return false;
        return JSON.stringify({ ...loaded, last_credited: undefined }) !== JSON.stringify({ ...draft, last_credited: undefined });
    }, [loaded, draft]);

    const saveMutation = useMutation({
        mutationFn: (payload: FinancialSettingsPayload) => updateFinancialSettings(payload),
        onSuccess: (response) => {
            queryClient.invalidateQueries({ queryKey: ['financial-settings'] });
            const requestId = response.data?.request_id;
            const isPending = response.data?.status === 'pending' || response?.status === 202;
            const message = requestId
                ? `Financial settings change queued for countersign approval (Request #${requestId}).`
                : (response.data?.message || 'Financial settings updated.');
            addToast(message, isPending ? 'info' : 'success', isPending ? 8000 : undefined);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update financial settings.', 'error'),
    });

    const runMutation = useMutation({
        mutationFn: (accountType: FinancialAccountType) => runInterestNow({ account_type: accountType }),
        onSuccess: (response, accountType) => {
            const requestId = response.data?.request_id;
            const isPending = response.data?.status === 'pending' || response?.status === 202;
            const message = response.data?.message || `Interest run for ${accountType === 'share-capital' ? 'share capital' : 'savings'} queued for countersign approval${requestId ? ` (Request #${requestId})` : ''}.`;
            addToast(message, isPending ? 'info' : 'success', isPending ? 8000 : undefined);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to request an interest run.', 'error'),
    });

    const updateSavings = (next: FinancialAccountConfig) => setDraft(d => (d ? { ...d, savings: next } : d));
    const updateShareCapital = (next: FinancialAccountConfig) => setDraft(d => (d ? { ...d, share_capital: next } : d));
    const updateWithdrawal = (patch: Partial<FinancialWithdrawalConfig>) => setDraft(d => (d ? { ...d, withdrawal: { ...d.withdrawal, ...patch } } : d));
    const updateInterest = (patch: Partial<FinancialSettings['interest']>) => setDraft(d => (d ? { ...d, interest: { ...d.interest, ...patch } } : d));

    const save = () => {
        if (!draft) return;
        saveMutation.mutate({
            savings: draft.savings,
            share_capital: draft.share_capital,
            interest: draft.interest,
            withdrawal: draft.withdrawal,
            notify_affected: notifyAffected,
        });
    };

    const cancel = () => { if (loaded) { setDraft(loaded); setNotifyAffected(false); } };

    if (isLoading || !draft) {
        return <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />)}</div>;
    }

    if (isError) {
        return <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">Failed to load financial settings: {(error as any)?.message || 'Unknown error'}</div>;
    }

    return (
        <div className={`space-y-4 ${dirty ? 'pb-24' : ''}`}>
            <SettingsTabHeader
                eyebrow="High-security configuration"
                title="Financial Accounts"
                description="Interest crediting, withdrawal limits, and account withdrawal switches for member accounts."
                icon="fas fa-piggy-bank"
                helpText="Changes here are high-security and require chairman countersign approval before they take effect. Interest is a nominal annual rate prorated by the chosen interval (daily = /365, monthly = /12, yearly = /1)."
            />

            <div className="rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800">
                <i className="fas fa-shield-halved mr-2"></i>
                Every change on this tab is queued for countersign approval by the chairman before it is applied.
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <AccountCard title="Savings Interest" icon="fas fa-wallet" config={draft.savings} onChange={updateSavings} />
                <AccountCard title="Share Capital Interest" icon="fas fa-hand-holding-dollar" config={draft.share_capital} onChange={updateShareCapital} />
            </div>

            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Interest Run Options</h3>
                    <p className="mt-0.5 text-xs text-gray-500">How each interest batch is posted.</p>
                </div>
                <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                    <NumberField label="Minimum interest payout" value={draft.interest.min_payout} onChange={v => updateInterest({ min_payout: v })} suffix="₱" hint="Per-member interest below this is skipped (0 credits any amount above zero)." />
                    <div className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 px-4 py-3 sm:col-span-2">
                        <div>
                            <p className="text-sm font-medium text-gray-800">Notify members when interest is credited</p>
                            <p className="mt-0.5 text-xs text-gray-500">Sends each member an account notification when interest lands.</p>
                        </div>
                        <Toggle checked={draft.interest.notify_members} onChange={v => updateInterest({ notify_members: v })} label="Notify members on interest credit" />
                    </div>
                </div>
            </section>

            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Withdrawal Rules</h3>
                    <p className="mt-0.5 text-xs text-gray-500">Limits and channel availability for member withdrawals. Moved here from Service Availability.</p>
                </div>
                <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                    <NumberField label="Minimum withdrawal amount" value={draft.withdrawal.min_amount} onChange={v => updateWithdrawal({ min_amount: v })} suffix="₱" />
                    <NumberField label="Maximum withdrawal amount" value={draft.withdrawal.max_amount} onChange={v => updateWithdrawal({ max_amount: v })} suffix="₱" hint="0 means no cap beyond the available balance." />

                    <div className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium text-gray-800">Allow savings withdrawals</p>
                            <p className="mt-0.5 text-xs text-gray-500">When off, members cannot withdraw from savings.</p>
                        </div>
                        <Toggle checked={draft.withdrawal.savings_enabled} onChange={v => updateWithdrawal({ savings_enabled: v })} label="Allow savings withdrawals" />
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 px-4 py-3 opacity-70">
                        <div>
                            <p className="text-sm font-medium text-gray-800">Allow share-capital withdrawals</p>
                            <p className="mt-0.5 text-xs text-gray-500">Reserved — share-capital withdrawals are not available yet.</p>
                        </div>
                        <Toggle checked={draft.withdrawal.share_capital_enabled} onChange={() => undefined} disabled label="Allow share capital withdrawals" />
                    </div>
                </div>

                <div className="border-t border-gray-100 px-5 py-4">
                    <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Availability</p>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {([
                            ['enabled', 'Withdrawal service', 'Global switch for withdrawals.'],
                            ['member', 'Members', 'Allow members to request withdrawals.'],
                            ['officer', 'Officers', 'Allow officers to process withdrawals.'],
                            ['counter', 'Counter (OTC)', 'Allow over-the-counter withdrawals.'],
                            ['online', 'Online', 'Allow online withdrawals.'],
                            ['bank_transfer', 'Bank Transfer', 'Allow bank transfer withdrawals.'],
                        ] as Array<[keyof FinancialWithdrawalConfig, string, string]>).map(([key, label, description]) => (
                            <div key={key} className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-800">{label}</p>
                                    <p className="mt-0.5 text-xs text-gray-500">{description}</p>
                                </div>
                                <Toggle checked={Boolean(draft.withdrawal[key])} onChange={v => updateWithdrawal({ [key]: v } as Partial<FinancialWithdrawalConfig>)} label={label} />
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Manual Interest Run</h3>
                    <p className="mt-0.5 text-xs text-gray-500">Run a batch now, outside the schedule. Countersigned, and idempotent per period.</p>
                </div>
                <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                    {([
                        ['savings', 'Savings', draft.last_credited.savings],
                        ['share-capital', 'Share Capital', draft.last_credited.share_capital],
                    ] as Array<[FinancialAccountType, string, string | null]>).map(([type, label, lastCredited]) => (
                        <div key={type} className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 px-4 py-3">
                            <div>
                                <p className="text-sm font-medium text-gray-800">{label}</p>
                                <p className="mt-0.5 text-xs text-gray-500">Last credited: {formatDateTime(lastCredited)}</p>
                                <p className="mt-0.5 text-xs text-gray-400">{type === 'savings' ? draft.savings.rate : draft.share_capital.rate}% p.a. · {type === 'savings' ? draft.savings.interval : draft.share_capital.interval}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => runMutation.mutate(type)}
                                disabled={runMutation.isPending || dirty}
                                className="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {runMutation.isPending && runMutation.variables === type ? 'Queuing…' : 'Run now'}
                            </button>
                        </div>
                    ))}
                </div>
                {dirty && <p className="px-5 pb-4 text-xs text-amber-600">Save or discard your unsaved changes before running a batch.</p>}
            </section>

            {dirty && (
                <div className="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(15,23,42,0.08)] backdrop-blur sm:px-6">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" checked={notifyAffected} onChange={e => setNotifyAffected(e.target.checked)} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                            Notify affected users if a withdrawal channel is disabled
                        </label>
                        <div className="flex gap-2">
                            <button type="button" onClick={cancel} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                            <button type="button" onClick={save} disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                {saveMutation.isPending ? 'Submitting…' : 'Save Changes'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
