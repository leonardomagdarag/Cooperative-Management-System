import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getAnnouncements, createAnnouncement, updateAnnouncement, deleteAnnouncement, Announcement } from '../../api/announcements';
import DatePicker from '../../components/DatePicker';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, announcementSchema, type FieldErrors } from '../../lib/validation';
import Button from '../../components/ui/Button';
import Badge from '../../components/ui/Badge';
import EmptyState from '../../components/ui/EmptyState';
import Pagination from '../../components/ui/Pagination';

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
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to delete announcement', 'error'),
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
                    <Button onClick={() => { setEditing(null); setShowModal(true); }} icon={<i className="fas fa-plus" />}>New Announcement</Button>
                )}
            </div>

            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-lg bg-gray-100" />)}
                </div>
            ) : announcements.length === 0 ? (
                <EmptyState icon="fa-bullhorn" title="No announcements yet" />
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
                                        <Badge>{a.type}</Badge>
                                    </div>
                                    <div className="prose prose-sm mt-1 text-gray-500 line-clamp-2">{a.content}</div>
                                    <div className="mt-2 flex items-center gap-4 text-xs text-gray-400">
                                        <span>{a.creator_name}</span>
                                        <span>{new Date(a.created_at).toLocaleDateString()}</span>
                                        <span>{a.visibility_label}</span>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <Button variant="ghost" size="sm" onClick={() => setViewing(a)} aria-label={`View ${a.title}`}><i className="fas fa-eye"></i></Button>
                                    {permissions?.can_update && (
                                        <Button variant="ghost" size="sm" onClick={() => { setEditing(a); setShowModal(true); }} aria-label={`Edit ${a.title}`}><i className="fas fa-edit"></i></Button>
                                    )}
                                    {permissions?.can_delete && (
                                        <Button variant="ghost" size="sm" onClick={() => { if (confirm('Delete this announcement?')) deleteMutation.mutate(a.id); }} aria-label={`Delete ${a.title}`} className="hover:bg-red-50 hover:text-red-600"><i className="fas fa-trash"></i></Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <Pagination page={page} lastPage={pagination.last_page} onChange={setPage} />
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
    const [selectedType, setSelectedType] = useState(announcement?.type || 'announcement');
    const [selectedVisibilities, setSelectedVisibilities] = useState<string[]>(
        announcement?.visibility?.length ? announcement.visibility : ['members']
    );
    const [title, setTitle] = useState(announcement?.title || '');
    const [content, setContent] = useState(announcement?.content || '');
    const [eventDate, setEventDate] = useState(announcement?.event_date || '');
    const [errors, setErrors] = useState<FieldErrors>({});
    const { addToast } = useToast();
    const { markDirty, requestClose, renderDialog } = useDiscardGuard();

    const setTitleD = (v: string) => { markDirty(); setTitle(v); };
    const setContentD = (v: string) => { markDirty(); setContent(v); };
    const setTypeD = (v: string) => { markDirty(); setSelectedType(v); };
    const setEventDateD = (v: string) => { markDirty(); setEventDate(v); };

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const errs = validate(announcementSchema, {
            title, content, type: selectedType, visibility: selectedVisibilities,
            event_date: eventDate,
        });
        if (Object.keys(errs).length > 0) {
            setErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setErrors({});
        setIsSaving(true);
        try {
            const fd = new FormData(e.currentTarget);
            fd.delete('visibility');
            selectedVisibilities.forEach(v => fd.append('visibility[]', v));

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

    const toggleVisibility = (value: string) => {
        markDirty();
        setSelectedVisibilities(prev =>
            prev.includes(value) ? prev.filter(v => v !== value) : [...prev, value]
        );
    };

    const requestCloseGuard = () => requestClose(onClose);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={requestCloseGuard}>
            <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-bold text-gray-900">{announcement ? 'Edit' : 'New'} Announcement</h2>
                        <button onClick={requestCloseGuard} className="text-gray-400 hover:text-gray-600"><i className="fas fa-times"></i></button>
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input name="title" value={title} onChange={e => setTitleD(e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.title ? 'border-red-400' : 'border-gray-200'}`} />
                            {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Content</label>
                            <textarea name="content" rows={4} value={content} onChange={e => setContentD(e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.content ? 'border-red-400' : 'border-gray-200'}`} />
                            {errors.content && <p className="mt-1 text-xs text-red-600">{errors.content}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                <select
                                    name="type"
                                    value={selectedType}
                                    onChange={e => setTypeD(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                                >
                                    <option value="announcement">Announcement</option>
                                    <option value="news">News</option>
                                    <option value="event">Event</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Visibility</label>
                                <div className="space-y-1.5 rounded-lg border border-gray-200 p-2.5">
                                    {(['everyone', 'members', 'officers', 'public'] as const).map(v => (
                                        <label key={v} className="flex items-center gap-2 text-sm cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={selectedVisibilities.includes(v)}
                                                onChange={() => toggleVisibility(v)}
                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span className="text-gray-700 capitalize">{v === 'everyone' ? 'Everyone' : v === 'members' ? 'Members' : v === 'officers' ? 'Officers' : 'Public'}</span>
                                        </label>
                                    ))}
                                </div>
                                {errors.visibility && <p className="mt-1 text-xs text-red-600">{errors.visibility}</p>}
                            </div>
                        </div>
                        {selectedType === 'event' && (
                            <div className="space-y-4 rounded-lg border border-purple-200 bg-purple-50 p-4">
                                <p className="text-sm font-semibold text-purple-800"><i className="fas fa-calendar mr-1"></i> Event Details</p>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Event Date</label>
                                        <DatePicker value={eventDate} onChange={setEventDateD} className={`w-full rounded-lg border px-3 py-2 text-sm ${errors.event_date ? 'border-red-400' : 'border-gray-200'}`} />
                                        <input type="hidden" name="event_date" value={eventDate} />
                                        {errors.event_date && <p className="mt-1 text-xs text-red-600">{errors.event_date}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Event Time</label>
                                        <input type="time" name="event_time" defaultValue={announcement?.event_time || ''} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Event Location</label>
                                    <input type="text" name="event_location" defaultValue={announcement?.event_location || ''} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Venue or address" />
                                </div>
                            </div>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Image</label>
                            <input type="file" name="image" accept="image/*" className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                        <div className="flex justify-end gap-3">
                            <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" disabled={isSaving} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium disabled:opacity-50">
                                {isSaving ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            {renderDialog()}
        </div>
    );
}
