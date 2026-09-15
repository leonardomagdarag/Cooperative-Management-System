import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLocation } from 'react-router';
import { getFaqs, getTickets, type FaqCategory, type TicketSummary } from '../../api/tickets';
import NewTicketModal from '../../components/tickets/NewTicketModal';
import TicketDetailModal from '../../components/tickets/TicketDetailModal';
import Button from '../../components/ui/Button';
import SettingsTabHeader from './SettingsTabHeader';

export default function HelpSupportTab() {
    const location = useLocation();
    const initialSubTab = (location.state as { subTab?: 'faqs' | 'tickets' } | null)?.subTab || 'faqs';

    const [activeSubTab, setActiveSubTab] = useState<'faqs' | 'tickets'>(initialSubTab);
    const [ticketFilter, setTicketFilter] = useState<'active' | 'closed' | 'all'>('active');

    // FAQ state
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [expandedFaqIndex, setExpandedFaqIndex] = useState<string | null>(null);

    // Ticket modals
    const [isNewTicketOpen, setIsNewTicketOpen] = useState(false);
    const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);

    // Query FAQs
    const { data: faqCategories = [], isLoading: isLoadingFaqs } = useQuery({
        queryKey: ['faqs'],
        queryFn: getFaqs,
    });

    // Query User's Tickets
    const { data: ticketData, isLoading: isLoadingTickets, refetch: refetchTickets } = useQuery({
        queryKey: ['tickets', 'mine', ticketFilter],
        queryFn: () => getTickets({
            scope: 'mine',
            status_group: ticketFilter === 'all' ? undefined : ticketFilter,
        }),
    });

    const activeTicketsCount = ticketData?.counts?.active_total ?? 0;

    // Filter FAQs based on category and search query
    const filteredCategories = useMemo(() => {
        const q = searchQuery.toLowerCase().trim();

        return faqCategories
            .filter(cat => selectedCategory === 'all' || cat.id === selectedCategory)
            .map(cat => {
                const matchingFaqs = cat.faqs.filter(faq => {
                    if (!q) return true;
                    return (
                        faq.q.toLowerCase().includes(q) ||
                        faq.a.toLowerCase().includes(q) ||
                        faq.tags.some(tag => tag.toLowerCase().includes(q))
                    );
                });
                return { ...cat, faqs: matchingFaqs };
            })
            .filter(cat => cat.faqs.length > 0);
    }, [faqCategories, selectedCategory, searchQuery]);

    const toggleFaq = (key: string) => {
        setExpandedFaqIndex(prev => (prev === key ? null : key));
    };

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
            <SettingsTabHeader
                eyebrow="Helpdesk & Support"
                title="Help & Support"
                description="Explore system guides, search frequently asked questions, or communicate with cooperative support staff."
                icon="fas fa-life-ring"
            />

            {/* Sub-tab Switcher */}
            <div className="flex items-center gap-2 border-b border-gray-200">
                <button
                    type="button"
                    onClick={() => setActiveSubTab('faqs')}
                    className={`flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors ${
                        activeSubTab === 'faqs'
                            ? 'border-emerald-600 text-emerald-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                >
                    <i className="fas fa-circle-question text-xs"></i>
                    <span>FAQs & Guides</span>
                </button>

                <button
                    type="button"
                    onClick={() => setActiveSubTab('tickets')}
                    className={`flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors ${
                        activeSubTab === 'tickets'
                            ? 'border-emerald-600 text-emerald-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                >
                    <i className="fas fa-comments text-xs"></i>
                    <span>Active Tickets & Messages</span>
                    {activeTicketsCount > 0 && (
                        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                            {activeTicketsCount}
                        </span>
                    )}
                </button>
            </div>

            {/* Sub-tab 1: FAQs & Guides */}
            {activeSubTab === 'faqs' && (
                <div className="space-y-6">
                    {/* Search & Categories Bar */}
                    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs space-y-4">
                        <div className="relative">
                            <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <i className="fas fa-search text-sm"></i>
                            </span>
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                placeholder="Search questions, keywords (e.g. withdrawal, loan, OTP, password)..."
                                className="w-full rounded-xl border border-gray-300 bg-gray-50/50 py-2.5 pl-10 pr-4 text-sm text-gray-900 placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => setSearchQuery('')}
                                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs"
                                >
                                    <i className="fas fa-times"></i>
                                </button>
                            )}
                        </div>

                        {/* Category Pills */}
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('all')}
                                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                                    selectedCategory === 'all'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                All Topics
                            </button>
                            {faqCategories.map(cat => (
                                <button
                                    key={cat.id}
                                    type="button"
                                    onClick={() => setSelectedCategory(cat.id)}
                                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                                        selectedCategory === cat.id
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    }`}
                                >
                                    <i className={`fas ${cat.icon} text-[11px]`}></i>
                                    <span>{cat.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* FAQ List */}
                    {isLoadingFaqs ? (
                        <div className="py-12 text-center text-gray-500">
                            <i className="fas fa-circle-notch fa-spin text-2xl text-emerald-600 mb-2" />
                            <p className="text-sm">Loading knowledge base...</p>
                        </div>
                    ) : filteredCategories.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center">
                            <i className="fas fa-question-circle text-4xl text-gray-300 mb-3" />
                            <h3 className="font-semibold text-gray-800 text-base">No matching questions found</h3>
                            <p className="text-sm text-gray-500 mt-1 max-w-md mx-auto">
                                Can't find the answer you need? You can submit a direct support ticket to our team.
                            </p>
                            <Button
                                type="button"
                                variant="primary"
                                size="sm"
                                onClick={() => {
                                    setActiveSubTab('tickets');
                                    setIsNewTicketOpen(true);
                                }}
                                className="mt-4"
                            >
                                <i className="fas fa-ticket-alt mr-1.5"></i>
                                Submit a Support Ticket
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {filteredCategories.map(cat => (
                                <div key={cat.id} className="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs">
                                    <div className="flex items-center gap-2.5 mb-4 pb-3 border-b border-gray-100">
                                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 text-sm">
                                            <i className={`fas ${cat.icon}`}></i>
                                        </span>
                                        <div>
                                            <h3 className="font-bold text-gray-900 text-base">{cat.label}</h3>
                                            <p className="text-xs text-gray-500">{cat.description}</p>
                                        </div>
                                    </div>

                                    <div className="space-y-2.5">
                                        {cat.faqs.map((faq, idx) => {
                                            const key = `${cat.id}-${idx}`;
                                            const isOpen = expandedFaqIndex === key;

                                            return (
                                                <div
                                                    key={idx}
                                                    className="rounded-xl border border-gray-200 overflow-hidden transition-all duration-200"
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleFaq(key)}
                                                        className="w-full flex items-center justify-between gap-4 p-4 text-left font-semibold text-sm text-gray-800 hover:bg-gray-50/70 transition-colors"
                                                    >
                                                        <span className="flex items-center gap-2.5">
                                                            <i className="fas fa-question-circle text-emerald-600 text-xs shrink-0"></i>
                                                            <span>{faq.q}</span>
                                                        </span>
                                                        <i className={`fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200 ${isOpen ? 'rotate-180 text-emerald-600' : ''}`} />
                                                    </button>

                                                    {isOpen && (
                                                        <div className="px-4 pb-4 pt-1 text-sm text-gray-600 bg-gray-50/40 border-t border-gray-100 leading-relaxed">
                                                            <p className="whitespace-pre-line">{faq.a}</p>
                                                            {faq.tags && faq.tags.length > 0 && (
                                                                <div className="mt-3 flex flex-wrap gap-1.5">
                                                                    {faq.tags.map(tag => (
                                                                        <span key={tag} className="text-[11px] px-2 py-0.5 rounded-md bg-white border border-gray-200 text-gray-500 font-medium">
                                                                            #{tag}
                                                                        </span>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Support Callout Banner */}
                    <div className="rounded-2xl border border-emerald-200 bg-linear-to-r from-emerald-50 to-teal-50 p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                        <div className="flex items-center gap-3">
                            <div className="h-12 w-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xl shrink-0 shadow-sm">
                                <i className="fas fa-headset"></i>
                            </div>
                            <div>
                                <h4 className="font-bold text-gray-900 text-base">Still need assistance?</h4>
                                <p className="text-xs text-gray-600 mt-0.5">
                                    Our support team and officers are ready to help fix your issue or answer questions.
                                </p>
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="primary"
                            size="md"
                            onClick={() => {
                                setActiveSubTab('tickets');
                                setIsNewTicketOpen(true);
                            }}
                            className="shrink-0"
                        >
                            <i className="fas fa-plus mr-1.5"></i>
                            Submit a Support Ticket
                        </Button>
                    </div>
                </div>
            )}

            {/* Sub-tab 2: Active Tickets & Messages */}
            {activeSubTab === 'tickets' && (
                <div className="space-y-4">
                    {/* Controls Bar */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl border border-gray-200 bg-white shadow-xs">
                        {/* Status Filter Buttons */}
                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                onClick={() => setTicketFilter('active')}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                    ticketFilter === 'active'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                Active Tickets ({ticketData?.counts?.active_total ?? 0})
                            </button>
                            <button
                                type="button"
                                onClick={() => setTicketFilter('closed')}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                    ticketFilter === 'closed'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                Resolved / Closed ({((ticketData?.counts?.resolved ?? 0) + (ticketData?.counts?.closed ?? 0))})
                            </button>
                            <button
                                type="button"
                                onClick={() => setTicketFilter('all')}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                                    ticketFilter === 'all'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                }`}
                            >
                                All Tickets ({ticketData?.counts?.total ?? 0})
                            </button>
                        </div>

                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            onClick={() => setIsNewTicketOpen(true)}
                        >
                            <i className="fas fa-plus mr-1.5"></i>
                            New Ticket
                        </Button>
                    </div>

                    {/* Ticket List */}
                    {isLoadingTickets ? (
                        <div className="py-16 text-center text-gray-500">
                            <i className="fas fa-circle-notch fa-spin text-2xl text-emerald-600 mb-2" />
                            <p className="text-sm">Loading tickets...</p>
                        </div>
                    ) : (ticketData?.data?.length ?? 0) === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center">
                            <i className="fas fa-ticket-alt text-4xl text-gray-300 mb-3" />
                            <h3 className="font-semibold text-gray-800 text-base">No tickets found</h3>
                            <p className="text-sm text-gray-500 mt-1 max-w-md mx-auto">
                                {ticketFilter === 'active'
                                    ? 'You have no active support tickets or ongoing inquiries.'
                                    : 'You have not submitted any tickets in this category.'}
                            </p>
                            <Button
                                type="button"
                                variant="primary"
                                size="sm"
                                onClick={() => setIsNewTicketOpen(true)}
                                className="mt-4"
                            >
                                <i className="fas fa-plus mr-1.5"></i>
                                Submit a Ticket
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {ticketData?.data.map((ticket: TicketSummary) => (
                                <div
                                    key={ticket.id}
                                    onClick={() => setSelectedTicketId(ticket.id)}
                                    className="group rounded-2xl border border-gray-200 bg-white p-5 shadow-xs hover:border-emerald-500 hover:shadow-md transition-all cursor-pointer"
                                >
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div className="space-y-1 min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                    {ticket.ticket_number}
                                                </span>
                                                <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-medium capitalize">
                                                    {ticket.category.replace(/_/g, ' ')}
                                                </span>
                                                {getPriorityBadge(ticket.priority)}
                                                {getStatusBadge(ticket.status)}
                                            </div>

                                            <h4 className="font-bold text-gray-900 text-base group-hover:text-emerald-700 transition-colors truncate">
                                                {ticket.subject}
                                            </h4>

                                            {ticket.latest_message_snippet && (
                                                <p className="text-xs text-gray-500 line-clamp-1">
                                                    <span className="font-semibold text-gray-600">Latest message: </span>
                                                    {ticket.latest_message_snippet}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center shrink-0 text-xs text-gray-500 gap-1 border-t sm:border-t-0 pt-2 sm:pt-0 border-gray-100">
                                            <span>{ticket.created_at_human}</span>
                                            <span className="inline-flex items-center text-emerald-600 font-semibold text-xs group-hover:translate-x-0.5 transition-transform">
                                                View Thread <i className="fas fa-arrow-right text-[10px] ml-1" />
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Modals */}
            <NewTicketModal
                open={isNewTicketOpen}
                onClose={() => setIsNewTicketOpen(false)}
                onSuccess={(ticket) => {
                    refetchTickets();
                    setSelectedTicketId(ticket.id);
                }}
            />

            <TicketDetailModal
                ticketId={selectedTicketId}
                open={!!selectedTicketId}
                onClose={() => setSelectedTicketId(null)}
                onTicketUpdated={() => refetchTickets()}
            />
        </div>
    );
}
