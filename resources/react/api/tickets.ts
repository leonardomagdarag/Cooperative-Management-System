import { api } from './client';

export interface TicketSummary {
    id: number;
    ticket_number: string;
    subject: string;
    category: string;
    priority: 'low' | 'medium' | 'high' | 'urgent';
    status: 'open' | 'in_progress' | 'resolved' | 'closed';
    user_id: number;
    requester_name: string;
    requester_email: string;
    requester_role: string;
    assigned_to: number | null;
    assignee_name: string | null;
    last_reply_at: string | null;
    created_at: string;
    created_at_human: string;
    latest_message_snippet: string | null;
}

export interface TicketAttachmentItem {
    id: number;
    file_name: string;
    file_size: number;
    mime_type: string;
    url: string;
}

export interface TicketMessageItem {
    id: number;
    user_id: number;
    author_name: string;
    author_role: string;
    is_staff: boolean;
    message: string;
    is_internal_note: boolean;
    created_at: string;
    created_at_human: string;
    attachments: TicketAttachmentItem[];
}

export interface TicketDetail extends TicketSummary {
    resolved_at: string | null;
    closed_at: string | null;
    messages: TicketMessageItem[];
}

export interface TicketCounts {
    total: number;
    open: number;
    in_progress: number;
    resolved: number;
    closed: number;
    active_total: number;
}

export interface TicketListResponse {
    data: TicketSummary[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    counts: TicketCounts;
    meta: {
        is_support: boolean;
        scope: string;
    };
}

export interface SupportOfficer {
    id: number;
    name: string;
    email: string;
    role: string;
}

export interface FaqItem {
    q: string;
    a: string;
    tags: string[];
}

export interface FaqCategory {
    id: string;
    label: string;
    icon: string;
    description: string;
    faqs: FaqItem[];
}

export interface HelpContent {
    header_description: string;
    callout_title: string;
    callout_description: string;
}

export interface FaqPayload {
    data: FaqCategory[];
    content: HelpContent;
}

export interface TicketQueryParams {
    scope?: 'all' | 'mine';
    status_group?: 'active' | 'closed';
    status?: string;
    priority?: string;
    category?: string;
    search?: string;
    page?: number;
    per_page?: number;
}

export async function getTickets(params: TicketQueryParams = {}): Promise<TicketListResponse> {
    const res = await api.get('/api/tickets', { params });
    return res.data;
}

export async function getTicket(id: number): Promise<{ data: TicketDetail; meta: { can_reply: boolean; can_update_status: boolean; can_assign: boolean } }> {
    const res = await api.get(`/api/tickets/${id}`);
    return res.data;
}

export async function createTicket(formData: FormData): Promise<{ message: string; data: TicketDetail }> {
    const res = await api.post('/api/tickets', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
}

export async function replyTicket(id: number, formData: FormData): Promise<{ message: string; data: TicketMessageItem; ticket_status: string }> {
    const res = await api.post(`/api/tickets/${id}/messages`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data;
}

export async function updateTicketStatus(id: number, status: 'open' | 'in_progress' | 'resolved' | 'closed'): Promise<{ message: string; data: any }> {
    const res = await api.patch(`/api/tickets/${id}/status`, { status });
    return res.data;
}

export async function assignTicket(id: number, assigned_to: number | null): Promise<{ message: string; data: any }> {
    const res = await api.patch(`/api/tickets/${id}/assign`, { assigned_to });
    return res.data;
}

export async function getSupportOfficers(): Promise<SupportOfficer[]> {
    const res = await api.get('/api/tickets/support-officers');
    return res.data.data;
}

export async function getFaqs(): Promise<FaqPayload> {
    const res = await api.get('/api/faqs');
    return res.data;
}

export async function updateFaqs(categories: FaqCategory[], content: HelpContent): Promise<{ message: string }> {
    const res = await api.put('/api/faqs', { categories, content });
    return res.data;
}
