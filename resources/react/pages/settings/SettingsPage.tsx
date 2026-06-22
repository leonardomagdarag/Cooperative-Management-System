import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { changePassword, revokeDevices, deactivateAccount, deleteAccount } from '../../api/settings';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';

export default function SettingsPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const isMember = user?.primary_role === 'member';

    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Settings</h1>

            {isMember ? (
                <MemberSettings />
            ) : (
                <OfficerSettings />
            )}
        </div>
    );
}

function MemberSettings() {
    const { addToast } = useToast();
    const [showPasswordForm, setShowPasswordForm] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const passwordMutation = useMutation({
        mutationFn: changePassword,
        onSuccess: () => {
            addToast('Password updated. Trusted devices were revoked.', 'success');
            setShowPasswordForm(false);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to change password', 'error'),
    });

    const revokeMutation = useMutation({
        mutationFn: revokeDevices,
        onSuccess: () => addToast('Trusted devices revoked.', 'success'),
        onError: () => addToast('Failed to revoke devices', 'error'),
    });

    const deactivateMutation = useMutation({
        mutationFn: () => deactivateAccount({ current_password: '' }),
        onSuccess: () => {
            addToast('Account deactivated.', 'success');
            window.location.href = '/login';
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    return (
        <div className="space-y-6">
            {/* Password */}
            <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h3 className="font-semibold text-slate-900 dark:text-white">Change Password</h3>
                <p className="mt-1 text-sm text-slate-500">Update your password to keep your account secure.</p>
                {!showPasswordForm ? (
                    <button onClick={() => setShowPasswordForm(true)} className="mt-4 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        Change Password
                    </button>
                ) : (
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        const fd = new FormData(e.currentTarget);
                        passwordMutation.mutate({
                            current_password: fd.get('current_password') as string,
                            password: fd.get('password') as string,
                            password_confirmation: fd.get('password_confirmation') as string,
                        });
                    }} className="mt-4 space-y-3">
                        <input type="password" name="current_password" placeholder="Current password" required className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                        <input type="password" name="password" placeholder="New password (min 8 chars)" required minLength={8} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                        <input type="password" name="password_confirmation" placeholder="Confirm new password" required className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setShowPasswordForm(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" disabled={passwordMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                {passwordMutation.isPending ? 'Updating...' : 'Update Password'}
                            </button>
                        </div>
                    </form>
                )}
            </div>

            {/* Trusted Devices */}
            <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h3 className="font-semibold text-slate-900 dark:text-white">Trusted Devices</h3>
                <p className="mt-1 text-sm text-slate-500">Revoke all trusted devices to force re-authentication.</p>
                <button
                    onClick={() => revokeMutation.mutate()}
                    disabled={revokeMutation.isPending}
                    className="mt-4 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                >
                    <i className="fas fa-shield-alt mr-1"></i>
                    {revokeMutation.isPending ? 'Revoking...' : 'Revoke All Devices'}
                </button>
            </div>

            {/* Account Deactivation */}
            <div className="rounded-xl border border-red-200 bg-white p-6 dark:border-red-800 dark:bg-slate-800">
                <h3 className="font-semibold text-red-600">Deactivate Account</h3>
                <p className="mt-1 text-sm text-slate-500">Deactivate your account. You can contact support to reactivate.</p>
                <button onClick={() => setShowDeleteConfirm(true)} className="mt-4 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Deactivate Account
                </button>

                {showDeleteConfirm && (
                    <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                        <p className="text-sm text-red-700 dark:text-red-300">Are you sure? This action requires your password.</p>
                        <div className="mt-3 flex gap-2">
                            <button onClick={() => setShowDeleteConfirm(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">Cancel</button>
                            <button
                                onClick={() => {
                                    const pw = prompt('Enter your password to confirm:');
                                    if (pw) deactivateMutation.mutate({ current_password: pw });
                                }}
                                className="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                            >
                                Confirm Deactivate
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function OfficerSettings() {
    const { addToast } = useToast();

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h3 className="font-semibold text-slate-900 dark:text-white">System Settings</h3>
                <p className="mt-1 text-sm text-slate-500">Manage system-wide settings.</p>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <a href="/dashboard/role-permissions" className="rounded-lg border border-slate-200 p-4 text-center hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700">
                        <i className="fas fa-user-shield text-2xl text-emerald-600"></i>
                        <p className="mt-2 text-sm font-medium">Roles & Permissions</p>
                    </a>
                    <a href="/dashboard/loan-products" className="rounded-lg border border-slate-200 p-4 text-center hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700">
                        <i className="fas fa-file-invoice text-2xl text-emerald-600"></i>
                        <p className="mt-2 text-sm font-medium">Loan Products</p>
                    </a>
                    <a href="/dashboard/public-content" className="rounded-lg border border-slate-200 p-4 text-center hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700">
                        <i className="fas fa-globe text-2xl text-emerald-600"></i>
                        <p className="mt-2 text-sm font-medium">Public Content</p>
                    </a>
                </div>
            </div>
        </div>
    );
}
