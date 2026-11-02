import HelpTip from '../../components/ui/HelpTip';
import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import LoadingIndicator from '../../components/ui/LoadingIndicator';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, roleSchema, type FieldErrors } from '../../lib/validation';
import { formatPermissionLabel } from '../../lib/permissions';
import SettingsTabHeader from '../settings/SettingsTabHeader';

interface RoleData {
    roles: string[];
    role_permissions: Record<string, string[]>;
    role_user_counts: Record<string, number>;
    role_display_names: Record<string, string>;
    role_descriptions: Record<string, string>;
    role_presets: Record<string, string[]>;
    base_roles: string[];
    groups: Record<string, string[]>;
}

export default function RolePermissionsPage() {
    const [selectedRole, setSelectedRole] = useState('');
    const [pendingRole, setPendingRole] = useState('');
    const [showDiscardModal, setShowDiscardModal] = useState(false);
    const [dirtyPermissions, setDirtyPermissions] = useState<Record<string, string[]>>({});
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [newRoleName, setNewRoleName] = useState('');
    const [newRoleTemplate, setNewRoleTemplate] = useState('');
    const [roleErrors, setRoleErrors] = useState<FieldErrors>({});
    const [renameValue, setRenameValue] = useState('');
    const [showSaveModal, setShowSaveModal] = useState(false);
    const [saveSummary, setSaveSummary] = useState<{ added: string[]; removed: string[] }>({ added: [], removed: [] });
    const [showDeleteRoleModal, setShowDeleteRoleModal] = useState(false);
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['roles'],
        queryFn: () => api.get('/api/roles'),
    });

    const d: RoleData | undefined = data?.data;

    const editablePermissions = normalize(Object.values(d?.groups || {}).flat());
    const totalEditableCount = editablePermissions.length;
    const roles: string[] = d?.roles || [];
    const rolePermissions: Record<string, string[]> = d?.role_permissions || {};
    const roleUserCounts: Record<string, number> = d?.role_user_counts || {};
    const roleDisplayNames: Record<string, string> = d?.role_display_names || {};
    const roleDescriptions: Record<string, string> = d?.role_descriptions || {};
    const rolePresets: Record<string, string[]> = d?.role_presets || {};
    const baseRoles: string[] = d?.base_roles || [];

    useEffect(() => {
        if (!selectedRole && roles.length > 0) {
            setSelectedRole(roles[0]);
        }
    }, [roles, selectedRole]);

    useEffect(() => {
        setRenameValue(roleDisplayNames[selectedRole] || selectedRole);
    }, [selectedRole, roleDisplayNames]);

    useEffect(() => {
        if (!selectedRole) return;
        if (!roles.includes(selectedRole)) {
            setSelectedRole(roles[0] || '');
        }
    }, [roles, selectedRole]);

    const roleLabel = (role: string) => roleDisplayNames[role] || role;
    const roleDescription = (role: string) => roleDescriptions[role] || 'Custom database role managed through shared permission groups.';
    const isBaseRole = (role: string) => baseRoles.includes(role);
    const assignedUserCount = (role: string) => roleUserCounts[role] || 0;

    const initialFor = (role: string) => normalize((rolePermissions[role] || []).filter(p => editablePermissions.includes(p)));
    const currentFor = (role: string) => (dirtyPermissions[role] !== undefined ? normalize(dirtyPermissions[role]) : initialFor(role));
    const hasChanges = (role: string) => dirtyPermissions[role] !== undefined;
    const hasPreset = (role: string) => Object.prototype.hasOwnProperty.call(rolePresets, role);
    const presetFor = (role: string) => normalize((rolePresets[role] || []).filter(p => editablePermissions.includes(p)));
    const canManageRoleIdentity = (role: string) => !isBaseRole(role) && assignedUserCount(role) === 0 && !hasChanges(role);

    const currentPermissions = currentFor(selectedRole);

    const updateMutation = useMutation({
        mutationFn: ({ role, permissions }: { role: string; permissions: string[] }) => api.put(`/api/roles/${role}/permissions`, { permissions }),
        onSuccess: (response, variables) => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            setDirtyPermissions(prev => {
                const next = { ...prev };
                delete next[variables.role];
                return next;
            });
            setShowSaveModal(false);
            const isPending = response.data?.status === 'pending' || response?.status === 202;
            if (isPending) {
                const message = response.data?.message || `Permission changes for ${roleLabel(variables.role)} were submitted and are awaiting approval. They will take effect after approval.`;
                addToast(message, 'info', 8000);
            } else {
                addToast(response.data?.message || `Role permissions updated for ${roleLabel(variables.role)}.`, 'success');
            }
        },
        onError: () => {
            setShowSaveModal(true);
            addToast('Unable to save role permissions. Please try again.', 'error');
        },
    });

    const createMutation = useMutation({
        mutationFn: () => api.post('/api/roles', { name: newRoleName, template: newRoleTemplate || undefined }),
        onSuccess: (response) => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            setShowCreateModal(false);
            setNewRoleName('');
            setNewRoleTemplate('');
            setRoleErrors({});
            reset();
            const isPending = response.data?.status === 'pending' || response?.status === 202;
            const message = response.data?.message || (isPending
                ? 'Role creation is awaiting chairman approval.'
                : 'Role created');
            addToast(message, isPending ? 'info' : 'success', isPending ? 8000 : undefined);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (role: string) => api.delete(`/api/roles/${role}`),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            const isPending = res.data?.status === 'pending' || res?.status === 202;
            setShowDeleteRoleModal(false);
            if (isPending) {
                const message = res.data?.message || 'Role deletion is awaiting chairman approval.';
                addToast(message, 'info', 8000);
            } else {
                setSelectedRole('');
                addToast(res.data?.message || 'Role deleted', 'success');
            }
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed', 'error');
        },
    });

    const renameMutation = useMutation({
        mutationFn: ({ role, name }: { role: string; name: string }) => api.put(`/api/roles/${role}/rename`, { name }),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            const isPending = res.data?.status === 'pending' || res?.status === 202;
            if (isPending) {
                const message = res.data?.message || 'Role rename is awaiting chairman approval.';
                addToast(message, 'info', 8000);
            } else {
                setSelectedRole(res.data.role);
                addToast(res.data?.message || 'Role renamed', 'success');
            }
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to rename role', 'error'),
    });

    const switchRole = (role: string) => {
        setSelectedRole(role);
        setPendingRole('');
        setShowDiscardModal(false);
        setRenameValue(roleLabel(role));
    };

    const requestRoleSwitch = (role: string) => {
        if (role === selectedRole) return;
        if (hasChanges(selectedRole)) {
            setPendingRole(role);
            setShowDiscardModal(true);
            return;
        }
        switchRole(role);
    };

    const discardAndSwitch = () => {
        if (!pendingRole) return;
        setDirtyPermissions(prev => {
            const next = { ...prev };
            delete next[selectedRole];
            return next;
        });
        switchRole(pendingRole);
    };

    const togglePermission = (permission: string) => {
        if (!selectedRole) return;
        setDirtyPermissions(prev => {
            const saved = initialFor(selectedRole);
            const current = prev[selectedRole] !== undefined ? normalize(prev[selectedRole]) : saved;
            const updated = normalize(
                current.includes(permission) ? current.filter(p => p !== permission) : [...current, permission],
            );
            if (updated.join('|') === saved.join('|')) {
                const next = { ...prev };
                delete next[selectedRole];
                return next;
            }
            return { ...prev, [selectedRole]: updated };
        });
    };

    const openSaveModal = () => {
        if (!hasChanges(selectedRole) || updateMutation.isPending) return;
        const current = currentFor(selectedRole);
        const initial = initialFor(selectedRole);
        setSaveSummary({
            added: current.filter(p => !initial.includes(p)),
            removed: initial.filter(p => !current.includes(p)),
        });
        setShowSaveModal(true);
    };

    const confirmSave = () => {
        if (!hasChanges(selectedRole) || updateMutation.isPending) return;
        const role = selectedRole;
        const permissions = currentFor(role);
        updateMutation.mutate({ role, permissions });
    };

    const openDeleteRoleModal = () => {
        if (!canManageRoleIdentity(selectedRole)) return;
        setShowDeleteRoleModal(true);
    };

    const confirmDeleteRole = () => {
        if (!canManageRoleIdentity(selectedRole) || deleteMutation.isPending) return;
        setShowDeleteRoleModal(false);
        deleteMutation.mutate(selectedRole);
    };

    const handleRename = () => {
        const proposed = renameValue.trim();
        if (!canManageRoleIdentity(selectedRole) || renameMutation.isPending) return;
        if (!proposed) {
            addToast('Role name is required.', 'error');
            return;
        }
        renameMutation.mutate({ role: selectedRole, name: proposed });
    };

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

    const openCreateModal = () => {
        reset();
        setNewRoleName('');
        setNewRoleTemplate('');
        setRoleErrors({});
        setShowCreateModal(true);
    };

    const restorePreset = () => {
        if (!selectedRole) return;
        if (!hasPreset(selectedRole)) {
            addToast(`No preset is defined for ${roleLabel(selectedRole)}.`, 'info');
            return;
        }
        const preset = presetFor(selectedRole);
        setDirtyPermissions(prev => {
            const saved = initialFor(selectedRole);
            const next = { ...prev };
            if (preset.join('|') === saved.join('|')) {
                delete next[selectedRole];
            } else {
                next[selectedRole] = preset;
            }
            return next;
        });
    };

    const groupGrantedCount = (permissions: string[]) => permissions.filter(p => currentPermissions.includes(p)).length;

    if (isLoading) {
        return <LoadingIndicator message="Loading roles and permissions..." />;
    }

    return (
        <div className="flex min-h-[calc(100vh-8rem)] flex-col gap-5 pb-2">
            <SettingsTabHeader
                eyebrow="Security & access"
                title="Roles & Permissions"
                description="Configure permissions for database roles. Core system features remain accessible to all roles."
                icon="fas fa-user-shield"
                helpText="Permissions determine which screens and actions a role can access. Changes affect every user assigned that role; individual officer overrides may further allow or deny access."
                action={
                    <button
                        type="button"
                        onClick={openCreateModal}
                        className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200"
                    >
                        <i className="fas fa-plus text-xs"></i>
                        Add Custom Role
                    </button>
                }
            />

            {roles.length === 0 ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-8 text-sm text-amber-800 shadow-sm">
                    No editable roles exist yet. Use the create role button above to add the first assignable role.
                </div>
            ) : (
                <>
                    <div className="grid flex-1 gap-5 xl:grid-cols-[240px_minmax(0,1fr)]">
                        <div className="space-y-2">
                            {roles.map(role => {
                                const active = selectedRole === role;
                                const count = currentFor(role).length;
                                const assigned = assignedUserCount(role);
                                return (
                                    <button
                                        key={role}
                                        type="button"
                                        onClick={() => requestRoleSwitch(role)}
                                        className={`group w-full rounded-xl border px-4 py-3.5 text-left transition-all ${
                                            active
                                                ? 'border-emerald-300 bg-emerald-50 shadow-sm ring-1 ring-emerald-200'
                                                : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className={`text-sm font-semibold ${active ? 'text-emerald-900' : 'text-gray-800'}`}>
                                                {roleLabel(role)}
                                            </span>
                                            <span
                                                className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'
                                                }`}
                                            >
                                                {count} / {totalEditableCount}
                                            </span>
                                        </div>
                                        <p className={`mt-1 text-xs leading-relaxed ${active ? 'text-emerald-700' : 'text-gray-500'}`}>
                                            {roleDescription(role)}
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${
                                                    isBaseRole(role) ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700'
                                                }`}
                                            >
                                                {isBaseRole(role) ? 'Base role' : 'Custom role'}
                                            </span>
                                            {assigned > 0 && (
                                                <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                                                    {assigned} assigned
                                                </span>
                                            )}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="space-y-3">
                            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div className="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="text-base font-semibold text-gray-900">{roleLabel(selectedRole)}</h3>
                                                {isBaseRole(selectedRole) ? (
                                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                                        Base role
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                                        Custom role
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-gray-600">{roleDescription(selectedRole)}</p>
                                        </div>

                                        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                            {assignedUserCount(selectedRole)} assigned
                                        </span>
                                    </div>

                                    <p className="mt-3 text-xs text-gray-400">
                                        Role key: <span className="font-mono">{selectedRole}</span>
                                    </p>
                                </div>

                                <div className="space-y-4 px-5 py-4">
                                    {isBaseRole(selectedRole) ? (
                                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                            Base roles stay available to the system. You can keep editing their permissions below,
                                            but rename and delete are locked.
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {hasChanges(selectedRole) && (
                                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                                    Save or discard the current permission draft before renaming or deleting this role.
                                                </div>
                                            )}
                                            {assignedUserCount(selectedRole) > 0 && (
                                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                                    This role is currently assigned to <span className="font-semibold">{assignedUserCount(selectedRole)}</span> user(s). Unassign it before renaming or deleting it.
                                                </div>
                                            )}

                                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                                <div className="space-y-2">
                                                    <label className="block text-sm font-medium text-gray-700">Rename role</label>
                                                    <input
                                                        type="text"
                                                        value={renameValue}
                                                        onChange={e => setRenameValue(e.target.value)}
                                                        onKeyDown={e => { if (e.key === 'Enter') handleRename(); }}
                                                        placeholder="Enter a new role name"
                                                        disabled={!canManageRoleIdentity(selectedRole) || renameMutation.isPending}
                                                        className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                                                    />
                                                    <p className="text-xs text-gray-500">
                                                        The saved role key will be normalized to lowercase slug format.
                                                    </p>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={handleRename}
                                                        disabled={!canManageRoleIdentity(selectedRole) || renameMutation.isPending || !renameValue.trim()}
                                                        className="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400"
                                                    >
                                                        {renameMutation.isPending ? 'Renaming...' : 'Rename Role'}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={openDeleteRoleModal}
                                                        disabled={!canManageRoleIdentity(selectedRole) || deleteMutation.isPending}
                                                        className="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400"
                                                    >
                                                        Delete Role
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {Object.entries(d?.groups || {}).map(([group, permissions]) => (
                                <div key={group} className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                    <div className="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-2.5">
                                        <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">{group}</h3>
                                        <span className="text-xs text-gray-400">
                                            {groupGrantedCount(permissions)} / {permissions.length}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-1 gap-px bg-gray-100 sm:grid-cols-2">
                                        {permissions.map(permission => {
                                            const on = currentPermissions.includes(permission);
                                            const isPresetForRole = hasPreset(selectedRole) && presetFor(selectedRole).includes(permission);
                                            return (
                                                <div key={permission} className="flex items-center justify-between gap-3 bg-white px-4 py-3 transition-colors hover:bg-gray-50">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium text-gray-800">{formatPermissionLabel(permission)}</p>
                                                        <div className="mt-0.5 flex items-center gap-2">
                                                            {isPresetForRole && (
                                                                <span className="shrink-0 text-[10px] font-medium text-emerald-600">preset</span>
                                                            )}
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() => togglePermission(permission)}
                                                        role="switch"
                                                        aria-checked={on}
                                                        className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 ${on ? 'bg-emerald-600' : 'bg-gray-200'}`}
                                                    >
                                                        <span
                                                            className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${on ? 'translate-x-4' : 'translate-x-0'}`}
                                                        />
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="sticky bottom-0 z-30 mt-2 border-t border-gray-200 bg-white/95 px-6 py-3 shadow-lg backdrop-blur-sm">
                        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                            <div className="flex items-center gap-2">
                                {hasChanges(selectedRole) ? (
                                    <div className="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 border border-amber-200">
                                        <i className="fas fa-circle-exclamation text-amber-600"></i>
                                        <span>Unsaved permission changes for {roleLabel(selectedRole)}</span>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2 px-1 text-xs text-gray-400">
                                        <i className="fas fa-check text-emerald-600"></i>
                                        <span>All permissions saved for {roleLabel(selectedRole)}</span>
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={restorePreset}
                                    disabled={!hasPreset(selectedRole)}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400"
                                >
                                    <i className="fas fa-rotate-left text-xs"></i>
                                    Restore Preset
                                </button>
                                <button
                                    type="button"
                                    onClick={openSaveModal}
                                    disabled={!hasChanges(selectedRole) || updateMutation.isPending}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:bg-emerald-300"
                                >
                                    <i className="fas fa-check text-xs"></i>
                                    <span>{updateMutation.isPending ? 'Saving...' : 'Save Changes'}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </>
            )}

            {showDiscardModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
                    <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                        <div className="flex items-start gap-4">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.13 14A1.5 1.5 0 0 0 3.46 20h17.08a1.5 1.5 0 0 0 1.3-2.14l-8.13-14a1.5 1.5 0 0 0-2.6 0Z" />
                                </svg>
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="text-lg font-semibold text-gray-900">Discard Changes?</h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    You have unsaved changes for <span className="font-medium">{roleLabel(selectedRole)}</span>.
                                    Switching roles will discard those edits.
                                </p>
                                <p className="mt-1 text-xs text-gray-400">
                                    Current draft will be reset before loading <span className="font-medium">{roleLabel(pendingRole)}</span>.
                                </p>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => { setShowDiscardModal(false); setPendingRole(''); }}
                                className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Keep Editing
                            </button>
                            <button
                                type="button"
                                onClick={discardAndSwitch}
                                className="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                            >
                                Discard Changes
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6" onClick={() => requestClose(() => setShowCreateModal(false))}>
                    <div className="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl" onClick={e => e.stopPropagation()}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">Create Custom Role</h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    Add a new assignable database role and optionally clone permissions from an existing one.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => requestClose(() => setShowCreateModal(false))}
                                className="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                                aria-label="Close create role modal"
                            >
                                <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div className="mt-6 space-y-4">
                            <div>
                                <label htmlFor="new-role-name" className="mb-1 block text-sm font-medium text-gray-700">Role name</label>
                                <input
                                    id="new-role-name"
                                    type="text"
                                    value={newRoleName}
                                    onChange={e => { markDirty(); setNewRoleName(e.target.value); if (roleErrors.name) setRoleErrors({}); }}
                                    placeholder="e.g. auditor or branch-supervisor"
                                    className={`w-full rounded-xl border px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 ${roleErrors.name ? 'border-rose-400' : 'border-gray-300'}`}
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    The name is saved as a lowercase slug, such as <span className="font-medium">branch-supervisor</span>.
                                </p>
                                {roleErrors.name && <p className="mt-1 text-xs font-medium text-rose-600">{roleErrors.name}</p>}
                            </div>

                            <div>
                                <label htmlFor="new-role-template" className="mb-1 block text-sm font-medium text-gray-700">Start with permissions from</label>
                                <select
                                    id="new-role-template"
                                    value={newRoleTemplate}
                                    onChange={e => { markDirty(); setNewRoleTemplate(e.target.value); }}
                                    className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                                >
                                    <option value="">Blank role with base dashboard/profile access</option>
                                    {roles.map(r => <option key={r} value={r}>{roleLabel(r)}</option>)}
                                </select>
                            </div>

                            <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                New roles can be assigned immediately in Officers after creation.
                            </div>

                            <div className="flex justify-end gap-3 border-t border-gray-200 pt-4">
                                <button
                                    type="button"
                                    onClick={() => requestClose(() => setShowCreateModal(false))}
                                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={handleCreateRole}
                                    disabled={createMutation.isPending}
                                    className="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
                                >
                                    {createMutation.isPending ? 'Creating...' : 'Create Role'}
                                </button>
                            </div>
                        </div>
                        {renderDialog()}
                    </div>
                </div>
            )}

            {showSaveModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
                    <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                        <div className="flex items-start gap-4">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="text-lg font-semibold text-gray-900">Review Changes</h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    You're about to save permission updates for <span className="font-medium">{roleLabel(selectedRole)}</span>.
                                </p>
                                <p className="mt-1 text-xs text-gray-400">
                                    Role key: <span className="font-mono">{selectedRole}</span>
                                </p>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Added</p>
                                <p className="mt-1 text-sm text-emerald-900">
                                    {saveSummary.added.length ? `${saveSummary.added.length} permission(s)` : 'No additions'}
                                </p>
                            </div>
                            <div className="rounded-xl border border-rose-200 bg-rose-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-rose-700">Removed</p>
                                <p className="mt-1 text-sm text-rose-900">
                                    {saveSummary.removed.length ? `${saveSummary.removed.length} permission(s)` : 'No removals'}
                                </p>
                            </div>
                        </div>

                        <div className="mt-5 space-y-4">
                            {saveSummary.added.length > 0 && (
                                <div className="rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Added permissions</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {saveSummary.added.map(permission => (
                                            <span key={permission} className="inline-flex max-w-full items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                                <span className="truncate">{formatPermissionLabel(permission)}</span>
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {saveSummary.removed.length > 0 && (
                                <div className="rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Removed permissions</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {saveSummary.removed.map(permission => (
                                            <span key={permission} className="inline-flex max-w-full items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700">
                                                <span className="truncate">{formatPermissionLabel(permission)}</span>
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setShowSaveModal(false)}
                                className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmSave}
                                disabled={updateMutation.isPending}
                                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300 transition-colors"
                            >
                                {updateMutation.isPending ? 'Saving...' : 'Confirm and Save'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showDeleteRoleModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
                    <div className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
                        <div className="flex items-start gap-4">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                                <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="text-lg font-semibold text-gray-900">Delete Custom Role?</h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    This will permanently remove <span className="font-medium">{roleLabel(selectedRole)}</span>
                                    from the available assignable roles.
                                </p>
                                <p className="mt-1 text-xs text-gray-400">
                                    Current key: <span className="font-mono">{selectedRole}</span>
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setShowDeleteRoleModal(false)}
                                className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmDeleteRole}
                                disabled={deleteMutation.isPending}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300"
                            >
                                {deleteMutation.isPending ? 'Deleting...' : 'Confirm Delete'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function normalize(list: string[]): string[] {
    return [...new Set((list || []).filter(Boolean))].sort();
}
