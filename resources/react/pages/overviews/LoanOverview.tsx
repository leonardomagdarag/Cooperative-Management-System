export default function LoanOverview() {
    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Loan Officer Dashboard</h1>
                <p className="text-slate-500">Overview of loan activities.</p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard icon="fas fa-clock" label="Pending Loans" value="—" color="amber" />
                <StatCard icon="fas fa-check-circle" label="Active Loans" value="—" color="emerald" />
                <StatCard icon="fas fa-exclamation-circle" label="Overdue Loans" value="—" color="red" />
                <StatCard icon="fas fa-money-bill-wave" label="Outstanding" value="—" color="blue" />
            </div>

            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                <p className="text-sm text-slate-500">Dashboard data will be populated when the overview API is built.</p>
            </div>
        </div>
    );
}

function StatCard({ icon, label, value, color }: { icon: string; label: string; value: string; color: string }) {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        red: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div className="flex items-center gap-3">
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors[color]}`}>
                    <i className={icon}></i>
                </div>
                <div>
                    <p className="text-xs text-slate-500">{label}</p>
                    <p className="text-lg font-bold text-slate-900 dark:text-white">{value}</p>
                </div>
            </div>
        </div>
    );
}
