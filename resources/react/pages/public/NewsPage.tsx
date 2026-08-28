import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function NewsPage() {
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<any>(null);

    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const newsContent = pc?.data?.form?.news || {};
    const eventsContent = pc?.data?.form?.events || {};

    const { data, isLoading } = useQuery({
        queryKey: ['announcements-news', page],
        queryFn: () => api.get('/api/announcements/public', { params: { page, per_page: 9 } }),
    });

    const announcements = data?.data?.data || [];
    const pagination = data?.data?.pagination;

    const iconMap: Record<string, string> = { Achievement: 'trophy', Update: 'mobile-alt' };
    const categoryIcons: Record<string, string> = {
        'Workshop': 'book', 'Annual Meeting': 'handshake', 'Community Service': 'hands-helping',
        'Celebration': 'gift', 'Campaign': 'coins', 'Training': 'chalkboard-teacher',
        'Seminar': 'microphone', 'General Assembly': 'users',
    };
    const bgColors = ['bg-slate-700', 'bg-emerald-700', 'bg-cyan-700', 'bg-indigo-700'];

    const events = announcements.filter((a: any) => a.event_date);
    const upcoming = events.filter((e: any) => e.is_upcoming);
    const past = events.filter((e: any) => !e.is_upcoming);

    return (
        <div className="public-page">
            <section className="relative overflow-hidden bg-slate-950 px-4 pb-16 pt-32">
                <div className="relative mx-auto max-w-4xl text-center">
                    <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                        <i className="fas fa-newspaper text-xs"></i> Cooperative updates
                    </span>
                    <h1 className="text-4xl md:text-6xl font-extrabold text-white mb-6">News & Events</h1>
                    <p className="text-xl text-slate-300 leading-relaxed">
                        {newsContent.hero_description || 'Stay updated with our latest news and events'}
                    </p>
                </div>
            </section>

            <section className="public-section bg-white">
                <div className="public-shell">
                    <div className="mb-12">
                        <div className="flex items-center gap-4 mb-6">
                            <div className="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                                <i className="fas fa-newspaper text-white text-xl"></i>
                            </div>
                            <h2 className="public-heading text-3xl md:text-5xl">Latest News & Announcements</h2>
                        </div>
                        <div className="w-28 h-1 bg-emerald-700 rounded-full"></div>
                    </div>

                    {isLoading ? (
                        <div className="space-y-4">{[...Array(3)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-2xl bg-slate-100" />)}</div>
                    ) : announcements.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                            No news items are available yet.
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {announcements.map((a: any) => {
                                const category = a.type || a.category;
                                const isAchievement = category === 'Achievement';
                                const isUpdate = category === 'Update';
                                const bannerBg = a.image_url ? 'bg-gray-200' : isAchievement ? 'bg-emerald-700' : isUpdate ? 'bg-blue-700' : 'bg-slate-700';
                                const badgeBg = isAchievement ? 'bg-emerald-100 text-emerald-700' : isUpdate ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700';

                                return (
                                    <div key={a.id} className="public-card public-card-hover overflow-hidden md:flex cursor-pointer" onClick={() => setSelected(a)}>
                                        <div className={`md:w-1/4 h-48 md:h-auto shrink-0 relative overflow-hidden flex items-center justify-center ${bannerBg}`}>
                                            {a.image_url ? (
                                                <img src={a.image_url} alt={a.title} className="w-full h-full object-cover" />
                                            ) : (
                                                <i className={`fas fa-${iconMap[category] || 'bullhorn'} text-5xl text-white opacity-30`}></i>
                                            )}
                                        </div>
                                        <div className="p-8 md:w-3/4">
                                            <div className="flex items-center gap-3 mb-4">
                                                <span className={`px-4 py-1 rounded-full text-sm font-semibold ${badgeBg}`}>
                                                    {category || a.type_label || 'News'}
                                                </span>
                                                <span className="text-sm text-gray-500">
                                                    <i className="fas fa-calendar mr-2"></i>{new Date(a.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                                                </span>
                                            </div>
                                            <h3 className="text-2xl font-bold text-gray-900 mb-3">{a.title}</h3>
                                            <p className="text-gray-600 leading-relaxed mb-4">{a.content}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-10 flex justify-center gap-2">
                            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-slate-200 px-4 py-2 text-sm disabled:opacity-50">Previous</button>
                            <span className="flex items-center px-4 text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                            <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-slate-200 px-4 py-2 text-sm disabled:opacity-50">Next</button>
                        </div>
                    )}
                </div>
            </section>

            <section className="public-section public-section-soft">
                <div className="public-shell">
                    <div className="mb-12">
                        <div className="flex items-center gap-4 mb-6">
                            <div className="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                                <i className="fas fa-calendar-check text-white text-xl"></i>
                            </div>
                            <h2 className="public-heading text-3xl md:text-5xl">Events</h2>
                        </div>
                        <div className="w-24 h-1 bg-emerald-700 rounded"></div>
                        <p className="mt-6 max-w-4xl text-base text-gray-600">
                            {eventsContent.events || 'Join us at our upcoming events and activities.'}
                        </p>
                    </div>

                    {events.length > 0 ? (
                        <>
                            {upcoming.length > 0 && (
                                <>
                                    <h3 className="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
                                        <span className="inline-block w-3 h-3 rounded-full bg-emerald-500"></span> Upcoming
                                    </h3>
                                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                                        {upcoming.map((e: any, i: number) => {
                                            const bg = bgColors[i % bgColors.length];
                                            return (
                                                <div key={e.id} className="public-card public-card-hover overflow-hidden flex flex-col">
                                                    <div className={`h-48 shrink-0 relative overflow-hidden ${e.image_url ? 'bg-gray-200' : bg}`}>
                                                        {e.image_url ? (
                                                            <img src={e.image_url} alt={e.title} className="w-full h-full object-cover" />
                                                        ) : (
                                                            <div className="absolute inset-0 flex items-center justify-center">
                                                                <i className={`fas fa-${categoryIcons[e.category] || 'calendar-alt'} text-7xl text-white opacity-20`}></i>
                                                            </div>
                                                        )}
                                                        <div className="absolute top-4 left-4 bg-emerald-600 text-white px-3 py-1.5 rounded-lg shadow text-xs font-bold uppercase">Upcoming</div>
                                                        <div className="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-sm font-bold text-slate-700">
                                                            {new Date(e.event_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                                                        </div>
                                                    </div>
                                                    <div className="p-6 flex flex-col flex-1">
                                                        {e.category && (
                                                            <div className="flex items-center gap-2 mb-3">
                                                                <i className={`fas fa-${categoryIcons[e.category] || 'tag'} text-emerald-700 text-sm`}></i>
                                                                <span className="text-sm font-semibold text-emerald-700">{e.category}</span>
                                                            </div>
                                                        )}
                                                        <h3 className="text-lg font-bold text-slate-800 mb-3">{e.title}</h3>
                                                        <p className="text-gray-600 mb-4 leading-relaxed text-sm line-clamp-3 flex-1">{e.content}</p>
                                                        <div className="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                                            {e.event_time && <div className="flex items-center gap-1.5"><i className="fas fa-clock"></i><span>{e.event_time}</span></div>}
                                                            {e.event_location && <div className="flex items-center gap-1.5"><i className="fas fa-map-marker-alt"></i><span>{e.event_location}</span></div>}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </>
                            )}

                            {past.length > 0 && (
                                <>
                                    <h3 className="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
                                        <span className="inline-block w-3 h-3 rounded-full bg-gray-400"></span> Past Events
                                    </h3>
                                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        {past.map((e: any) => (
                                            <div key={e.id} className="public-card public-card-hover overflow-hidden opacity-90 flex flex-col">
                                                <div className={`h-48 shrink-0 relative overflow-hidden ${e.image_url ? 'bg-gray-300' : 'bg-gray-400'}`}>
                                                    {e.image_url ? (
                                                        <img src={e.image_url} alt={e.title} className="w-full h-full object-cover opacity-80" />
                                                    ) : (
                                                        <div className="absolute inset-0 flex items-center justify-center">
                                                            <i className={`fas fa-${categoryIcons[e.category] || 'calendar-check'} text-7xl text-white opacity-20`}></i>
                                                        </div>
                                                    )}
                                                    <div className="absolute top-4 left-4 bg-gray-600 text-white px-3 py-1.5 rounded-lg shadow text-xs font-bold uppercase">Completed</div>
                                                    <div className="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-sm font-bold text-gray-600">
                                                        {new Date(e.event_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                                                    </div>
                                                </div>
                                                <div className="p-6 flex flex-col flex-1">
                                                    {e.category && (
                                                        <div className="flex items-center gap-2 mb-3">
                                                            <i className={`fas fa-${categoryIcons[e.category] || 'tag'} text-gray-500 text-sm`}></i>
                                                            <span className="text-sm font-semibold text-gray-600">{e.category}</span>
                                                        </div>
                                                    )}
                                                    <h3 className="text-lg font-bold text-slate-800 mb-3">{e.title}</h3>
                                                    <p className="text-gray-600 mb-4 leading-relaxed text-sm line-clamp-3 flex-1">{e.content}</p>
                                                    <div className="flex items-center gap-2 text-sm text-gray-500">
                                                        <i className="fas fa-check-circle text-emerald-500"></i>
                                                        <span>Event Completed</span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}
                        </>
                    ) : (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center text-gray-500">
                            <i className="fas fa-calendar-alt text-4xl text-gray-300 mb-4 block"></i>
                            No events are scheduled yet. Check back soon!
                        </div>
                    )}
                </div>
            </section>

            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" onClick={() => setSelected(null)}>
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onClick={e => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{selected.type}</span>
                            <button onClick={() => setSelected(null)} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><i className="fas fa-times"></i></button>
                        </div>
                        {selected.image_url && <img src={selected.image_url} alt="" className="mb-4 w-full rounded-xl object-cover" />}
                        <h2 className="mb-3 text-2xl font-bold text-slate-900">{selected.title}</h2>
                        <p className="mb-2 text-sm text-slate-400">By {selected.creator_name} · {new Date(selected.created_at).toLocaleDateString()}</p>
                        <p className="whitespace-pre-wrap leading-relaxed text-slate-600">{selected.content}</p>
                    </div>
                </div>
            )}
        </div>
    );
}
