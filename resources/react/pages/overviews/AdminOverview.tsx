import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { formatBytes } from '../../lib/format';

interface SystemHealthData {
    greeting?: string;
    period_label?: string;
    system_status?: 'operational' | 'attention' | 'critical';
    system_health?: {
        database?: {
            status: 'healthy' | 'unhealthy';
            driver: string;
            name: string;
        };
        environment?: {
            app_env: string;
            app_debug: boolean;
            php_version: string;
            laravel_version: string;
            timezone: string;
            server_time: string;
        };
        maintenance?: {
            active: boolean;
            public_login: boolean;
            member_login: boolean;
            officer_login: boolean;
        };
        services?: {
            deposit: boolean;
            withdrawal: boolean;
            loan_application: boolean;
            loan_payment: boolean;
        };
        integrations?: {
            sms?: {
                name: string;
                enabled: boolean;
                configured: boolean;
            };
            paymongo?: {
                name: string;
                enabled: boolean;
                mode: string;
                configured: boolean;
            };
            mail?: {
                name: string;
                mailer: string;
                from_address: string;
            };
        };
    };
    backup_status?: {
        health: 'ok' | 'attention' | 'never';
        last_run_at: string | null;
        tiers?: Record<string, {
            last_status: string | null;
            last_scope: string | null;
            last_run_at: string | null;
            last_size: number | null;
        }>;
    };
    security_summary?: {
        officers_breakdown?: Record<string, number>;
        recent_logs?: Array<{
            id: number;
            description: string;
            event: string;
            log_name: string;
            created_at_diff: string;
            created_at_iso: string;
            causer_name: string;
        }>;
        pending_privileged_actions?: number;
    };
    operations_summary?: {
        open_tickets?: number;
        urgent_tickets?: number;
        recent_tickets?: Array<{
            id: number;
            ticket_number: string;
            subject: string;
            category: string;
            priority: string;
            status: string;
            created_at_diff: string;
            user_name: string;
        }>;
    };
    total_officers?: number;
    staff_total?: number;
    total_members?: number;
}

const roleDescriptions: Record<string, string> = {
    admin: 'System, security & technical configuration',
    chairman: 'Board governance & countersign approvals',
    manager: 'Day-to-day operations & branch staff',
    accountant: 'Financial bookkeeping, expenses & ledger',
    loan: 'Borrower evaluation & loan applications',
};

const formatTimeAgo = (iso: string | null | undefined): string => {
    if (!iso) return 'Not yet recorded';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return 'Not yet recorded';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

export default function AdminOverview() {
    const { user } = useAuth();

    const { data, isLoading, isError } = useQuery({
        queryKey: ['overview-admin'],
        queryFn: () => api.get('/api/dashboard'),
        refetchInterval: 30_000,
    });

    const d: SystemHealthData = data?.data || {};

    if (isLoading) {
        return <OverviewSkeleton />;
    }

    if (isError) {
        return (
            <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">
                <i className="fas fa-triangle-exclamation text-2xl mb-2 text-red-500"></i>
                <p className="font-semibold">Unable to load system health information.</p>
                <p className="mt-1 text-xs text-red-600">Please refresh the page to try again.</p>
            </div>
        );
    }

    const health = d.system_health;
    const backupStatus = d.backup_status;
    const secSummary = d.security_summary;
    const opsSummary = d.operations_summary;

    const backupHealth = backupStatus?.health || 'never';
    const isDbHealthy = health?.database?.status === 'healthy';

    const services = health?.services || { deposit: true, withdrawal: true, loan_application: true, loan_payment: true };
    const onlineServicesCount = Object.values(services).filter(Boolean).length;
    const allServicesOnline = onlineServicesCount === 4;

    const totalOfficers = d.total_officers || d.staff_total || 0;
    const officersBreakdown = secSummary?.officers_breakdown || {};
    const recentLogs = secSummary?.recent_logs || [];

    const openTicketsCount = opsSummary?.open_tickets ?? 0;
    const urgentTicketsCount = opsSummary?.urgent_tickets ?? 0;
    const recentTickets = opsSummary?.recent_tickets || [];

    const isSystemHealthy = isDbHealthy && allServicesOnline && backupHealth !== 'attention';

    return (
        <div className="space-y-6">
            {/* Top Welcome Banner */}
            <div className="relative overflow-hidden bg-gradient-to-br from-emerald-800 via-teal-800 to-slate-900 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-emerald-200 text-sm font-medium uppercase tracking-widest mb-1">
                            System Administrator
                        </p>
                        <h1 className="text-3xl font-extrabold leading-tight">
                            {d.greeting || 'Good day'}, {user?.first_name || 'Admin'}
                        </h1>
                        <p className="mt-1 text-emerald-200">
                            Overall system health, member service status, and data safety
                        </p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-emerald-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">
                            {new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                        </p>
                        <p className="text-emerald-200 text-sm">
                            {new Date().toLocaleDateString('en-US', { weekday: 'long' })}
                        </p>
                    </div>
                </div>

                {/* Plain-Language Status Pills */}
                <div className="relative mt-5 flex flex-wrap gap-3">
                    {isSystemHealthy ? (
                        <span className="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-emerald-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <i className="fas fa-check-circle text-emerald-300"></i>
                            All systems running normally
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <i className="fas fa-triangle-exclamation text-amber-300"></i>
                            One or more services need attention
                        </span>
                    )}

                    <span className="inline-flex items-center gap-1.5 bg-white/10 border border-white/15 text-emerald-100 text-xs font-medium px-3 py-1.5 rounded-full">
                        <i className="fas fa-shield-halved text-emerald-300"></i>
                        {backupHealth === 'ok' ? 'Automatic backups safe & up to date' : 'Backups need attention'}
                    </span>

                    {urgentTicketsCount > 0 ? (
                        <span className="inline-flex items-center gap-1.5 bg-red-400/20 border border-red-300/40 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <i className="fas fa-headset text-red-300"></i>
                            {urgentTicketsCount} urgent member question{urgentTicketsCount !== 1 ? 's' : ''}
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 bg-white/10 border border-white/15 text-emerald-100 text-xs font-medium px-3 py-1.5 rounded-full">
                            <i className="fas fa-headset text-emerald-300"></i>
                            {openTicketsCount === 0 ? 'No pending member inquiries' : `${openTicketsCount} open member inquiries`}
                        </span>
                    )}
                </div>
            </div>

            {/* Top 4 KPI Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {/* 1. Core Server & Database */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-server w-5 h-5 text-emerald-600"></i>
                        </div>
                        <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${
                            isDbHealthy ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'
                        }`}>
                            {isDbHealthy ? 'Healthy' : 'Issue Detected'}
                        </span>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">
                        {isDbHealthy ? 'Connected' : 'Offline'}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">Main Database & Server</p>
                </div>

                {/* 2. Automated Backups */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-shield-halved w-5 h-5 text-blue-600"></i>
                        </div>
                        <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${
                            backupHealth === 'ok' ? 'bg-emerald-50 text-emerald-700' :
                            backupHealth === 'attention' ? 'bg-red-50 text-red-700' :
                            'bg-gray-100 text-gray-600'
                        }`}>
                            {backupHealth === 'ok' ? 'Safe' : backupHealth === 'attention' ? 'Needs Check' : 'Pending'}
                        </span>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">
                        {backupHealth === 'ok' ? 'Up to Date' : backupHealth === 'attention' ? 'Action Needed' : 'No Runs Yet'}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                        {backupStatus?.last_run_at ? `Last run: ${formatTimeAgo(backupStatus.last_run_at)}` : 'Automatic Data Backups'}
                    </p>
                </div>

                {/* 3. Member Services */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-toggle-on w-5 h-5 text-teal-600"></i>
                        </div>
                        <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${
                            allServicesOnline ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
                        }`}>
                            {allServicesOnline ? 'All Open' : `${4 - onlineServicesCount} Paused`}
                        </span>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">
                        {onlineServicesCount} of 4 Available
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                        {health?.maintenance?.public_login ? 'Member login is active' : 'Member login restricted'}
                    </p>
                </div>

                {/* 4. Support Inquiries */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-headset w-5 h-5 text-indigo-600"></i>
                        </div>
                        <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full ${
                            urgentTicketsCount > 0 ? 'bg-red-50 text-red-700 font-bold' :
                            openTicketsCount > 0 ? 'bg-blue-50 text-blue-700' :
                            'bg-emerald-50 text-emerald-700'
                        }`}>
                            {urgentTicketsCount > 0 ? `${urgentTicketsCount} Urgent` : openTicketsCount > 0 ? `${openTicketsCount} Active` : 'All Resolved'}
                        </span>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">
                        {openTicketsCount === 0 ? 'All Clear' : `${openTicketsCount} Open`}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">Member Support Desk</p>
                </div>
            </div>

            {/* 2-Column Content Layout (Matching other dashboards) */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* ─── Column 1 ─── */}
                <div className="space-y-6">
                    {/* Member Transaction Services Status */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="border-b border-slate-100 pb-4 mb-4">
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i className="fas fa-circle-check text-emerald-600 text-base"></i>
                                Member Services Availability
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Current status of cooperative services for members
                            </p>
                        </div>

                        <div className="space-y-3">
                            <ServiceRow
                                name="Savings Deposits"
                                description="Members can deposit money into their savings accounts"
                                active={services.deposit}
                                icon="fa-piggy-bank"
                            />
                            <ServiceRow
                                name="Member Withdrawals"
                                description="Members can request counter or online cash withdrawals"
                                active={services.withdrawal}
                                icon="fa-money-bill-transfer"
                            />
                            <ServiceRow
                                name="Loan Applications"
                                description="Members can submit new loan and credit requests"
                                active={services.loan_application}
                                icon="fa-file-signature"
                            />
                            <ServiceRow
                                name="Loan Repayments"
                                description="Members can make payments on their existing loans"
                                active={services.loan_payment}
                                icon="fa-receipt"
                            />
                        </div>
                    </div>

                    {/* Third-Party Integrations */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="border-b border-slate-100 pb-4 mb-4">
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i className="fas fa-plug text-blue-600 text-base"></i>
                                Connected External Services
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Automated messaging, online payment, and email connections
                            </p>
                        </div>

                        <div className="space-y-3.5">
                            {/* SMS Text Messaging */}
                            <div className="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <i className="fas fa-comment-sms text-sm"></i>
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">SMS Text Messaging (TextBee)</p>
                                        <p className="text-xs text-slate-500 mt-0.5">
                                            Sends one-time security codes (OTP) and transaction alerts to member mobile phones.
                                        </p>
                                    </div>
                                </div>
                                <span className={`px-2.5 py-1 text-[11px] font-semibold rounded-full shrink-0 ${
                                    health?.integrations?.sms?.enabled && health?.integrations?.sms?.configured
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-amber-100 text-amber-800'
                                }`}>
                                    {health?.integrations?.sms?.enabled && health?.integrations?.sms?.configured ? 'Active' : 'Not Configured'}
                                </span>
                            </div>

                            {/* Online Payments */}
                            <div className="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-cyan-100 text-cyan-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <i className="fas fa-credit-card text-sm"></i>
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">Online Payments (PayMongo)</p>
                                        <p className="text-xs text-slate-500 mt-0.5">
                                            Accepts digital deposits and loan payments through GCash, Maya, and bank cards.
                                        </p>
                                    </div>
                                </div>
                                <span className={`px-2.5 py-1 text-[11px] font-semibold rounded-full shrink-0 ${
                                    health?.integrations?.paymongo?.enabled
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-slate-200 text-slate-700'
                                }`}>
                                    {health?.integrations?.paymongo?.enabled ? 'Active' : 'Turned Off'}
                                </span>
                            </div>

                            {/* System Email */}
                            <div className="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <i className="fas fa-envelope text-sm"></i>
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">Email System</p>
                                        <p className="text-xs text-slate-500 mt-0.5">
                                            Sends account verification emails, receipts, and official cooperative notices.
                                        </p>
                                    </div>
                                </div>
                                <span className="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 shrink-0">
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Automatic Data Backups */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="border-b border-slate-100 pb-4 mb-4">
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i className="fas fa-shield-halved text-blue-600 text-base"></i>
                                Data Safety & Backups
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Automatic backups protect member balances, accounts, and records
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            {(['hourly', 'daily', 'offsite'] as const).map(tier => {
                                const b = backupStatus?.tiers?.[tier] || {};
                                const isOk = b.last_status === 'success';
                                const isFail = b.last_status === 'failed';

                                return (
                                    <div
                                        key={tier}
                                        className={`rounded-xl border p-4 ${
                                            isFail ? 'border-red-200 bg-red-50/50' :
                                            isOk ? 'border-slate-100 bg-slate-50/50' :
                                            'border-slate-100 bg-slate-50/30'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span className="text-xs font-bold text-slate-900 capitalize">
                                                {tier === 'hourly' ? 'Recent Backup' : tier === 'daily' ? 'Daily Archive' : 'Off-Site Cloud'}
                                            </span>
                                            <span className={`px-2 py-0.5 text-[10px] font-semibold rounded-full ${
                                                isOk ? 'bg-emerald-100 text-emerald-800' :
                                                isFail ? 'bg-red-100 text-red-800' :
                                                'bg-slate-200 text-slate-700'
                                            }`}>
                                                {isOk ? 'Saved' : isFail ? 'Failed' : 'Pending'}
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-500 mt-1">
                                            {b.last_run_at ? formatTimeAgo(b.last_run_at) : 'No backup yet'}
                                        </p>
                                        {b.last_size && (
                                            <p className="text-[11px] text-slate-400 mt-0.5 font-mono">
                                                {formatBytes(b.last_size)}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* ─── Column 2 ─── */}
                <div className="space-y-6">
                    {/* Authorized Officers */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="border-b border-slate-100 pb-4 mb-4">
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i className="fas fa-users-gear text-indigo-600 text-base"></i>
                                Authorized Officers & Roles
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Staff members authorized to operate and govern the cooperative system
                            </p>
                        </div>

                        <div className="space-y-3">
                            {Object.entries(officersBreakdown).length > 0 ? (
                                Object.entries(officersBreakdown).map(([role, count]) => (
                                    <div
                                        key={role}
                                        className="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-slate-50/50"
                                    >
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900 capitalize">
                                                {role.replace(/_/g, ' ')}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {roleDescriptions[role] || 'Authorized cooperative role'}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-900 border border-slate-200 shadow-2xs">
                                            {count} {count === 1 ? 'user' : 'users'}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <p className="text-xs text-slate-400 py-2">No officer roles recorded.</p>
                            )}
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span>Total authorized officers</span>
                            <span className="font-bold text-slate-900">{totalOfficers} active staff</span>
                        </div>
                    </div>

                    {/* Member Support Inquiries */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="border-b border-slate-100 pb-4 mb-4">
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i className="fas fa-headset text-violet-600 text-base"></i>
                                Member Support Inquiries
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Member questions and assistance requests
                            </p>
                        </div>

                        {recentTickets.length > 0 ? (
                            <div className="space-y-2.5">
                                {recentTickets.map(ticket => (
                                    <div
                                        key={ticket.id}
                                        className="p-3 rounded-xl border border-slate-100 bg-slate-50/50 flex items-start justify-between gap-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-xs font-bold text-slate-900">
                                                    {ticket.ticket_number}
                                                </span>
                                                {ticket.priority === 'urgent' && (
                                                    <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-800 uppercase">
                                                        Urgent
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-sm font-medium text-slate-900 truncate mt-0.5">
                                                {ticket.subject}
                                            </p>
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                From <strong className="text-slate-700">{ticket.user_name}</strong> · {ticket.created_at_diff}
                                            </p>
                                        </div>
                                        <span className="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-blue-50 text-blue-700 capitalize shrink-0">
                                            {ticket.status.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-slate-400">
                                <i className="fas fa-circle-check text-2xl text-emerald-500 mb-2"></i>
                                <p className="text-sm font-semibold text-slate-800">All member questions are answered</p>
                                <p className="text-xs text-slate-400 mt-0.5">There are no pending tickets requiring attention.</p>
                            </div>
                        )}
                    </div>

                    {/* Recent Security Activity Trail */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="border-b border-slate-100 pb-4 mb-4">
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i className="fas fa-clock-rotate-left text-slate-700 text-base"></i>
                                Recent System Activity
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Chronological record of administrative and security events
                            </p>
                        </div>

                        {recentLogs.length > 0 ? (
                            <div className="space-y-3">
                                {recentLogs.map(log => (
                                    <div key={log.id} className="flex items-start gap-3 text-xs">
                                        <span className="mt-0.5 flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-600 text-[10px] shrink-0">
                                            <i className="fas fa-circle-info"></i>
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold text-slate-900 truncate">
                                                {log.description || 'System event recorded'}
                                            </p>
                                            <p className="text-[11px] text-slate-500 mt-0.5">
                                                {log.causer_name} · {log.created_at_diff}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-slate-400 py-4 text-center">No recent activity recorded.</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

function ServiceRow({ name, description, active, icon }: { name: string; description: string; active: boolean; icon: string }) {
    return (
        <div className={`p-4 rounded-xl border flex items-start justify-between gap-3 ${
            active ? 'border-slate-100 bg-slate-50/50' : 'border-amber-200 bg-amber-50/50'
        }`}>
            <div className="flex items-start gap-3">
                <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${
                    active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                }`}>
                    <i className={`fas ${icon} text-sm`}></i>
                </div>
                <div>
                    <p className="text-sm font-semibold text-slate-900">{name}</p>
                    <p className="text-xs text-slate-500 mt-0.5">{description}</p>
                </div>
            </div>
            <span className={`px-2.5 py-1 text-[11px] font-semibold rounded-full shrink-0 ${
                active ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
            }`}>
                {active ? 'Available' : 'Paused'}
            </span>
        </div>
    );
}

function OverviewSkeleton() {
    return (
        <div className="space-y-6 animate-pulse">
            <div className="h-44 rounded-2xl bg-slate-200" />
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {[...Array(4)].map((_, i) => (
                    <div key={i} className="h-28 rounded-2xl bg-slate-100" />
                ))}
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <div className="h-80 rounded-2xl bg-slate-100" />
                <div className="h-80 rounded-2xl bg-slate-100" />
            </div>
        </div>
    );
}