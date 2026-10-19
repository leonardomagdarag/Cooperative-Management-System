import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import Button from '../../components/ui/Button';
import { useToast } from '../../contexts/ToastContext';

type Decision = 'approve' | 'reject' | 'reconcile_executed' | 'reconcile_failed';
type RequestItem = { id: number; action_type: string; requested_by: string; requested_by_id: number; created_at: string; status: string; in_doubt_since: string | null; payload: Record<string, unknown> };

function requestSummary(item: RequestItem): string {
    const payload = item.payload;
    const role = String(payload.role ?? '');
    switch (item.action_type) {
        case 'role.create': return `Create role "${role}" with ${Array.isArray(payload.permissions) ? payload.permissions.length : 0} permissions`;
        case 'role.rename': return `Rename role "${role}" to "${String(payload.new_name ?? '')}"`;
        case 'role.delete': return `Delete role "${role}"`;
        case 'permissions.update': return `Update ${Array.isArray(payload.permissions) ? payload.permissions.length : 0} permissions for "${role}"`;
        case 'officer.permissions.update': return `Change ${Object.keys((payload.overrides as Record<string, unknown>) ?? {}).length} permission overrides for account #${String(payload.user_id ?? '—')}`;
        case 'role.assign': {
            const roles = Array.isArray(payload.roles) ? payload.roles.map(String).join(', ') : role;
            return `Assign ${roles} to account #${String(payload.user_id ?? '—')}`;
        }
        case 'transaction.update': {
            const txnNumber = String(payload.transaction_number ?? `#${String(payload.transaction_id ?? '—')}`);
            return `Apply edits to ledger transaction ${txnNumber}`;
        }
        case 'expense.update': {
            const changes = (payload.changes as Record<string, unknown>) ?? {};
            return `Apply edits to expense #${String(payload.expense_id ?? '—')} (${String(changes.category ?? '—')})`;
        }
        case 'expense.delete': {
            return `Delete expense #${String(payload.expense_id ?? '—')} (${String(payload.category ?? '—')})`;
        }
        default: return `${item.action_type.replaceAll('.', ' ')} for account #${String(payload.user_id ?? '—')}`;
    }
}

const EDIT_CHANGES_TYPES = ['transaction.update', 'expense.update'];

function requestChanges(item: RequestItem): string[] {
    if (item.action_type === 'officer.permissions.update') {
        return Object.entries((item.payload.overrides as Record<string, string>) ?? {}).map(([permission, effect]) => `${permission}: ${effect === 'allow' ? 'grant' : 'deny'}`);
    }
    if (item.action_type === 'permissions.update') {
        return (Array.isArray(item.payload.permissions) ? item.payload.permissions : []).map(String);
    }
    if (EDIT_CHANGES_TYPES.includes(item.action_type)) {
        const changes = (item.payload.changes as Record<string, unknown>) ?? {};
        return Object.entries(changes)
            .filter(([, value]) => value !== null && value !== undefined && value !== '')
            .map(([key, value]) => `${key.replaceAll('_', ' ')}: ${key === 'amount' ? `₱${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 })}` : String(value)}`);
    }
    if (item.action_type === 'expense.delete') {
        const payload = item.payload;
        const details: string[] = [];
        if (payload.category) details.push(`category: ${String(payload.category)}`);
        if (payload.amount !== undefined) details.push(`amount: ₱${Number(payload.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}`);
        if (payload.expense_date) details.push(`expense date: ${String(payload.expense_date)}`);
        if (payload.description) details.push(`description: ${String(payload.description)}`);
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
    const { addToast } = useToast();
    const client = useQueryClient();
    const requests = useQuery({
        queryKey: ['oversight-requests'],
        queryFn: async () => (await api.get('/api/oversight/requests')).data.data as RequestItem[],
    });
    const sendOtp = useMutation({
        mutationFn: async ({ item, decision }: { item: RequestItem; decision: Decision }) => api.post(`/api/oversight/requests/${item.id}/otp`, { decision }),
        onSuccess: () => { setOtpSent(true); addToast('Verification code sent to your email.', 'success'); },
        onError: (error: any) => addToast(error.response?.data?.message || 'Could not send verification code.', 'error'),
    });
    const decide = useMutation({
        mutationFn: async () => api.post(`/api/oversight/requests/${selected!.id}/decision`, { decision, otp_code: otp, note }),
        onSuccess: (res: any) => {
            const defaultMsg = decision === 'approve' ? 'Request approved.' : decision === 'reject' ? 'Request rejected.' : 'Execution outcome recorded for reconciliation.';
            addToast(res?.data?.message || defaultMsg, 'success', 6000);
            setSelected(null); setOtp(''); setNote(''); setOtpSent(false);
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
        setSelected(item); setDecision(next); setOtp(''); setNote(''); setOtpSent(false);
        sendOtp.mutate({ item, decision: next });
    };

    const pendingCount = requests.data?.filter(item => item.status === 'pending').length ?? 0;
    const inDoubtCount = requests.data?.filter(item => item.status === 'executing').length ?? 0;

    return <div className="space-y-6">
        <header className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-700 via-green-700 to-cyan-800 p-6 text-white shadow-sm sm:p-8">
            <div className="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-sm font-semibold uppercase tracking-wider text-emerald-100">Chairman oversight</p>
                    <h1 className="mt-1 text-2xl font-bold sm:text-3xl">Approval requests</h1>
                    <p className="mt-2 max-w-2xl text-sm text-emerald-50">Review privileged changes and confirm them with your one-time verification code.</p>
                </div>
                {!requests.isLoading && !requests.isError && <div className="flex gap-3">
                    <div className="min-w-28 rounded-xl border border-white/20 bg-white/10 px-4 py-3"><p className="text-xs font-medium text-emerald-100">Awaiting review</p><p className="mt-1 text-2xl font-bold">{pendingCount}</p></div>
                    <div className="min-w-28 rounded-xl border border-amber-200/30 bg-amber-300/15 px-4 py-3"><p className="text-xs font-medium text-amber-100">Needs checking</p><p className="mt-1 text-2xl font-bold">{inDoubtCount}</p></div>
                </div>}
            </div>
        </header>

        {selected ? <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
                <button type="button" className="mb-3 inline-flex items-center gap-2 text-sm font-medium text-emerald-700 hover:text-emerald-800" onClick={() => { setSelected(null); setOtpSent(false); }}><i className="fa-solid fa-arrow-left" aria-hidden="true" />Back to requests</button>
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Confirm {decision.startsWith('reconcile_') ? 'reconciliation' : 'decision'}</p>
                <h2 className="mt-1 text-lg font-bold text-slate-900">{requestSummary(selected)}</h2>
                <p className="mt-1 text-sm text-slate-600">Requested by {selected.requested_by} · {new Date(selected.created_at).toLocaleString()}</p>
            </div>
            <div className="space-y-5 p-5 sm:p-6">
                <p className="text-sm text-slate-700">You are about to <strong>{decision === 'approve' ? 'approve' : decision === 'reject' ? 'reject' : decision === 'reconcile_executed' ? 'confirm the action completed' : 'confirm the action did not complete'}</strong> this request.</p>
                {requestChanges(selected).length > 0 && <div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{itemActionIsPermissions(selected) ? 'Requested permission changes' : 'Requested changes'}</p><ul className="mt-3 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">{requestChanges(selected).map(change => <li key={change} className="rounded-lg border border-slate-200 bg-white px-3 py-2">{change}</li>)}</ul></div>}
                {decision !== 'approve' && <label className="block text-sm font-medium text-slate-700">{decision.startsWith('reconcile_') ? 'Reconciliation evidence (required)' : 'Reason (optional)'}<textarea value={note} onChange={e => setNote(e.target.value)} maxLength={1000} rows={3} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" /></label>}
                <div className="max-w-sm">
                    {otpSent ? <label className="block text-sm font-medium text-slate-700">Email verification code<input autoComplete="one-time-code" inputMode="numeric" value={otp} onChange={e => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))} maxLength={6} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm tracking-[0.35em] shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" /></label> : <p role="status" className="text-sm text-slate-500"><i className="fa-solid fa-spinner fa-spin mr-2 text-emerald-600" aria-hidden="true" />Sending a one-time code to your account email…</p>}
                </div>
                <div className="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4"><Button variant="secondary" onClick={() => { setSelected(null); setOtpSent(false); }}>Cancel</Button><Button isLoading={decide.isPending} loadingText="Submitting…" disabled={!otpSent || otp.length !== 6 || sendOtp.isPending || (decision.startsWith('reconcile_') && !note.trim())} onClick={() => decide.mutate()}>{decision === 'approve' ? 'Verify and approve' : decision === 'reject' ? 'Verify and reject' : 'Verify reconciliation'}</Button></div>
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
