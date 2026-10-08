import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import Modal from '../../components/ui/Modal';
import Button from '../../components/ui/Button';
import { useToast } from '../../contexts/ToastContext';

type Decision = 'approve' | 'reject' | 'reconcile_executed' | 'reconcile_failed';
type RequestItem = { id: number; action_type: string; requested_by: string; requested_by_id: number; created_at: string; status: string; in_doubt_since: string | null; payload: Record<string, unknown> };

function requestSummary(item: RequestItem): string {
    const payload = item.payload;
    const role = String(payload.role ?? '');
    switch (item.action_type) {
        case 'role.create':
            return `Create role “${role}” with ${Array.isArray(payload.permissions) ? payload.permissions.length : 0} permissions`;
        case 'role.rename':
            return `Rename role “${role}” to “${String(payload.new_name ?? '')}”`;
        case 'role.delete':
            return `Delete role “${role}”`;
        case 'permissions.update':
            return `Update ${Array.isArray(payload.permissions) ? payload.permissions.length : 0} permissions for “${role}”`;
        case 'officer.permissions.update':
            return `Change ${Object.keys((payload.overrides as Record<string, unknown>) ?? {}).length} permission overrides for account #${String(payload.user_id ?? '—')}`;
        case 'role.assign': {
            const roles = Array.isArray(payload.roles) ? payload.roles.map(String).join(', ') : role;
            return `Assign ${roles} to account #${String(payload.user_id ?? '—')}`;
        }
        default:
            return `${item.action_type.replaceAll('.', ' ')} for account #${String(payload.user_id ?? '—')}`;
    }
}

function requestChanges(item: RequestItem): string[] {
    if (item.action_type === 'officer.permissions.update') {
        return Object.entries((item.payload.overrides as Record<string, string>) ?? {})
            .map(([permission, effect]) => `${permission}: ${effect === 'allow' ? 'grant' : 'deny'}`);
    }
    if (item.action_type === 'permissions.update') {
        return (Array.isArray(item.payload.permissions) ? item.payload.permissions : []).map(String);
    }
    return [];
}

export default function OversightApprovalModal({ open, onClose, canDecide }: { open: boolean; onClose: () => void; canDecide: boolean }) {
    const [selected, setSelected] = useState<RequestItem | null>(null);
    const [decision, setDecision] = useState<Decision>('approve');
    const [otp, setOtp] = useState('');
    const [note, setNote] = useState('');
    const [otpSent, setOtpSent] = useState(false);
    const { addToast } = useToast();
    const client = useQueryClient();
    const requests = useQuery({
        queryKey: ['oversight-requests'],
        enabled: open,
        queryFn: async () => (await api.get('/api/oversight/requests')).data.data as RequestItem[],
    });
    const sendOtp = useMutation({
        mutationFn: async ({ item, decision }: { item: RequestItem; decision: Decision }) => api.post(`/api/oversight/requests/${item.id}/otp`, { decision }),
        onSuccess: () => { setOtpSent(true); addToast('Verification code sent to your email.', 'success'); },
        onError: (error: any) => addToast(error.response?.data?.message || 'Could not send verification code.', 'error'),
    });
    const decide = useMutation({
        mutationFn: async () => api.post(`/api/oversight/requests/${selected!.id}/decision`, { decision, otp_code: otp, note }),
        onSuccess: () => {
            addToast(decision === 'approve' ? 'Request approved.' : decision === 'reject' ? 'Request rejected.' : 'Execution outcome recorded for reconciliation.', 'success');
            setSelected(null); setOtp(''); setNote(''); setOtpSent(false);
            client.invalidateQueries({ queryKey: ['oversight-requests'] });
            client.invalidateQueries({ queryKey: ['members'] });
        },
        onError: (error: any) => addToast(error.response?.data?.message || 'Could not decide this request.', 'error'),
    });

    const select = (item: RequestItem, next: Decision) => {
        setSelected(item); setDecision(next); setOtp(''); setNote(''); setOtpSent(false);
        sendOtp.mutate({ item, decision: next });
    };

    return <Modal open={open} onClose={onClose} title="Privileged action approvals" size="lg">
        {selected ? <div className="space-y-4">
            <button type="button" className="text-sm text-emerald-700" onClick={() => { setSelected(null); setOtpSent(false); }}>← Back to requests</button>
            <p className="text-sm text-gray-700">{decision === 'approve' ? 'Approve' : decision === 'reject' ? 'Reject' : 'Reconcile'} <strong>{requestSummary(selected)}</strong>, requested by {selected.requested_by}?</p>
            {requestChanges(selected).length > 0 && <div className="rounded-lg border border-slate-200 bg-slate-50 p-3"><p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested permission changes</p><ul className="mt-2 max-h-48 space-y-1 overflow-y-auto text-sm text-slate-700">{requestChanges(selected).map(change => <li key={change}>{change}</li>)}</ul></div>}
            {decision !== 'approve' && <label className="block text-sm">{decision.startsWith('reconcile_') ? 'Reconciliation evidence (required)' : 'Reason (optional)'}<textarea value={note} onChange={e => setNote(e.target.value)} maxLength={1000} className="mt-1 w-full rounded border p-2" /></label>}
            {otpSent ? <label className="block text-sm">Email verification code<input autoComplete="one-time-code" inputMode="numeric" value={otp} onChange={e => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))} maxLength={6} className="mt-1 w-full rounded border p-2" /></label> : <p className="text-sm text-gray-500">Sending a one-time code to your account email…</p>}
            <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => { setSelected(null); setOtpSent(false); }}>Cancel</Button><Button disabled={!otpSent || otp.length !== 6 || decide.isPending || sendOtp.isPending || (decision.startsWith('reconcile_') && !note.trim())} onClick={() => decide.mutate()}>{decide.isPending ? 'Submitting…' : decision === 'approve' ? 'Verify and approve' : decision === 'reject' ? 'Verify and reject' : 'Verify reconciliation'}</Button></div>
        </div> : <div className="space-y-3">
            {requests.isLoading ? <p>Loading requests…</p> : requests.data?.length ? requests.data.map(item => <article key={item.id} className={`rounded-lg border p-4 ${item.status === 'executing' ? 'border-amber-300 bg-amber-50' : ''}`}>
                <div className="flex flex-wrap justify-between gap-2"><div><h3 className="font-semibold">{requestSummary(item)}{item.status === 'executing' && <span className="ml-2 rounded bg-amber-200 px-2 py-0.5 text-xs text-amber-900">In doubt</span>}</h3><p className="text-sm text-gray-600">Requested by {item.requested_by} · {new Date(item.created_at).toLocaleString()}</p>{item.status === 'executing' && <p className="mt-2 max-w-xl text-sm text-amber-900">Execution has been running for over {15} minutes. Check the affected system before recording whether it completed. It will not be retried automatically.</p>}</div>{canDecide && (item.status === 'executing' ? <div className="flex flex-col gap-2"><Button size="sm" onClick={() => select(item, 'reconcile_executed')}>I verified it completed</Button><Button size="sm" variant="danger" onClick={() => select(item, 'reconcile_failed')}>I verified it did not complete</Button></div> : <div className="flex gap-2"><Button size="sm" onClick={() => select(item, 'approve')}>Approve</Button><Button size="sm" variant="danger" onClick={() => select(item, 'reject')}>Reject</Button></div>)}</div>
            </article>) : <p className="py-8 text-center text-sm text-gray-500">No pending requests.</p>}
            {!canDecide && !!requests.data?.length && <p className="text-sm text-gray-500">Only the chairman can approve or reject these requests.</p>}
        </div>}
    </Modal>;
}
