import { useState, useRef, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    getTicket,
    replyTicket,
    updateTicketStatus,
    assignTicket,
    getSupportOfficers,
    type TicketDetail,
} from '../../api/tickets';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import Modal from '../ui/Modal';
import ModalHeader from '../ui/ModalHeader';
import Button from '../ui/Button';
import Badge from '../ui/Badge';

interface TicketDetailModalProps {
    ticketId: number | null;
    open: boolean;
    onClose: () => void;
    onTicketUpdated?: () => void;
}

export default function TicketDetailModal({
    ticketId,
    open,
    onClose,
    onTicketUpdated,
}: TicketDetailModalProps) {
    const { user } = useAuth();
    const { addToast } = useToast();
    const queryClient = useQueryClient();

    const isSupport = ['admin', 'manager', 'accountant'].includes(user?.primary_role || '');

    const [replyText, setReplyText] = useState('');
    const [isInternal, setIsInternal] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [previews, setPreviews] = useState<string[]>([]);
    const [lightboxImage, setLightboxImage] = useState<string | null>(null);

    const messagesEndRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data: ticketResponse, isLoading, refetch } = useQuery({
        queryKey: ['ticket', ticketId],
        queryFn: () => getTicket(ticketId!),
        enabled: !!ticketId && open,
    });

    const { data: supportOfficers } = useQuery({
        queryKey: ['support-officers'],
        queryFn: getSupportOfficers,
        enabled: open && isSupport,
    });

    const ticket = ticketResponse?.data;
    const meta = ticketResponse?.meta;

    useEffect(() => {
        if (ticket?.messages) {
            messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [ticket?.messages?.length]);

    const replyMutation = useMutation({
        mutationFn: async () => {
            if (!ticketId) return;
            const fd = new FormData();
            fd.append('message', replyText.trim());
            if (isInternal) {
                fd.append('is_internal_note', '1');
            }
            selectedFiles.forEach(file => {
                fd.append('attachments[]', file);
            });
            return replyTicket(ticketId, fd);
        },
        onSuccess: () => {
            setReplyText('');
            setIsInternal(false);
            previews.forEach(url => URL.revokeObjectURL(url));
            setPreviews([]);
            setSelectedFiles([]);
            refetch();
            queryClient.invalidateQueries({ queryKey: ['tickets'] });
            if (onTicketUpdated) onTicketUpdated();
            addToast('Message posted successfully.', 'success');
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to post reply.', 'error');
        },
    });

    const statusMutation = useMutation({
        mutationFn: (newStatus: 'open' | 'in_progress' | 'resolved' | 'closed') => {
            return updateTicketStatus(ticketId!, newStatus);
        },
        onSuccess: (_, newStatus) => {
            refetch();
            queryClient.invalidateQueries({ queryKey: ['tickets'] });
            if (onTicketUpdated) onTicketUpdated();
            addToast(`Ticket status updated to ${newStatus}.`, 'success');
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to update status.', 'error');
        },
    });

    const assignMutation = useMutation({
        mutationFn: (officerId: number | null) => {
            return assignTicket(ticketId!, officerId);
        },
        onSuccess: () => {
            refetch();
            queryClient.invalidateQueries({ queryKey: ['tickets'] });
            if (onTicketUpdated) onTicketUpdated();
            addToast('Assigned officer updated.', 'success');
        },
        onError: (err: any) => {
            addToast(err.response?.data?.message || 'Failed to assign officer.', 'error');
        },
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files) return;
        const newFiles = Array.from(e.target.files);
        if (selectedFiles.length + newFiles.length > 5) {
            addToast('Max 5 images allowed per reply.', 'error');
            return;
        }

        const validImages: File[] = [];
        const newUrls: string[] = [];

        for (const file of newFiles) {
            if (!file.type.startsWith('image/')) {
                addToast(`${file.name} is not an image file.`, 'error');
                continue;
            }
            if (file.size > 5 * 1024 * 1024) {
                addToast(`${file.name} exceeds 5MB size limit.`, 'error');
                continue;
            }
            validImages.push(file);
            newUrls.push(URL.createObjectURL(file));
        }

        setSelectedFiles(prev => [...prev, ...validImages]);
        setPreviews(prev => [...prev, ...newUrls]);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const removeFile = (index: number) => {
        URL.revokeObjectURL(previews[index]);
        setSelectedFiles(prev => prev.filter((_, i) => i !== index));
        setPreviews(prev => prev.filter((_, i) => i !== index));
    };

    const handleReplySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!replyText.trim()) return;
        replyMutation.mutate();
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
                return <Badge>{status}</Badge>;
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
                return <Badge>{priority}</Badge>;
        }
    };

    return (
        <>
            <Modal
                open={open}
                onClose={onClose}
                title={ticket?.ticket_number || 'Ticket Details'}
                size="xl"
                header={
                    <ModalHeader
                        title={ticket ? `${ticket.ticket_number}: ${ticket.subject}` : 'Ticket Details'}
                        subtitle={ticket ? `Submitted by ${ticket.requester_name} (${ticket.requester_role}) • ${ticket.created_at_human}` : 'Loading ticket details...'}
                        icon="fas fa-ticket-alt"
                        onClose={onClose}
                    />
                }
            >
                {isLoading || !ticket ? (
                    <div className="py-16 text-center text-gray-500">
                        <i className="fas fa-circle-notch fa-spin text-3xl text-emerald-600 mb-3" />
                        <p className="text-sm">Loading ticket conversation...</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {/* Header Info Bar */}
                        <div className="flex flex-wrap items-center justify-between gap-3 p-3.5 rounded-xl border border-gray-200 bg-gray-50/70 text-xs">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-semibold text-gray-500">Status:</span>
                                {getStatusBadge(ticket.status)}
                                <span className="text-gray-300">|</span>
                                <span className="font-semibold text-gray-500">Priority:</span>
                                {getPriorityBadge(ticket.priority)}
                                <span className="text-gray-300">|</span>
                                <span className="font-semibold text-gray-500">Category:</span>
                                <span className="capitalize text-gray-800 font-medium">
                                    {ticket.category.replace(/_/g, ' ')}
                                </span>
                            </div>

                            {/* Staff Controls: Assignee & Status */}
                            {isSupport && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="flex items-center gap-1.5">
                                        <span className="font-semibold text-gray-500">Assignee:</span>
                                        <select
                                            value={ticket.assigned_to || ''}
                                            onChange={e => {
                                                const val = e.target.value ? parseInt(e.target.value, 10) : null;
                                                assignMutation.mutate(val);
                                            }}
                                            disabled={assignMutation.isPending}
                                            className="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-800 focus:outline-none focus:border-emerald-500"
                                        >
                                            <option value="">Unassigned</option>
                                            {supportOfficers?.map(off => (
                                                <option key={off.id} value={off.id}>{off.name} ({off.role})</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex items-center gap-1">
                                        {ticket.status !== 'in_progress' && ticket.status !== 'closed' && (
                                            <button
                                                type="button"
                                                onClick={() => statusMutation.mutate('in_progress')}
                                                disabled={statusMutation.isPending}
                                                className="px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition-colors font-medium"
                                                title="Mark as In Progress"
                                            >
                                                In Progress
                                            </button>
                                        )}
                                        {ticket.status !== 'resolved' && (
                                            <button
                                                type="button"
                                                onClick={() => statusMutation.mutate('resolved')}
                                                disabled={statusMutation.isPending}
                                                className="px-2 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors font-medium"
                                                title="Mark as Resolved"
                                            >
                                                Resolve
                                            </button>
                                        )}
                                        {ticket.status !== 'closed' ? (
                                            <button
                                                type="button"
                                                onClick={() => statusMutation.mutate('closed')}
                                                disabled={statusMutation.isPending}
                                                className="px-2 py-1 rounded bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200 transition-colors font-medium"
                                                title="Close Ticket"
                                            >
                                                Close
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() => statusMutation.mutate('open')}
                                                disabled={statusMutation.isPending}
                                                className="px-2 py-1 rounded bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors font-medium"
                                                title="Reopen Ticket"
                                            >
                                                Reopen
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Message Timeline */}
                        <div className="max-h-[50vh] overflow-y-auto space-y-4 px-1 py-2">
                            {ticket.messages.map((msg, idx) => {
                                const isAuthorMe = msg.user_id === user?.id;

                                return (
                                    <div
                                        key={msg.id || idx}
                                        className={`flex gap-3 ${isAuthorMe ? 'flex-row-reverse' : 'flex-row'}`}
                                    >
                                        {/* Avatar */}
                                        <div
                                            className={`h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 text-white shadow-xs ${
                                                msg.is_staff
                                                    ? 'bg-linear-to-r from-emerald-600 to-teal-600'
                                                    : 'bg-linear-to-r from-cyan-600 to-blue-600'
                                            }`}
                                        >
                                            {msg.author_name.charAt(0).toUpperCase()}
                                        </div>

                                        {/* Bubble */}
                                        <div
                                            className={`max-w-[82%] rounded-2xl p-4 text-sm shadow-xs ${
                                                msg.is_internal_note
                                                    ? 'border border-amber-200 bg-amber-50/80 text-amber-950'
                                                    : isAuthorMe
                                                    ? 'bg-emerald-600 text-white rounded-tr-none'
                                                    : 'border border-gray-200 bg-white text-gray-900 rounded-tl-none'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between gap-3 mb-1 text-xs">
                                                <div className="flex items-center gap-1.5">
                                                    <span className={`font-semibold ${isAuthorMe ? 'text-white' : 'text-gray-900'}`}>
                                                        {msg.author_name}
                                                    </span>
                                                    {msg.is_staff && (
                                                        <span className={`text-[10px] px-1.5 py-0.2 rounded uppercase font-bold tracking-wider ${isAuthorMe ? 'bg-emerald-700 text-emerald-100' : 'bg-emerald-100 text-emerald-800'}`}>
                                                            Staff
                                                        </span>
                                                    )}
                                                    {msg.is_internal_note && (
                                                        <span className="text-[10px] px-1.5 py-0.2 rounded uppercase font-bold tracking-wider bg-amber-200 text-amber-900">
                                                            Internal Note
                                                        </span>
                                                    )}
                                                </div>
                                                <span className={`text-[11px] ${isAuthorMe ? 'text-emerald-100' : 'text-gray-400'}`}>
                                                    {msg.created_at_human}
                                                </span>
                                            </div>

                                            <p className="whitespace-pre-wrap leading-relaxed">
                                                {msg.message}
                                            </p>

                                            {/* Attached Images */}
                                            {msg.attachments && msg.attachments.length > 0 && (
                                                <div className="mt-3 pt-3 border-t border-black/10 flex flex-wrap gap-2">
                                                    {msg.attachments.map(att => (
                                                        <button
                                                            key={att.id}
                                                            type="button"
                                                            onClick={() => setLightboxImage(att.url)}
                                                            className="group/att relative rounded-lg overflow-hidden border border-black/10 bg-black/5 hover:opacity-90 transition-all text-left"
                                                            title="Click to view full image"
                                                        >
                                                            <img
                                                                src={att.url}
                                                                alt={att.file_name}
                                                                className="h-20 w-24 object-cover"
                                                            />
                                                            <div className="absolute inset-0 bg-black/40 opacity-0 group-hover/att:opacity-100 flex items-center justify-center text-white text-xs transition-opacity">
                                                                <i className="fas fa-search-plus text-sm mr-1"></i> Zoom
                                                            </div>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Reply Form */}
                        {ticket.status === 'closed' && !isSupport ? (
                            <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-500">
                                <i className="fas fa-lock mr-1.5 text-gray-400" />
                                This support ticket has been closed. If you have further inquiries, please submit a new ticket.
                            </div>
                        ) : (
                            <form onSubmit={handleReplySubmit} className="space-y-3 pt-3 border-t border-gray-200">
                                {isSupport && (
                                    <label className="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={isInternal}
                                            onChange={e => setIsInternal(e.target.checked)}
                                            className="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                        />
                                        <span>Internal Staff Note (not visible to the ticket creator)</span>
                                    </label>
                                )}

                                <div>
                                    <textarea
                                        value={replyText}
                                        onChange={e => setReplyText(e.target.value)}
                                        rows={3}
                                        placeholder={isInternal ? 'Type internal note for support officers...' : 'Write your reply or support follow-up...'}
                                        required
                                        className="w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    />
                                </div>

                                {/* Attachment previews */}
                                {previews.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {previews.map((src, idx) => (
                                            <div key={idx} className="relative group rounded-lg overflow-hidden border border-gray-200 bg-gray-100 h-16 w-20 shadow-xs">
                                                <img src={src} alt="Upload preview" className="w-full h-full object-cover" />
                                                <button
                                                    type="button"
                                                    onClick={() => removeFile(idx)}
                                                    className="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px] shadow-sm hover:bg-red-700"
                                                >
                                                    <i className="fas fa-times" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => fileInputRef.current?.click()}
                                            disabled={selectedFiles.length >= 5 || replyMutation.isPending}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 hover:border-emerald-500 disabled:opacity-50 transition-colors"
                                        >
                                            <i className="fas fa-paperclip text-gray-500 text-xs" />
                                            <span>Attach Image ({selectedFiles.length}/5)</span>
                                        </button>
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept="image/*"
                                            multiple
                                            onChange={handleFileChange}
                                            className="hidden"
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        loading={replyMutation.isPending}
                                        disabled={!replyText.trim()}
                                    >
                                        <i className="fas fa-paper-plane mr-1.5 text-xs" />
                                        {isInternal ? 'Add Note' : 'Send Reply'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}
            </Modal>

            {/* Lightbox Zoom Modal */}
            {lightboxImage && (
                <div
                    className="fixed inset-0 z-60 bg-black/80 flex items-center justify-center p-4 backdrop-blur-sm"
                    onClick={() => setLightboxImage(null)}
                >
                    <div className="relative max-w-4xl max-h-[90vh] overflow-hidden rounded-xl bg-black" onClick={e => e.stopPropagation()}>
                        <button
                            type="button"
                            onClick={() => setLightboxImage(null)}
                            className="absolute top-3 right-3 h-8 w-8 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-black/90 transition-colors z-10"
                        >
                            <i className="fas fa-times" />
                        </button>
                        <img
                            src={lightboxImage}
                            alt="Attachment Full View"
                            className="max-h-[85vh] max-w-full object-contain mx-auto"
                        />
                    </div>
                </div>
            )}
        </>
    );
}
