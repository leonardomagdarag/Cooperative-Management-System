import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function RolePermissionsPage() {
    const [selectedRole, setSelectedRole] = useState('');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [newRoleName, setNewRoleName] = useState('');
    const [newRoleTemplate, setNewRoleTemplate] = useState('');
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['roles'],
        queryFn: () => api.get('/api/roles'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ role, permissions }: { role: string; permissions: string[] }) => api.put(`/api/roles/${role}/permissions`, { permissions }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['roles'] }); addToast('Permissions updated', 'success'); },
        onError: () => addToast('Failed to update permissions', 'error'),
    });

    const createMutation = useMutation({
        mutationFn: () => api.post('/api/roles', { name: newRoleName, template: newRoleTemplate || undefined }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['roles'] }); setShowCreateModal(false); setNewRoleName(''); addToast('Role created', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (role: string) => api.delete(`/api/roles/${role}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['roles'] }); addToast('Role deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const roles: string[] = data?.data?.roles || [];
    const rolePermissions: Record<string, string[]> = data?.data?.role_permissions || {};
    const roleUserCounts: Record<string, number> = data?.data?.role_user_counts || {};
    const roleDisplayNames: Record<string, string> = data?.data?.role_display_names || {};
    const groups: Record<string, string[]> = data?.data?.groups || {};
    const baseRoles: string[] = data?.data?.base_roles || [];

    if (!selectedRole && roles.length > 0 && !selectedRole) {
        setSelectedRole(roles[0]);
    }

    const currentPermissions = rolePermissions[selectedRole] || [];
    const isBaseRole = baseRoles.includes(selectedRole);
    const totalPermissionCount = Object.values(groups).reduce((sum, perms) => sum + perms.length, 0);
    const currentCount = currentPermissions.length;

    const togglePermission = (permission: string) => {
        const updated = currentPermissions.includes(permission)
            ? currentPermissions.filter(p => p !== permission)
            : [...currentPermissions, permission];
        updateMutation.mutate({ role: selectedRole, permissions: updated });
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900">Roles & Permissions</h1>
                <button onClick={() => setShowCreateModal(true)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><i className="fas fa-plus mr-1"></i> New Role</button>
            </div>

            <div className="flex gap-6">
                {/* Role List */}
                <div className="w-64 shrink-0 space-y-2">
                    {roles.map(role => {
                        const roleCount = rolePermissions[role]?.length || 0;
                        const isBase = baseRoles.includes(role);
                        return (
                            <button
                                key={role}
                                onClick={() => setSelectedRole(role)}
                                className={`w-full border rounded-xl bg-white px-3 py-3 text-left transition ${
                                    selectedRole === role
                                        ? 'border-cyan-200 bg-cyan-50 ring-1 ring-cyan-200'
                                        : 'border-gray-200 text-gray-500 hover:bg-gray-50'
                                }`}
                            >
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium text-gray-900">{roleDisplayNames[role] || role}</p>
                                    {isBase && <span className="rounded-full bg-cyan-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-cyan-700">Base</span>}
                                    {!isBase && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700">Custom</span>}
                                </div>
                                <p className="mt-1 text-xs text-gray-400">{roleCount} / {totalPermissionCount} permissions · {roleUserCounts[role] || 0} users</p>
                            </button>
                        );
                    })}
                </div>

                {/* Detail Panel */}
                <div className="flex-1 space-y-4">
                    {selectedRole && (
                        <div className="rounded-xl border bg-white shadow-sm">
                            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900">{roleDisplayNames[selectedRole] || selectedRole}</h2>
                                    <p className="text-sm text-gray-500">{currentCount} of {totalPermissionCount} permissions granted</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {isBaseRole ? (
                                        <span className="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-medium text-cyan-700">
                                            <i className="fas fa-lock mr-1"></i> Base role &mdash; cannot modify
                                        </span>
                                    ) : (
                                        <button onClick={() => { if (confirm(`Delete role "${selectedRole}"?`)) deleteMutation.mutate(selectedRole); }} className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-100">
                                            <i className="fas fa-trash mr-1"></i> Delete Role
                                        </button>
                                    )}
                                </div>
                            </div>
                            <div className="p-6 space-y-4">
                                {Object.entries(groups).map(([group, permissions]) => (
                                    <div key={group} className="rounded-xl border border-gray-200 bg-white p-4">
                                        <h3 className="mb-3 text-sm font-semibold text-gray-900 capitalize">{group.replace(/_/g, ' ')}</h3>
                                        <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                            {permissions.map(permission => (
                                                <label key={permission} className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition cursor-pointer ${currentPermissions.includes(permission) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-100 bg-white text-gray-600 hover:bg-gray-50'}`}>
                                                    <input
                                                        type="checkbox"
                                                        checked={currentPermissions.includes(permission)}
                                                        onChange={() => togglePermission(permission)}
                                                        disabled={isBaseRole}
                                                        className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 disabled:opacity-50"
                                                    />
                                                    <span className="truncate">{permission.split('.').pop()}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowCreateModal(false)}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-gray-900">Create Role</h2>
                        <div className="space-y-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Role Name</label>
                                <input type="text" value={newRoleName} onChange={e => setNewRoleName(e.target.value)} className="w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 border" placeholder="e.g., auditor" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Clone From (optional)</label>
                                <select value={newRoleTemplate} onChange={e => setNewRoleTemplate(e.target.value)} className="w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 border">
                                    <option value="">None (base access only)</option>
                                    {roles.map(r => <option key={r} value={r}>{roleDisplayNames[r] || r}</option>)}
                                </select>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <button onClick={() => setShowCreateModal(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button onClick={() => createMutation.mutate()} disabled={!newRoleName || createMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                {createMutation.isPending ? 'Creating...' : 'Create'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
