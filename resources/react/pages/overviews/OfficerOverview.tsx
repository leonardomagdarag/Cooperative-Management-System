import { useAuth } from '../../contexts/AuthContext';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function OfficerOverview() {
    const { user } = useAuth();
    const { data } = useQuery({ queryKey: ['overview-officer'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};

    const permissions = user?.permissions || [];

    return (
        <div className="space-y-6">
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="bg-linear-to-r from-slate-900 via-cyan-800 to-emerald-700 px-6 py-7 text-white">
                    <p className="text-sm font-medium text-cyan-100">{d.greeting || 'Good day'}</p>
                    <h2 className="mt-1 text-2xl font-semibold">Custom officer dashboard</h2>
                    <p className="mt-2 max-w-2xl text-sm text-cyan-50/90">
                        This role is being served by the shared permission-driven dashboard path. Use the navigation to access the sections granted to this account.
                    </p>
                </div>

                <div className="grid gap-4 px-6 py-5 md:grid-cols-3">
                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Active role</p>
                        <p className="mt-2 text-lg font-semibold text-slate-900">
                            {user?.primary_role ? user.primary_role.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'Officer'}
                        </p>
                    </div>

                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                        <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Granted permissions</p>
                        <p className="mt-2 text-lg font-semibold text-emerald-900">{permissions.length}</p>
                    </div>

                    <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-4">
                        <p className="text-xs font-semibold uppercase tracking-wide text-cyan-700">Account status</p>
                        <p className="mt-2 text-lg font-semibold text-cyan-900 capitalize">Active</p>
                    </div>
                </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 px-6 py-4">
                    <h3 className="text-lg font-semibold text-slate-900">Permission summary</h3>
                    <p className="mt-1 text-sm text-slate-500">Inherited and direct permissions currently available to this role.</p>
                </div>

                <div className="px-6 py-5">
                    {permissions.length === 0 ? (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                            No permissions are currently assigned. An administrator can grant access from Role Permission Management.
                        </div>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {permissions.map((p: string) => (
                                <span key={p} className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                                    {p.split('.').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
