import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function MemberProfileModal({ memberId, onClose }: { memberId: number; onClose: () => void }) {
    const { data, isLoading } = useQuery({
        queryKey: ['member', memberId],
        queryFn: () => api.get(`/api/members/${memberId}`),
    });

    const m = data?.data;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/25 p-4" onClick={onClose}>
            <div className="w-full max-w-lg rounded-2xl shadow-2xl bg-white overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Member Profile</h2>
                        <button onClick={onClose} className="text-white/70 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                </div>

                {isLoading ? (
                    <div className="p-6 space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-10 animate-pulse rounded bg-gray-100" />)}</div>
                ) : m ? (
                    <div className="p-6 space-y-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                                <span className="text-xl font-bold text-emerald-600">{m.personal_data?.first_name?.charAt(0)}{m.personal_data?.last_name?.charAt(0)}</span>
                            </div>
                            <div>
                                <p className="font-semibold text-gray-900">{m.personal_data?.first_name} {m.personal_data?.last_name}</p>
                                <p className="text-sm text-gray-500">{m.user_number} &middot; {m.email}</p>
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
                                <h3 className="mb-2 text-sm font-semibold text-gray-700">Accounts</h3>
                                <div className="space-y-2">
                                    {m.accounts.map((a: any) => (
                                        <div key={a.id} className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                            <span className="text-sm text-gray-600">{a.account_type}</span>
                                            <span className="font-medium text-gray-900">₱{Number(a.balance).toLocaleString()}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {m.beneficiaries?.length > 0 && (
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-gray-700">Beneficiaries</h3>
                                <div className="space-y-2">
                                    {m.beneficiaries.map((b: any) => (
                                        <div key={b.id} className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                            <span className="text-sm text-gray-600">{b.first_name} {b.last_name} ({b.relationship})</span>
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
            <p className="text-xs text-gray-500">{label}</p>
            <p className="text-sm font-medium text-gray-900">{value || '\u2014'}</p>
        </div>
    );
}
