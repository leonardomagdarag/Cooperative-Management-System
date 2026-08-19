import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

type State = 'inherit' | 'allow' | 'disallow';

export default function OfficerPermissionModal({ officer, onClose }: { officer: any; onClose: () => void }) {
    const { addToast } = useToast();
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [showOnlyOverrides, setShowOnlyOverrides] = useState(false);
    const [states, setStates] = useState<Record<string, State>>({});
    const [saved, setSaved] = useState<Record<string, State>>({});
    const [confirmingClearAll, setConfirmingClearAll] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['officer-permissions', officer.id],
        queryFn: () => api.get(`/api/officers/${officer.id}/permissions`),
        enabled: !!officer?.id,
    });

    const info = data?.data;
    const overrides: Record<string, string> = info?.overrides ?? {};
    const rolePermissions: string[] = info?.role_permissions ?? [];
    const groups: Record<string, string[]> = info?.groups ?? {};

    useEffect(() => {
        if (info) {
            const initial: Record<string, State> = {};
            Object.keys(groups).forEach(group => {
                groups[group].forEach(permission => {
                    initial[permission] = overrides[permission] ?? 'inherit';
                });
            });
            setStates(initial);
            setSaved(initial);
        }
    }, [info]);

    const isDirty = useMemo(
        () => JSON.stringify(states) !== JSON.stringify(saved),
        [states, saved]
    );

    const saveMutation = useMutation({
        mutationFn: (payload: Record<string, string>) =>
            api.put(`/api/officers/${officer.id}/permissions`, { overrides: payload }),
        onSuccess: (res) => {
            addToast('Permission overrides saved', 'success');
            setSaved(states);
            queryClient.invalidateQueries({ queryKey: ['officer-permissions', officer.id] });
            queryClient.invalidateQueries({ queryKey: ['auth'] });
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save', 'error'),
    });

    const setPermissionState = (permission: string, state: State) => {
        setStates(prev => ({ ...prev, [permission]: state }));
    };

    const doClearAll = () => {
        const cleared: Record<string, State> = {};
        Object.keys(groups).forEach(group => {
            groups[group].forEach(permission => {
                cleared[permission] = 'inherit';
            });
        });
        setStates(cleared);
        setConfirmingClearAll(false);
    };

    const dirtyOverrides = useMemo(() => {
        const payload: Record<string, string> = {};
        Object.entries(states).forEach(([permission, state]) => {
            if (state !== 'inherit') {
                payload[permission] = state;
            }
        });
        return payload;
    }, [states]);

    const filteredGroups = useMemo(() => {
        const result: Record<string, string[]> = {};
        Object.entries(groups).forEach(([groupName, permissions]) => {
            const filtered = permissions.filter(p => {
                const matchesSearch = search === '' || p.toLowerCase().includes(search.toLowerCase()) || formatLabel(p).toLowerCase().includes(search.toLowerCase());
                if (!matchesSearch) return false;
                if (showOnlyOverrides && states[p] === 'inherit') return false;
                return true;
            });
            if (filtered.length > 0) {
                result[groupName] = filtered;
            }
        });
        return result;
    }, [groups, search, showOnlyOverrides, states]);

    const counts = useMemo(() => {
        const entries = Object.entries(states);
        return {
            total: entries.filter(([, v]) => v !== 'inherit').length,
            allow: entries.filter(([, v]) => v === 'allow').length,
            deny: entries.filter(([, v]) => v === 'disallow').length,
        };
    }, [states]);

    const requestClose = () => {
        if (isDirty && !confirm('You have unsaved permission overrides. Discard changes?')) return;
        onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={requestClose}>
            <div className="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-indigo-600 to-cyan-600 px-6 py-5 text-white">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Permission Overrides</h2>
                        <button onClick={requestClose} className="text-white/70 hover:text-white"><i className="fas fa-times"></i></button>
                    </div>
                    <p className="mt-1 text-sm text-white/80">
                        {officer.name || 'Officer'} inherits permissions from the{' '}
                        <span className="font-semibold text-white">{info?.role_label ?? (officer.primary_role || 'Officer')}</span> role.
                    </p>
                </div>

                {isDirty && (
                    <div className="flex items-center gap-3 border-b border-amber-200 bg-amber-50 px-6 py-3">
                        <i className="fas fa-exclamation-triangle text-amber-500"></i>
                        <span className="text-sm font-semibold text-amber-800">Unsaved changes</span>
                        <span className="text-xs text-amber-700">use <strong>Save Overrides</strong> at the bottom to apply.</span>
                    </div>
                )}

                <div className="grid shrink-0 grid-cols-4 gap-2 border-b border-gray-100 bg-gray-50/50 px-6 py-4">
                    <StatCard label="Role" value={rolePermissions.length} className="border-gray-200 bg-white text-gray-900" />
                    <StatCard label="Total" value={counts.total} className="border-blue-200 bg-blue-50 text-blue-800" />
                    <StatCard label="Allows" value={counts.allow} className="border-emerald-200 bg-emerald-50 text-emerald-700" />
                    <StatCard label="Denies" value={counts.deny} className="border-red-200 bg-red-50 text-red-700" />
                </div>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-gray-100 bg-gray-50/50 px-6 py-2.5 text-xs text-gray-500">
                    <span className="font-semibold text-gray-600">Effective access:</span>
                    <LegendDot className="bg-blue-500" label="Active (from role)" />
                    <LegendDot className="bg-emerald-500" label="Granted (override)" />
                    <LegendDot className="bg-red-500" label="Denied (override)" />
                    <LegendDot className="bg-gray-300" label="Inactive" />
                </div>

                <div className="flex flex-col gap-3 border-b border-gray-200 bg-white px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative flex-1">
                        <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Search permissions…"
                            className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm text-gray-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200"
                        />
                    </div>
                    <label className="inline-flex cursor-pointer select-none items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" checked={showOnlyOverrides} onChange={e => setShowOnlyOverrides(e.target.checked)} className="rounded border-gray-300 text-cyan-600 focus:ring-cyan-400" />
                        Show only overrides
                    </label>
                    {confirmingClearAll ? (
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold text-red-700">Clear all overrides?</span>
                            <button onClick={doClearAll} className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Yes, clear all</button>
                            <button onClick={() => setConfirmingClearAll(false)} className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        </div>
                    ) : (
                        <button
                            onClick={() => setConfirmingClearAll(true)}
                            disabled={counts.total === 0}
                            className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Clear All
                        </button>
                    )}
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-4">
                    {isLoading ? (
                        <div className="p-8 text-center text-sm text-gray-500">Loading permissions...</div>
                    ) : Object.keys(filteredGroups).length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center">
                            <i className="fas fa-search mb-3 text-3xl text-gray-300"></i>
                            <p className="text-sm font-medium text-gray-500">No permissions found</p>
                            <p className="mt-1 text-xs text-gray-400">
                                {showOnlyOverrides ? 'No overrides are set yet. Uncheck "Show only overrides" to see all permissions.' : 'Try a different search term.'}
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {Object.entries(filteredGroups).map(([groupName, permissions]) => (
                                <div key={groupName} className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                    <div className="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-5 py-3">
                                        <h5 className="text-xs font-bold uppercase tracking-wider text-gray-500">{groupName}</h5>
                                        <span className="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">{permissions.length}</span>
                                    </div>
                                    <div className="divide-y divide-gray-100">
                                        {permissions.map(permission => {
                                            const state = states[permission] ?? 'inherit';
                                            const inherits = rolePermissions.includes(permission);
                                            const changed = state !== (saved[permission] ?? 'inherit');
                                            return (
                                                <div key={permission} className={`flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:justify-between ${changed ? 'bg-amber-50/50' : ''}`}>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="text-sm font-semibold text-gray-900">{formatLabel(permission)}</p>
                                                            {changed && <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-700">Modified</span>}
                                                        </div>
                                                        <p className="mt-0.5 font-mono text-xs text-gray-400">{permission}</p>
                                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                                            <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${inherits ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-400'}`}>
                                                                {inherits ? <i className="fas fa-check text-[10px]"></i> : <i className="fas fa-minus text-[10px]"></i>}
                                                                {inherits ? 'In role' : 'Not in role'}
                                                            </span>
                                                            {state === 'disallow' ? (
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700"><i className="fas fa-ban text-[10px]"></i> Denied</span>
                                                            ) : state === 'allow' ? (
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"><i className="fas fa-check text-[10px]"></i> Granted{!inherits ? ' (override)' : ''}</span>
                                                            ) : inherits ? (
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700"><i className="fas fa-check text-[10px]"></i> Active</span>
                                                            ) : (
                                                                <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-400"><i className="fas fa-minus text-[10px]"></i> Inactive</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="inline-flex shrink-0 gap-0.5 rounded-lg border border-gray-200 bg-gray-100 p-1">
                                                        <TriButton active={state === 'inherit'} activeClass="bg-white text-gray-800 shadow ring-1 ring-gray-200" inactiveClass="text-gray-500 hover:bg-white/60 hover:text-gray-700" onClick={() => setPermissionState(permission, 'inherit')} title="Inherit from role — no override"><i className="fas fa-history text-xs"></i> Role Default</TriButton>
                                                        <TriButton active={state === 'allow'} activeClass="bg-emerald-600 text-white shadow" inactiveClass="text-emerald-600 hover:bg-emerald-50" onClick={() => setPermissionState(permission, 'allow')} title="Always allow"><i className="fas fa-check text-xs"></i> Allow</TriButton>
                                                        <TriButton active={state === 'disallow'} activeClass="bg-red-600 text-white shadow" inactiveClass="text-red-600 hover:bg-red-50" onClick={() => setPermissionState(permission, 'disallow')} title="Always deny"><i className="fas fa-times text-xs"></i> Deny</TriButton>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button onClick={requestClose} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button
                        onClick={() => saveMutation.mutate(dirtyOverrides)}
                        disabled={!isDirty || saveMutation.isPending}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {saveMutation.isPending ? <><i className="fas fa-spinner fa-spin mr-2"></i>Saving...</> : 'Save Overrides'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function StatCard({ label, value, className }: { label: string; value: number; className: string }) {
    return (
        <div className={`rounded-lg border px-3 py-2.5 text-center ${className}`}>
            <p className="text-xs uppercase tracking-wide text-gray-400">{label}</p>
            <p className="text-xl font-bold">{value}</p>
        </div>
    );
}

function LegendDot({ className, label }: { className: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={`h-2.5 w-2.5 rounded-full ${className}`}></span>{label}
        </span>
    );
}

function TriButton({ active, activeClass, inactiveClass, onClick, title, children }: { active: boolean; activeClass: string; inactiveClass: string; onClick: () => void; title: string; children: any }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={title}
            className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all focus:outline-none ${active ? activeClass : inactiveClass}`}
        >
            {children}
        </button>
    );
}

function formatLabel(permission: string): string {
    const parts = permission.split('.');
    if (parts.length < 2) {
        return permission.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }
    const resource = parts[0];
    const action = parts.slice(1).join(' ');
    return `${action.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())} ${resource.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}`;
}