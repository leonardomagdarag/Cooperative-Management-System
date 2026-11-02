import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import Button from '../../components/ui/Button';
import PageHeader from '../../components/ui/PageHeader';
import { useToast } from '../../contexts/ToastContext';

type Decision = 'approve' | 'reject' | 'reconcile_executed' | 'reconcile_failed';
type RequestItem = { id: number; action_type: string; requested_by: string; requested_by_id: number; created_at: string; status: string; in_doubt_since: string | null; payload: Record<string, unknown> };

const FINANCIAL_SETTING_LABELS: Record<string, string> = {
    financial_savings_interest_rate: 'Savings interest rate',
    financial_savings_interest_interval: 'Savings interest interval',
    financial_savings_min_balance_to_earn: 'Minimum savings balance for interest',
    financial_share_capital_interest_rate: 'Share capital interest rate',
    financial_share_capital_interest_interval: 'Share capital interest interval',
    financial_share_capital_min_balance_to_earn: 'Minimum share capital balance for interest',
    financial_interest_min_payout: 'Minimum interest payout',
    financial_interest_notify_members: 'Notify members about interest',
    financial_withdrawal_min_amount: 'Minimum withdrawal',
    financial_withdrawal_max_amount: 'Maximum withdrawal',
    financial_withdrawal_savings_enabled: 'Savings withdrawals',
    financial_withdrawal_share_capital_enabled: 'Share capital withdrawals',
    service_withdrawal_enabled: 'Withdrawal service',
    service_withdrawal_member_enabled: 'Member withdrawals',
    service_withdrawal_officer_enabled: 'Officer withdrawals',
    service_withdrawal_counter_enabled: 'Counter withdrawals',
    service_withdrawal_online_enabled: 'Online withdrawals',
    service_withdrawal_bank_transfer_enabled: 'Bank transfer withdrawals',
};

function humanize(value: string): string {
    return value.replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, letter => letter.toUpperCase());
}

function formatMoney(value: unknown): string {
    const amount = Number(value);
    return Number.isFinite(amount)
        ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
        : String(value);
}

function settingLabel(key: string): string {
    if (FINANCIAL_SETTING_LABELS[key]) return FINANCIAL_SETTING_LABELS[key];
    if (key.startsWith('service_') && key.endsWith('_enabled')) {
        const serviceSetting = key.slice('service_'.length, -'_enabled'.length);
        const modes = ['member', 'officer', 'counter', 'online', 'bank_transfer'];
        const mode = modes.find(candidate => serviceSetting.endsWith(`_${candidate}`));
        const service = mode ? serviceSetting.slice(0, -(mode.length + 1)) : serviceSetting;
        return `${humanize(service)}${mode ? ` ${humanize(mode)}` : ''} service`;
    }
    return humanize(key);
}

function settingValueLabel(key: string, value: unknown): string {
    if (typeof value === 'boolean') return value ? 'Enabled' : 'Disabled';
    if (key.includes('rate')) return `${String(value)}%`;
    if (key.includes('amount') || key.includes('balance') || key.includes('payout')) return formatMoney(value);
    return typeof value === 'string' ? humanize(value) : String(value);
}

function serviceChanges(services: unknown): string[] {
    if (!services || typeof services !== 'object' || Array.isArray(services)) return [];
    return Object.entries(services as Record<string, unknown>).flatMap(([service, flags]) => {
        if (!flags || typeof flags !== 'object' || Array.isArray(flags)) return [];
        return Object.entries(flags as Record<string, unknown>).map(([flag, value]) =>
            `${humanize(service)} ${humanize(flag)}: ${value ? 'Enabled' : 'Disabled'}`
        );
    });
}

function requestSummary(item: RequestItem): string {
    const payload = item.payload;
    const role = String(payload.role ?? '');
    switch (item.action_type) {
        case 'role.create': return `Create role "${role}"`;
        case 'role.rename': return `Rename role "${role}" to "${String(payload.new_name ?? '')}"`;
        case 'role.delete': return `Delete role "${role}"`;
        case 'permissions.update': return `Set permissions for role "${role}"`;
        case 'officer.permissions.update': return `Update officer permissions for account #${String(payload.user_id ?? 'unknown')}`;
        case 'role.assign': {
            const roles = Array.isArray(payload.roles) ? payload.roles.map(String).join(', ') : role;
            return `${payload.new_account ? 'Create officer account with role' : 'Assign role'} ${roles} for account #${String(payload.user_id ?? 'unknown')}`;
        }
        case 'admin.create': return `Activate admin account #${String(payload.user_id ?? 'unknown')}`;
        case 'admin.update': return `Update privileged account #${String(payload.user_id ?? 'unknown')}`;
        case 'admin.delete': return `Deactivate officer account #${String(payload.user_id ?? 'unknown')}`;
        case 'admin.restore': return `Restore officer account #${String(payload.user_id ?? 'unknown')}`;
        case 'admin.force_delete': return `Permanently delete officer account #${String(payload.user_id ?? 'unknown')}`;
        case 'transaction.update': {
            const txnNumber = String(payload.transaction_number ?? `#${String(payload.transaction_id ?? 'unknown')}`);
            return `Edit ledger transaction ${txnNumber}`;
        }
        case 'expense.update': {
            const changes = (payload.changes as Record<string, unknown>) ?? {};
            return `Edit expense #${String(payload.expense_id ?? 'unknown')}${changes.category ? ` (${String(changes.category)})` : ''}`;
        }
        case 'expense.delete': return `Delete expense #${String(payload.expense_id ?? 'unknown')}${payload.category ? ` (${String(payload.category)})` : ''}`;
        case 'loan.delete': return `Delete pending loan ${String(payload.loan_number ?? `#${String(payload.loan_id ?? 'unknown')}`)}`;
        case 'financial.settings.update': return 'Update financial and withdrawal settings';
        case 'financial.interest.run': return `Run interest batch for ${humanize(String(payload.account_type ?? 'account'))}`;
        case 'settings.update': {
            const settings = (payload.settings as Record<string, unknown>) ?? {};
            if (Object.prototype.hasOwnProperty.call(settings, 'collections.approval_stages')) return 'Update collection approval workflow';
            if (payload.services) {
                const services = Object.keys(payload.services as Record<string, unknown>).map(humanize).join(', ');
                return `Change service availability${services ? ` for ${services}` : ''}`;
            }
            return 'Update system settings';
        }
        case 'backup.daily.schedule.update': return `Change daily backup time to ${payload.time}`;
        case 'backup.delete': return `Delete ${humanize(String(payload.tier ?? 'backup'))} backup`;
        case 'backup.restore': return `Restore ${humanize(String(payload.tier ?? 'backup'))} backup`;
        default: return `Review privileged action: ${humanize(item.action_type)}`;
    }
}

function requestChanges(item: RequestItem): string[] {
    if (item.action_type === 'officer.permissions.update') {
        return Object.entries((item.payload.overrides as Record<string, string>) ?? {}).map(([permission, effect]) => `${permission}: ${effect === 'allow' ? 'Grant' : 'Deny'}`);
    }
    if (['permissions.update', 'role.create'].includes(item.action_type)) {
        return (Array.isArray(item.payload.permissions) ? item.payload.permissions : []).map(String);
    }
    if (item.action_type === 'role.assign') {
        const roles = Array.isArray(item.payload.roles) ? item.payload.roles.map(String) : [];
        return [
            ...roles.map(role => `Role: ${humanize(role)}`),
            ...(item.payload.new_account ? ['New officer account will be activated after approval.'] : []),
        ];
    }
    if (item.action_type === 'financial.settings.update') {
        const settings = (item.payload.settings as Record<string, unknown>) ?? {};
        return Object.entries(settings).map(([key, value]) => `${settingLabel(key)}: ${settingValueLabel(key, value)}`);
    }
    if (item.action_type === 'settings.update') {
        const settings = (item.payload.settings as Record<string, unknown>) ?? {};
        const collectionStages = settings['collections.approval_stages'];
        if (Array.isArray(collectionStages)) {
            return collectionStages.map((stage, index) => {
                const item = (stage as Record<string, unknown>) ?? {};
                return `Stage ${index + 1}: ${String(item.label ?? item.key ?? 'Unnamed')} (${String(item.permission ?? 'No permission set')})`;
            });
        }
        const services = serviceChanges(item.payload.services);
        if (services.length) return services;
        return Object.entries(settings).map(([key, value]) => `${settingLabel(key)}: ${settingValueLabel(key, value)}`);
    }
    if (item.action_type === 'financial.interest.run') {
        return [`Account type: ${humanize(String(item.payload.account_type ?? 'unknown'))}`];
    }
    if (item.action_type.startsWith('backup.')) {
        const details: string[] = [];
        if (item.payload.tier) details.push(`Backup tier: ${humanize(String(item.payload.tier))}`);
        if (item.payload.path) details.push(`File: ${String(item.payload.path)}`);
        if (item.payload.scope) details.push(`Restore scope: ${humanize(String(item.payload.scope))}`);
        if (item.action_type === 'backup.daily.schedule.update') details.push(`Daily backup time: ${item.payload.time}`);
        return details;
    }
    if (item.action_type === 'transaction.update' || item.action_type === 'expense.update') {
        const changes = (item.payload.changes as Record<string, unknown>) ?? {};
        return Object.entries(changes).filter(([, value]) => value !== null && value !== undefined && value !== '').map(([key, value]) => {
            const formatted = key === 'amount' ? formatMoney(value) : key.endsWith('_id') ? `#${String(value)}` : typeof value === 'boolean' ? (value ? 'Enabled' : 'Disabled') : String(value);
            return `${humanize(key)}: ${formatted}`;
        });
    }
    if (['expense.delete', 'loan.delete'].includes(item.action_type)) {
        const details: string[] = [];
        if (item.payload.category) details.push(`Category: ${String(item.payload.category)}`);
        if (item.payload.amount !== undefined) details.push(`Amount: ${formatMoney(item.payload.amount)}`);
        if (item.payload.loan_amount !== undefined) details.push(`Loan amount: ${formatMoney(item.payload.loan_amount)}`);
        if (item.payload.expense_date) details.push(`Expense date: ${String(item.payload.expense_date)}`);
        if (item.payload.borrower_id) details.push(`Borrower account: #${String(item.payload.borrower_id)}`);
        if (item.payload.description) details.push(`Description: ${String(item.payload.description)}`);
        return details;
    }
    return [];
}
const itemActionIsPermissions = (item: RequestItem) => ['permissions.update', 'officer.permissions.update'].includes(item.action_type);

export default function OversightApprovalsPage() {
    const [selected, setSelected] = useState<RequestItem | null>(null);
    const [decision, setDecision] = useState<Decision>('approve');
    const [otp, setOtp] = useState('');
    const [note, setNote] = useState('');
    const [otpSent, setOtpSent] = useState(false);
    const [resendCountdown, setResendCountdown] = useState(0);
    const [restoreGraceCountdown, setRestoreGraceCountdown] = useState(0);
    const { addToast } = useToast();
    const client = useQueryClient();
    useEffect(() => {
        if (resendCountdown <= 0) return;
        const timer = window.setTimeout(() => setResendCountdown(seconds => seconds - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [resendCountdown]);
    useEffect(() => {
        if (restoreGraceCountdown <= 0) return;
        const timer = window.setTimeout(() => setRestoreGraceCountdown(seconds => seconds - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [restoreGraceCountdown]);
    const requests = useQuery({
        queryKey: ['oversight-requests'],
        queryFn: async () => (await api.get('/api/oversight/requests')).data.data as RequestItem[],
    });
    const sendOtp = useMutation({
        mutationFn: async ({ item, decision }: { item: RequestItem; decision: Decision }) => api.post(`/api/oversight/requests/${item.id}/otp`, { decision }),
        onSuccess: () => { setOtpSent(true); setOtp(''); setResendCountdown(60); addToast('Verification code sent to your email.', 'success'); },
        onError: (error: any) => addToast(error.response?.data?.message || 'Could not send verification code.', 'error'),
    });
    const decide = useMutation({
        mutationFn: async () => api.post(`/api/oversight/requests/${selected!.id}/decision`, { decision, otp_code: otp, note }),
        onSuccess: (res: any) => {
            const defaultMsg = decision === 'approve' ? 'Request approved.' : decision === 'reject' ? 'Request rejected.' : 'Execution outcome recorded for reconciliation.';
            addToast(res?.data?.message || defaultMsg, 'success', 6000);
            if (selected?.action_type === 'backup.restore' && decision === 'approve') {
                setRestoreGraceCountdown(Number(res?.data?.restore_start_delay_seconds) || 15);
            }
            setSelected(null); setOtp(''); setNote(''); setOtpSent(false); setResendCountdown(0);
            client.invalidateQueries({ queryKey: ['oversight-requests'] });
            client.invalidateQueries({ queryKey: ['members'] });
            client.invalidateQueries({ queryKey: ['expenses'] });
            client.invalidateQueries({ queryKey: ['ledger'] });
            client.invalidateQueries({ queryKey: ['roles'] });
            client.invalidateQueries({ queryKey: ['service-availability'] });
            client.invalidateQueries({ queryKey: ['financial-settings'] });
            client.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (error: any) => addToast(error.response?.data?.message || 'Could not decide this request.', 'error'),
    });
    const select = (item: RequestItem, next: Decision) => {
        setSelected(item); setDecision(next); setOtp(''); setNote(''); setOtpSent(false); setResendCountdown(0);
        sendOtp.mutate({ item, decision: next });
    };
    const clearSelection = () => { setSelected(null); setOtp(''); setOtpSent(false); setResendCountdown(0); };

    const pendingCount = requests.data?.filter(item => item.status === 'pending').length ?? 0;
    const inDoubtCount = requests.data?.filter(item => item.status === 'executing').length ?? 0;

    return <div className="space-y-6">
        <PageHeader eyebrow="Chairman oversight" title="Approval requests" description="Review privileged changes and confirm them with your one-time verification code." icon="fas fa-clipboard-check" action={!requests.isLoading && !requests.isError && <div className="flex gap-3">
            <div className="min-w-28 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3"><p className="text-xs font-medium text-emerald-700">Awaiting review</p><p className="mt-1 text-2xl font-bold text-emerald-900">{pendingCount}</p></div>
            <div className="min-w-28 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"><p className="text-xs font-medium text-amber-700">Needs checking</p><p className="mt-1 text-2xl font-bold text-amber-900">{inDoubtCount}</p></div>
        </div>} />

        {restoreGraceCountdown > 0 && <div role="status" className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p className="font-semibold">System restore begins in {restoreGraceCountdown} seconds.</p>
            <p className="mt-1">The site will show a maintenance page while it restores. Wait for it to return, then sign in again if prompted.</p>
        </div>}

        {selected ? <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
                <button type="button" className="mb-3 inline-flex items-center gap-2 text-sm font-medium text-emerald-700 hover:text-emerald-800" onClick={clearSelection}><i className="fa-solid fa-arrow-left" aria-hidden="true" />Back to requests</button>
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Confirm {decision.startsWith('reconcile_') ? 'reconciliation' : 'decision'}</p>
                <h2 className="mt-1 text-lg font-bold text-slate-900">{requestSummary(selected)}</h2>
                <p className="mt-1 text-sm text-slate-600">Requested by {selected.requested_by} · {new Date(selected.created_at).toLocaleString()}</p>
            </div>
            <div className="space-y-5 p-5 sm:p-6">
                <p className="text-sm text-slate-700">You are about to <strong>{decision === 'approve' ? 'approve' : decision === 'reject' ? 'reject' : decision === 'reconcile_executed' ? 'confirm the action completed' : 'confirm the action did not complete'}</strong> this request.</p>
                {selected.action_type === 'backup.restore' && decision === 'approve' && <p role="status" className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">After approval, the system will wait 15 seconds before entering maintenance mode. The site will be unavailable for the duration of the restore.</p>}
                {requestChanges(selected).length > 0 && <div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{itemActionIsPermissions(selected) ? (selected.action_type === 'permissions.update' ? 'Permissions requested' : 'Requested permission changes') : 'Requested changes'}</p><ul className="mt-3 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">{requestChanges(selected).map(change => <li key={change} className="rounded-lg border border-slate-200 bg-white px-3 py-2">{change}</li>)}</ul></div>}
                {decision !== 'approve' && <label className="block text-sm font-medium text-slate-700">{decision.startsWith('reconcile_') ? 'Reconciliation evidence (required)' : 'Reason (optional)'}<textarea value={note} onChange={e => setNote(e.target.value)} maxLength={1000} rows={3} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" /></label>}
                <div className="max-w-sm">
                    {otpSent ? <><label className="block text-sm font-medium text-slate-700">Email verification code<input autoComplete="one-time-code" inputMode="numeric" value={otp} onChange={e => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))} maxLength={6} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm tracking-[0.35em] shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" /></label><button type="button" className="mt-2 text-sm font-medium text-emerald-700 hover:text-emerald-800 disabled:cursor-not-allowed disabled:text-slate-400" disabled={resendCountdown > 0 || sendOtp.isPending} onClick={() => { setOtp(''); sendOtp.mutate({ item: selected!, decision }); }}>{sendOtp.isPending ? 'Sending code…' : resendCountdown > 0 ? `Resend email in ${resendCountdown}s` : 'Didn’t receive the email? Resend code'}</button></> : <p role="status" className="text-sm text-slate-500"><i className="fa-solid fa-spinner fa-spin mr-2 text-emerald-600" aria-hidden="true" />Sending a one-time code to your account email…</p>}
                </div>
                <div className="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4"><Button variant="secondary" onClick={clearSelection}>Cancel</Button><Button isLoading={decide.isPending} loadingText="Submitting…" disabled={!otpSent || otp.length !== 6 || sendOtp.isPending || (decision.startsWith('reconcile_') && !note.trim())} onClick={() => decide.mutate()}>{decision === 'approve' ? 'Verify and approve' : decision === 'reject' ? 'Verify and reject' : 'Verify reconciliation'}</Button></div>
            </div>
        </section> : <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6"><div><h2 className="font-semibold text-slate-900">Requests requiring your attention</h2><p className="mt-1 text-sm text-slate-500">A second authorized officer is required for these actions.</p></div><button type="button" onClick={() => requests.refetch()} disabled={requests.isFetching} aria-label="Refresh requests" className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"><i className={`fa-solid fa-rotate ${requests.isFetching ? 'fa-spin' : ''}`} aria-hidden="true" /></button></div>
            <div className="divide-y divide-slate-100">
                {requests.isLoading ? <div className="p-10 text-center text-sm text-slate-500"><i className="fa-solid fa-spinner fa-spin mr-2 text-emerald-600" aria-hidden="true" />Loading requests…</div>
                    : requests.isError ? <p role="alert" className="m-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Could not load approval requests. Refresh the page to try again.</p>
                        : requests.data?.length ? requests.data.map(item => <article key={item.id} className="p-5 transition hover:bg-slate-50/70 sm:px-6">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex min-w-0 gap-4">
                                    <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${item.status === 'executing' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}><i className={`fa-solid ${item.status === 'executing' ? 'fa-triangle-exclamation' : 'fa-file-signature'}`} aria-hidden="true" /></span>
                                    <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold text-slate-900">{requestSummary(item)}</h3><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.status === 'executing' ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800'}`}>{item.status === 'executing' ? 'Execution needs review' : 'Pending approval'}</span></div><p className="mt-1 text-sm text-slate-600">Requested by <span className="font-medium text-slate-800">{item.requested_by}</span></p><p className="mt-1 text-xs text-slate-500">{new Date(item.created_at).toLocaleString()}</p>
                                        {requestChanges(item).length > 0 && <p className="mt-2 text-xs text-slate-500">{requestChanges(item).length} {itemActionIsPermissions(item) ? 'permission change' : 'change'}{requestChanges(item).length === 1 ? '' : 's'} requested</p>}
                                        {item.status === 'executing' && <p className="mt-3 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">This action has been running for more than 15 minutes. Check the affected system before recording whether it completed. It will not be retried automatically.</p>}
                                    </div>
                                </div>
                                {item.status === 'executing' ? <div className="flex flex-wrap gap-2 lg:shrink-0"><Button size="sm" onClick={() => select(item, 'reconcile_executed')}>Confirm completed</Button><Button size="sm" variant="danger" onClick={() => select(item, 'reconcile_failed')}>Confirm failed</Button></div> : <div className="flex gap-2 lg:shrink-0"><Button size="sm" onClick={() => select(item, 'approve')}>Review and approve</Button><Button size="sm" variant="secondary" onClick={() => select(item, 'reject')}>Reject</Button></div>}
                            </div>
                        </article>) : <div className="px-5 py-14 text-center sm:px-6"><span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i className="fa-solid fa-circle-check text-2xl" aria-hidden="true" /></span><h3 className="mt-4 font-semibold text-slate-900">You are all caught up</h3><p className="mt-1 text-sm text-slate-500">New countersign requests will appear here.</p></div>}
            </div>
        </section>}
    </div>;
}
