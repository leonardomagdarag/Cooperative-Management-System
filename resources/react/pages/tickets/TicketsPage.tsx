import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getTickets, type TicketSummary, type TicketQueryParams } from '../../api/tickets';
import NewTicketModal from '../../components/tickets/NewTicketModal';
import TicketDetailModal from '../../components/tickets/TicketDetailModal';
import Button from '../../components/ui/Button';
import Pagination from '../../components/ui/Pagination';

const CATEGORIES = [
    { value: '', label: 'All Categories' },
    { value: 'membership', label: 'Membership' },
    { value: 'savings_deposits', label: 'Savings & Deposits' },
    { value: 'withdrawals', label: 'Withdrawals & OTP' },
    { value: 'loans', label: 'Loans & Borrowing' },
    { value: 'repayments', label: 'Loan Repayments' },
    { value: 'account_security', label: 'Account Security' },
    { value: 'technical_issue', label: 'Technical Issue' },
    { value: 'other', label: 'General / Other' },
];

export default function TicketsPage() {
    const [scope, setScope] = useState<'all' | 'mine'>('all');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [priorityFilter, setPriorityFilter] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [page, setPage] = useState(1);

    const [isNewTicketOpen, setIsNewTicketOpen] = useState(false);
    const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);

    const queryParams: TicketQueryParams = {
        scope,
        search: search.trim() || undefined,
        status: statusFilter || undefined,
        priority: priorityFilter || undefined,
        category: categoryFilter || undefined,
        page,
        per_page: 10,
    };

    const { data: ticketData, isLoading, refetch } = useQuery({
        queryKey: ['tickets', queryParams],
        queryFn: () => getTickets(queryParams),
    });

    const counts = ticketData?.counts;

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'open':
                return <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><span className="w-1.5 h-1.5 rounded-full bg-blue-600 animate-pulse" />Open</span>;
            case 'in_progress':
                return <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800"><span className="w-1.5 h-1.5 rounded-full bg-amber-600" />In Progress</span>;
            case 'resolved':
                return <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"><i className="fas fa-check text-[10px]" />Resolved</span>;
            case 'closed':
                return <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Closed</span>;
            default:
                return <span className="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{status}</span>;
        }
    };

    const getPriorityBadge = (priority: string) => {
        switch (priority) {
            case 'urgent':
                return <span className="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-rose-100 text-rose-700">Urgent</span>;
            case 'high':
                return <span className="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-amber-100 text-amber-700">High</span>;
            case 'medium':
                return <span className="px-2 py-0.5 rounded text-[11px] font-medium uppercase bg-blue-100 text-blue-700">Medium</span>;
            case 'low':
                return <span className="px-2 py-0.5 rounded text-[11px] font-medium uppercase bg-gray-100 text-gray-600">Low</span>;
            default:
                return null;
        }
    };

    return (
        <div className="space-y-6">
            {/* Header Banner */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-linear-to-r from-emerald-600 to-cyan-600 rounded-2xl p-6 text-white shadow-lg">
                <div>
                    <h1 className="text-2xl font-bold">Support Desk & Tickets</h1>
                    <p className="mt-1 text-sm text-white/80">
                        Manage member inquiries, resolve technical issues, and provide cooperative support.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="md"
                    onClick={() => setIsNewTicketOpen(true)}
                    className="bg-white/10 hover:bg-white/20 text-white border-white/30 shrink-0"
                >
                    <i className="fas fa-plus mr-1.5"></i>
                    New Ticket
                </Button>
            </div>

            {/* Metric KPI Cards */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Tickets</span>
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 text-sm">
                            <i className="fas fa-ticket-alt"></i>
                        </span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-gray-900">{counts?.total ?? 0}</p>
                </div>

                <div className="rounded-2xl border border-blue-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wider text-blue-600">Open Inquiries</span>
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 text-sm">
                            <i className="fas fa-inbox"></i>
                        </span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-blue-600">{counts?.open ?? 0}</p>
                </div>

                <div className="rounded-2xl border border-amber-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wider text-amber-600">In Progress</span>
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 text-sm">
                            <i className="fas fa-spinner"></i>
                        </span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-amber-600">{counts?.in_progress ?? 0}</p>
                </div>

                <div className="rounded-2xl border border-emerald-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wider text-emerald-600">Resolved / Closed</span>
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 text-sm">
                            <i className="fas fa-check-circle"></i>
                        </span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-emerald-600">
                        {(counts?.resolved ?? 0) + (counts?.closed ?? 0)}
                    </p>
                </div>
            </div>

            {/* Scope Switcher & Filters */}
            <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs space-y-4">
                {/* Tabs */}
                <div className="flex items-center justify-between border-b border-gray-200 pb-3 flex-wrap gap-3">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => { setScope('all'); setPage(1); }}
                            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all ${
                                scope === 'all'
                                    ? 'bg-emerald-600 text-white shadow-xs'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            <i className="fas fa-headset text-xs"></i>
                            <span>Support Queue (All Tickets)</span>
                            {counts?.active_total !== undefined && counts.active_total > 0 && (
                                <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${
                                    scope === 'all' ? 'bg-white text-emerald-700' : 'bg-emerald-100 text-emerald-800'
                                }`}>
                                    {counts.active_total}
                                </span>
                            )}
                        </button>

                        <button
                            type="button"
                            onClick={() => { setScope('mine'); setPage(1); }}
                            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all ${
                                scope === 'mine'
                                    ? 'bg-emerald-600 text-white shadow-xs'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            <i className="fas fa-user text-xs"></i>
                            <span>My Tickets</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={() => refetch()}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-900 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                        title="Refresh ticket queue"
                    >
                        <i className="fas fa-rotate text-xs"></i>
                        <span>Refresh</span>
                    </button>
                </div>

                {/* Filter Controls */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
                    {/* Search */}
                    <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <i className="fas fa-search text-xs"></i>
                        </span>
                        <input
                            type="text"
                            value={search}
                            onChange={e => { setSearch(e.target.value); setPage(1); }}
                            placeholder="Search by ticket #, subject, user..."
                            className="w-full rounded-xl border border-gray-300 bg-white py-2 pl-9 pr-3 text-xs text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>

                    {/* Status filter */}
                    <div>
                        <select
                            value={statusFilter}
                            onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option value="">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    {/* Priority filter */}
                    <div>
                        <select
                            value={priorityFilter}
                            onChange={e => { setPriorityFilter(e.target.value); setPage(1); }}
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option value="">All Priorities</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>

                    {/* Category filter */}
                    <div>
                        <select
                            value={categoryFilter}
                            onChange={e => { setCategoryFilter(e.target.value); setPage(1); }}
                            className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            {CATEGORIES.map(c => (
                                <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Tickets Table / Cards */}
            <div className="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
                {isLoading ? (
                    <div className="py-20 text-center text-gray-500">
                        <i className="fas fa-circle-notch fa-spin text-3xl text-emerald-600 mb-2" />
                        <p className="text-sm">Loading ticket queue...</p>
                    </div>
                ) : (ticketData?.data?.length ?? 0) === 0 ? (
                    <div className="py-16 text-center text-gray-500">
                        <i className="fas fa-ticket-alt text-4xl text-gray-300 mb-3" />
                        <h3 className="font-semibold text-gray-800 text-base">No tickets found</h3>
                        <p className="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            {search || statusFilter || priorityFilter || categoryFilter
                                ? 'No tickets match the selected filters.'
                                : scope === 'all'
                                ? 'The support queue is clear. No tickets have been submitted.'
                                : 'You have not submitted any internal tickets.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[700px] text-left border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-gray-200 bg-gray-50/75 text-gray-600 font-semibold uppercase tracking-wider">
                                    <th className="py-3 px-4">Ticket</th>
                                    <th className="py-3 px-4">Requester</th>
                                    <th className="py-3 px-4">Topic / Subject</th>
                                    <th className="py-3 px-4">Priority</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4">Assignee</th>
                                    <th className="py-3 px-4 text-right">Last Activity</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {ticketData?.data.map((ticket: TicketSummary) => (
                                    <tr
                                        key={ticket.id}
                                        onClick={() => setSelectedTicketId(ticket.id)}
                                        className="hover:bg-emerald-50/40 cursor-pointer transition-colors group"
                                    >
                                        <td className="py-3.5 px-4 font-mono font-bold text-emerald-700 whitespace-nowrap">
                                            {ticket.ticket_number}
                                        </td>

                                        <td className="py-3.5 px-4">
                                            <div className="font-semibold text-gray-900 group-hover:text-emerald-700 transition-colors truncate max-w-[140px]">
                                                {ticket.requester_name}
                                            </div>
                                            <div className="text-[11px] text-gray-500 capitalize">
                                                {ticket.requester_role}
                                            </div>
                                        </td>

                                        <td className="py-3.5 px-4 max-w-xs">
                                            <div className="flex items-center gap-1.5 mb-0.5">
                                                <span className="text-[10px] px-1.5 py-0.2 rounded bg-gray-100 text-gray-600 uppercase font-semibold">
                                                    {ticket.category.replace(/_/g, ' ')}
                                                </span>
                                            </div>
                                            <div className="font-medium text-gray-800 truncate" title={ticket.subject}>
                                                {ticket.subject}
                                            </div>
                                            {ticket.latest_message_snippet && (
                                                <div className="text-[11px] text-gray-400 truncate">
                                                    {ticket.latest_message_snippet}
                                                </div>
                                            )}
                                        </td>

                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            {getPriorityBadge(ticket.priority)}
                                        </td>

                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            {getStatusBadge(ticket.status)}
                                        </td>

                                        <td className="py-3.5 px-4 whitespace-nowrap text-gray-600">
                                            {ticket.assignee_name ? (
                                                <span className="inline-flex items-center gap-1 font-medium text-gray-800">
                                                    <i className="fas fa-user-check text-emerald-600 text-[10px]" />
                                                    {ticket.assignee_name}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400 italic">Unassigned</span>
                                            )}
                                        </td>

                                        <td className="py-3.5 px-4 text-right text-gray-500 whitespace-nowrap">
                                            <div>{ticket.created_at_human}</div>
                                            <span className="text-[10px] font-medium text-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity">
                                                Open thread &rarr;
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {ticketData?.pagination && ticketData.pagination.last_page > 1 && (
                    <div className="p-4 border-t border-gray-200">
                        <Pagination
                            currentPage={ticketData.pagination.current_page}
                            lastPage={ticketData.pagination.last_page}
                            onPageChange={setPage}
                        />
                    </div>
                )}
            </div>

            {/* Modals */}
            <NewTicketModal
                open={isNewTicketOpen}
                onClose={() => setIsNewTicketOpen(false)}
                onSuccess={(ticket) => {
                    refetch();
                    setSelectedTicketId(ticket.id);
                }}
            />

            <TicketDetailModal
                ticketId={selectedTicketId}
                open={!!selectedTicketId}
                onClose={() => setSelectedTicketId(null)}
                onTicketUpdated={() => refetch()}
            />
        </div>
    );
}
