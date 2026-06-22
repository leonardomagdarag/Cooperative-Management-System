import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function MemberProfileModal({ memberId, onClose }: { memberId: number; onClose: () => void }) {
    const { data, isLoading } = useQuery({
        queryKey: ['member', memberId],
        queryFn: () => api.get(`/api/members/${memberId}`),
    });

    const m = data?.data;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-white">Member Profile</h2>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600"><i className="fas fa-times"></i></button>
                </div>

                {isLoading ? (
                    <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-10 animate-pulse rounded bg-slate-100 dark:bg-slate-700" />)}</div>
                ) : m ? (
                    <div className="space-y-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                <span className="text-xl font-bold text-emerald-600">{m.personal_data?.first_name?.charAt(0)}{m.personal_data?.last_name?.charAt(0)}</span>
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900 dark:text-white">{m.personal_data?.first_name} {m.personal_data?.last_name}</p>
                                <p className="text-sm text-slate-500">{m.user_number} · {m.email}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <InfoRow label="Gender" value={m.personal_data?.gender} />
                            <InfoRow label="Civil Status" value={m.personal_data?.civil_status} />
                            <InfoRow label="Birth Date" value={m.personal_data?.birth_date} />
                            <InfoRow label="Phone" value={m.contact?.phone_no} />
                            <InfoRow label="Status" value={m.account_status} />
                            <InfoRow label="Role" value={m.primary_role} />
                        </div>

                        {m.accounts?.length > 0 && (
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Accounts</h3>
                                <div className="space-y-2">
                                    {m.accounts.map((a: any) => (
                                        <div key={a.id} className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
                                            <span className="text-sm text-slate-600">{a.account_type}</span>
                                            <span className="font-medium text-slate-900 dark:text-white">₱{Number(a.balance).toLocaleString()}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {m.beneficiaries?.length > 0 && (
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Beneficiaries</h3>
                                <div className="space-y-2">
                                    {m.beneficiaries.map((b: any) => (
                                        <div key={b.id} className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
                                            <span className="text-sm text-slate-600">{b.first_name} {b.last_name} ({b.relationship})</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <p className="text-xs text-slate-500">{label}</p>
            <p className="text-sm font-medium text-slate-900 dark:text-white">{value || '—'}</p>
        </div>
    );
}
