import { useAuth } from '../../contexts/AuthContext';

export default function OfficerOverview() {
    const { user } = useAuth();

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Welcome, {user?.name}!</h1>
                <p className="text-slate-500">Role: <span className="capitalize font-medium">{user?.primary_role}</span></p>
            </div>

            <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 className="mb-2 font-semibold text-slate-900 dark:text-white">Quick Actions</h2>
                <p className="text-sm text-slate-500">Use the sidebar to navigate to your assigned sections.</p>
            </div>
        </div>
    );
}
