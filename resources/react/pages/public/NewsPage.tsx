import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function NewsPage() {
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<any>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['announcements-news', page],
        queryFn: () => api.get('/api/announcements/public', { params: { page, per_page: 9 } }),
    });

    const announcements = data?.data?.data || [];
    const pagination = data?.data?.pagination;

    return (
        <>
            <section className="relative overflow-hidden bg-slate-950 pb-20 pt-32">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-950 to-emerald-950/30"></div>
                <div className="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                        <i className="fas fa-newspaper"></i> Latest Updates
                    </span>
                    <h1 className="mb-6 text-5xl font-extrabold text-white md:text-6xl">News & Announcements</h1>
                    <p className="text-lg text-slate-300">Stay updated with our latest news and events</p>
                </div>
            </section>

            <section className="py-16">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {isLoading ? (
                        <div className="grid gap-6 md:grid-cols-3">
                            {[...Array(6)].map((_, i) => <div key={i} className="h-72 animate-pulse rounded-2xl bg-slate-100" />)}
                        </div>
                    ) : announcements.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center">
                            <i className="fas fa-newspaper text-5xl text-slate-300"></i>
                            <p className="mt-4 text-slate-500">No news yet. Check back soon!</p>
                        </div>
                    ) : (
                        <div className="grid gap-6 md:grid-cols-3">
                            {announcements.map((a: any) => (
                                <button key={a.id} onClick={() => setSelected(a)} className="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                                    {a.image_url ? (
                                        <img src={a.image_url} alt={a.title} className="h-48 w-full object-cover" />
                                    ) : (
                                        <div className="flex h-48 items-center justify-center bg-gradient-to-br from-emerald-500 to-emerald-700">
                                            <i className="fas fa-newspaper text-5xl text-white/20"></i>
                                        </div>
                                    )}
                                    <div className="p-6">
                                        <div className="mb-3 flex items-center justify-between">
                                            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{a.type}</span>
                                            <span className="text-xs text-slate-400">{new Date(a.created_at).toLocaleDateString()}</span>
                                        </div>
                                        <h3 className="mb-2 text-lg font-bold text-slate-900 line-clamp-2 group-hover:text-emerald-600">{a.title}</h3>
                                        <p className="text-sm text-slate-500 line-clamp-2">{a.content}</p>
                                    </div>
                                </button>
                            ))}
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
        </>
    );
}
