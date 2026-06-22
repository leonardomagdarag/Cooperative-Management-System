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

    const togglePermission = (permission: string) => {
        const updated = currentPermissions.includes(permission)
            ? currentPermissions.filter(p => p !== permission)
            : [...currentPermissions, permission];
        updateMutation.mutate({ role: selectedRole, permissions: updated });
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Roles & Permissions</h1>
                <button onClick={() => setShowCreateModal(true)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><i className="fas fa-plus mr-1"></i> New Role</button>
            </div>

            <div className="flex gap-6">
                {/* Role List */}
                <div className="w-64 shrink-0 space-y-2">
                    {roles.map(role => (
                        <button key={role} onClick={() => setSelectedRole(role)} className={`w-full rounded-lg px-3 py-2.5 text-left ${selectedRole === role ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700'}`}>
                            <p className="text-sm font-medium">{roleDisplayNames[role] || role}</p>
                            <p className="text-xs text-slate-400">{roleUserCounts[role] || 0} users</p>
                        </button>
                    ))}
                </div>

                {/* Permissions */}
                <div className="flex-1 space-y-4">
                    {Object.entries(groups).map(([group, permissions]) => (
                        <div key={group} className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                            <h3 className="mb-3 text-sm font-semibold text-slate-900 dark:text-white capitalize">{group.replace(/_/g, ' ')}</h3>
                            <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                                {permissions.map(permission => (
                                    <label key={permission} className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                        <input type="checkbox" checked={currentPermissions.includes(permission)} onChange={() => togglePermission(permission)} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                        <span className="truncate">{permission.split('.').pop()}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}

                    {!isBaseRole && selectedRole && (
                        <button onClick={() => { if (confirm(`Delete role "${selectedRole}"?`)) deleteMutation.mutate(selectedRole); }} className="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            <i className="fas fa-trash mr-1"></i> Delete Role
                        </button>
                    )}
                </div>
            </div>

            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowCreateModal(false)}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">Create Role</h2>
                        <div className="space-y-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Role Name</label>
                                <input type="text" value={newRoleName} onChange={e => setNewRoleName(e.target.value)} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="e.g., auditor" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Clone From (optional)</label>
                                <select value={newRoleTemplate} onChange={e => setNewRoleTemplate(e.target.value)} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700">
                                    <option value="">None (base access only)</option>
                                    {roles.map(r => <option key={r} value={r}>{roleDisplayNames[r] || r}</option>)}
                                </select>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <button onClick={() => setShowCreateModal(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
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
