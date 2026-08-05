import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getAnnouncements, createAnnouncement, updateAnnouncement, deleteAnnouncement, Announcement } from '../../api/announcements';
import { useToast } from '../../contexts/ToastContext';

export default function AnnouncementsPage() {
    const [page, setPage] = useState(1);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<Announcement | null>(null);
    const [viewing, setViewing] = useState<Announcement | null>(null);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['announcements', page],
        queryFn: () => getAnnouncements({ page, per_page: 10 }),
    });

    const deleteMutation = useMutation({
        mutationFn: deleteAnnouncement,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['announcements'] });
            addToast('Announcement deleted', 'success');
        },
    });

    const announcements: Announcement[] = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const permissions = data?.data?.permissions;

    const getTypeIcon = (type: string) => {
        if (type === 'news') return 'fas fa-newspaper text-blue-500';
        if (type === 'event') return 'fas fa-calendar text-purple-500';
        return 'fas fa-bullhorn text-amber-500';
    };

    return (
        <div className="max-w-7xl mx-auto p-6">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Announcements</h1>
                    <p className="text-sm text-gray-500">Stay updated with the latest news and events</p>
                </div>
                {permissions?.can_create && (
                    <button onClick={() => { setEditing(null); setShowModal(true); }} className="bg-blue-600 hover:bg-blue-700 rounded-lg text-white px-4 py-2 text-sm font-medium">
                        <i className="fas fa-plus mr-1"></i> New Announcement
                    </button>
                )}
            </div>

            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-lg bg-gray-100" />)}
                </div>
            ) : announcements.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                    <i className="fas fa-bullhorn text-4xl text-gray-300"></i>
                    <p className="mt-4 text-sm text-gray-500">No announcements yet</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {announcements.map(a => (
                        <div key={a.id} className="rounded-lg border border-gray-200 bg-white p-4 hover:border-gray-300 transition-colors">
                            <div className="flex items-start gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                                    <i className={getTypeIcon(a.type)}></i>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-semibold text-gray-900">{a.title}</h3>
                                        <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-500">{a.type}</span>
                                    </div>
                                    <div className="prose prose-sm mt-1 text-gray-500 line-clamp-2">{a.content}</div>
                                    <div className="mt-2 flex items-center gap-4 text-xs text-gray-400">
                                        <span>{a.creator_name}</span>
                                        <span>{new Date(a.created_at).toLocaleDateString()}</span>
                                        <span>{a.visibility_label}</span>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <button onClick={() => setViewing(a)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i className="fas fa-eye"></i></button>
                                    {permissions?.can_update && (
                                        <button onClick={() => { setEditing(a); setShowModal(true); }} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i className="fas fa-edit"></i></button>
                                    )}
                                    {permissions?.can_delete && (
                                        <button onClick={() => { if (confirm('Delete this announcement?')) deleteMutation.mutate(a.id); }} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"><i className="fas fa-trash"></i></button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                    <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                </div>
            )}

            {/* View Modal */}
            {viewing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setViewing(null)}>
                    <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-lg font-bold text-gray-900">{viewing.title}</h2>
                                <button onClick={() => setViewing(null)} className="text-gray-400 hover:text-gray-600"><i className="fas fa-times"></i></button>
                            </div>
                            {viewing.image_url && <img src={viewing.image_url} alt="" className="mb-4 w-full rounded-lg object-cover" />}
                            <div className="prose prose-sm text-gray-600 whitespace-pre-wrap">{viewing.content}</div>
                            <div className="mt-4 flex items-center gap-4 text-xs text-gray-400">
                                <span>By {viewing.creator_name}</span>
                                <span>{new Date(viewing.created_at).toLocaleDateString()}</span>
                                <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100">{viewing.visibility_label}</span>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Create/Edit Modal */}
            {showModal && (
                <AnnouncementFormModal
                    announcement={editing}
                    onClose={() => { setShowModal(false); setEditing(null); }}
                    onSuccess={() => {
                        queryClient.invalidateQueries({ queryKey: ['announcements'] });
                        setShowModal(false);
                        setEditing(null);
                        addToast(editing ? 'Announcement updated' : 'Announcement created', 'success');
                    }}
                />
            )}
        </div>
    );
}

function AnnouncementFormModal({ announcement, onClose, onSuccess }: {
    announcement: Announcement | null;
    onClose: () => void;
    onSuccess: () => void;
}) {
    const [isSaving, setIsSaving] = useState(false);
    const { addToast } = useToast();

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setIsSaving(true);
        try {
            const fd = new FormData(e.currentTarget);
            fd.set('visibility[]', fd.get('visibility') as string);

            const visValue = fd.get('visibility') as string;
            fd.delete('visibility');
            fd.append('visibility[]', visValue || 'members');

            if (announcement) {
                fd.append('_method', 'PUT');
                await updateAnnouncement(announcement.id, fd);
            } else {
                await createAnnouncement(fd);
            }
            onSuccess();
        } catch (err: any) {
            addToast(err.response?.data?.message || 'Failed to save', 'error');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-bold text-gray-900">{announcement ? 'Edit' : 'New'} Announcement</h2>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><i className="fas fa-times"></i></button>
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input name="title" defaultValue={announcement?.title} required className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Content</label>
                            <textarea name="content" rows={4} defaultValue={announcement?.content} required className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                <select name="type" defaultValue={announcement?.type || 'announcement'} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="announcement">Announcement</option>
                                    <option value="news">News</option>
                                    <option value="event">Event</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Visibility</label>
                                <select name="visibility" defaultValue={announcement?.visibility?.[0] || 'members'} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="everyone">Everyone</option>
                                    <option value="members">Members</option>
                                    <option value="officers">Officers</option>
                                    <option value="public">Public</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Image</label>
                            <input type="file" name="image" accept="image/*" className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                        <div className="flex justify-end gap-3">
                            <button type="button" onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" disabled={isSaving} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium disabled:opacity-50">
                                {isSaving ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
