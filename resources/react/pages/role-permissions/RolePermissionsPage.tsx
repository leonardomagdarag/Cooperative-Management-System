import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, roleSchema, type FieldErrors } from '../../lib/validation';
import SettingsTabHeader from '../settings/SettingsTabHeader';

export default function RolePermissionsPage() {
    const [selectedRole, setSelectedRole] = useState('');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [newRoleName, setNewRoleName] = useState('');
    const [newRoleTemplate, setNewRoleTemplate] = useState('');
    const [dirtyPermissions, setDirtyPermissions] = useState<Record<string, string[]>>({});
    const [isRenaming, setIsRenaming] = useState(false);
    const [renameValue, setRenameValue] = useState('');
    const [roleErrors, setRoleErrors] = useState<FieldErrors>({});
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['roles'],
        queryFn: () => api.get('/api/roles'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ role, permissions }: { role: string; permissions: string[] }) => api.put(`/api/roles/${role}/permissions`, { permissions }),
        onSuccess: (_data, variables) => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            setDirtyPermissions(prev => {
                const next = { ...prev };
                delete next[variables.role];
                return next;
            });
            addToast('Permissions updated', 'success');
        },
        onError: () => addToast('Failed to update permissions', 'error'),
    });

    const createMutation = useMutation({
        mutationFn: () => api.post('/api/roles', { name: newRoleName, template: newRoleTemplate || undefined }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['roles'] }); setShowCreateModal(false); setNewRoleName(''); setNewRoleTemplate(''); reset(); addToast('Role created', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const handleCreateRole = () => {
        const errs = validate(roleSchema, { name: newRoleName });
        if (Object.keys(errs).length > 0) {
            setRoleErrors(errs);
            addToast(errs.name, 'error');
            return;
        }
        setRoleErrors({});
        createMutation.mutate();
    };

    const deleteMutation = useMutation({
        mutationFn: (role: string) => api.delete(`/api/roles/${role}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['roles'] }); addToast('Role deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const renameMutation = useMutation({
        mutationFn: ({ role, name }: { role: string; name: string }) => api.put(`/api/roles/${role}/rename`, { name }),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            setSelectedRole(res.data.role);
            setIsRenaming(false);
            addToast('Role renamed', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to rename role', 'error'),
    });

    const restorePresetMutation = useMutation({
        mutationFn: (role: string) => api.get(`/api/roles/${role}/restore-preset`),
        onSuccess: (res, role) => {
            const presetPermissions = res.data.permissions as string[];
            updateMutation.mutate({ role, permissions: presetPermissions });
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to restore preset', 'error'),
    });

    const roles: string[] = data?.data?.roles || [];
    const rolePermissions: Record<string, string[]> = data?.data?.role_permissions || {};
    const roleUserCounts: Record<string, number> = data?.data?.role_user_counts || {};
    const roleDisplayNames: Record<string, string> = data?.data?.role_display_names || {};
    const groups: Record<string, string[]> = data?.data?.groups || {};
    const baseRoles: string[] = data?.data?.base_roles || [];
    const rolePresets: Record<string, string[]> = data?.data?.role_presets || {};

    if (!selectedRole && roles.length > 0 && !selectedRole) {
        setSelectedRole(roles[0]);
    }

    const getEffectivePermissions = (role: string): string[] => {
        if (dirtyPermissions[role] !== undefined) return dirtyPermissions[role];
        return rolePermissions[role] || [];
    };

    const currentPermissions = getEffectivePermissions(selectedRole);
    const isDirty = dirtyPermissions[selectedRole] !== undefined;
    const isBaseRole = baseRoles.includes(selectedRole);
    const totalPermissionCount = Object.values(groups).reduce((sum, perms) => sum + perms.length, 0);
    const currentCount = currentPermissions.length;

    const togglePermission = (permission: string) => {
        setDirtyPermissions(prev => {
            const saved = rolePermissions[selectedRole] || [];
            const current = prev[selectedRole] !== undefined ? prev[selectedRole] : saved;
            const updated = current.includes(permission)
                ? current.filter(p => p !== permission)
                : [...current, permission];
            const arraysEqual = saved.length === updated.length && saved.every(v => updated.includes(v));
            if (arraysEqual) {
                const next = { ...prev };
                delete next[selectedRole];
                return next;
            }
            return { ...prev, [selectedRole]: updated };
        });
    };

    const savePermissions = () => {
        if (dirtyPermissions[selectedRole]) {
            updateMutation.mutate({ role: selectedRole, permissions: dirtyPermissions[selectedRole] });
        }
    };

    const handleStartRename = () => {
        setRenameValue(roleDisplayNames[selectedRole] || selectedRole);
        setIsRenaming(true);
    };

    const handleRename = () => {
        if (!renameValue.trim() || renameValue.trim() === selectedRole) {
            addToast('Enter a new role name', 'error');
            return;
        }
        renameMutation.mutate({ role: selectedRole, name: renameValue });
    };

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                title="Roles & Permissions"
                description="Manage role access across the cooperative system."
                icon="fas fa-user-shield"
                action={<button onClick={() => { reset(); setNewRoleName(''); setNewRoleTemplate(''); setRoleErrors({}); setShowCreateModal(true); }} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><i className="fas fa-plus mr-1"></i> New Role</button>}
            />

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
                            {isDirty && (
                                <div className="flex items-center justify-between bg-amber-50 border-b border-amber-200 px-6 py-2.5">
                                    <span className="text-sm font-medium text-amber-800">
                                        <i className="fas fa-exclamation-triangle mr-1"></i>
                                        You have unsaved changes &mdash; Save to apply
                                    </span>
                                    <button
                                        onClick={savePermissions}
                                        disabled={updateMutation.isPending}
                                        className="rounded-lg bg-amber-600 px-3 py-1 text-xs font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                                    >
                                        {updateMutation.isPending ? 'Saving...' : 'Save'}
                                    </button>
                                </div>
                            )}
                            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div className="flex items-center gap-2">
                                    {isRenaming ? (
                                        <div className="flex items-center gap-1">
                                            <input
                                                type="text"
                                                value={renameValue}
                                                onChange={e => setRenameValue(e.target.value)}
                                                onKeyDown={e => { if (e.key === 'Enter') handleRename(); if (e.key === 'Escape') setIsRenaming(false); }}
                                                className="w-48 rounded-lg border border-gray-300 px-2 py-1 text-sm font-semibold"
                                                autoFocus
                                            />
                                            <button onClick={handleRename} className="rounded-lg p-1 text-emerald-600 hover:bg-emerald-50"><i className="fas fa-check"></i></button>
                                            <button onClick={() => setIsRenaming(false)} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100"><i className="fas fa-times"></i></button>
                                        </div>
                                    ) : (
                                        <>
                                            <h2 className="text-lg font-semibold text-gray-900">{roleDisplayNames[selectedRole] || selectedRole}</h2>
                                            <button onClick={handleStartRename} className="rounded-lg p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100"><i className="fas fa-pen text-xs"></i></button>
                                        </>
                                    )}
                                    <p className="text-sm text-gray-500">{currentCount} of {totalPermissionCount} permissions granted</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {rolePresets[selectedRole] && (
                                        <button
                                            onClick={() => { if (confirm('Restore default permissions for this role?')) restorePresetMutation.mutate(selectedRole); }}
                                            disabled={restorePresetMutation.isPending}
                                            className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50"
                                        >
                                            <i className="fas fa-undo mr-1"></i> Restore Preset
                                        </button>
                                    )}
                                    {isBaseRole ? (
                                        <span className="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-medium text-cyan-700">
                                            <i className="fas fa-lock mr-1"></i> Base role &mdash; edit permissions, rename/delete locked
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
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => requestClose(() => setShowCreateModal(false))}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-gray-900">Create Role</h2>
                        <div className="space-y-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Role Name</label>
                                <input type="text" value={newRoleName} onChange={e => { markDirty(); setNewRoleName(e.target.value); if (roleErrors.name) setRoleErrors({}); }} className={`w-full rounded-xl border bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 ${roleErrors.name ? 'border-red-400' : 'border-gray-300'}`} placeholder="e.g., auditor" />
                                {roleErrors.name && <p className="mt-1 text-xs text-red-600">{roleErrors.name}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Clone From (optional)</label>
                                <select value={newRoleTemplate} onChange={e => { markDirty(); setNewRoleTemplate(e.target.value); }} className="w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 border">
                                    <option value="">None (base access only)</option>
                                    {roles.map(r => <option key={r} value={r}>{roleDisplayNames[r] || r}</option>)}
                                </select>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <button onClick={() => requestClose(() => setShowCreateModal(false))} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button onClick={handleCreateRole} disabled={createMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                {createMutation.isPending ? 'Creating...' : 'Create'}
                            </button>
                        </div>
                    </div>
                    {renderDialog()}
                </div>
            )}
        </div>
    );
}
