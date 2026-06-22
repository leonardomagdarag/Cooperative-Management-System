import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '../../api/client';

export default function AboutPage() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const about = data?.data?.form?.about || {};
    const board = data?.data?.form?.board || {};
    const officers = data?.data?.form?.officers || {};
    const boardCards = (board.cards || []).filter((c: any) => c.name || c.position);
    const officerCards = (officers.cards || []).filter((c: any) => c.name || c.position);

    return (
        <>
            {/* Hero */}
            <section className="relative overflow-hidden bg-slate-950 pb-20 pt-32">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-950 to-emerald-950/30"></div>
                <div className="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                        <i className="fas fa-leaf"></i> Our Cooperative Story
                    </span>
                    <h1 className="mb-6 text-5xl font-extrabold text-white md:text-6xl">About Us</h1>
                    <p className="text-lg text-slate-300 md:text-xl">{about.about1 || 'Settlers Credit Cooperative is dedicated to serving our community.'}</p>
                </div>
            </section>

            {/* History */}
            <section className="py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid items-center gap-14 md:grid-cols-2">
                        <div>
                            <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <i className="fas fa-history"></i> Our Journey
                            </span>
                            <h2 className="mb-4 text-4xl font-extrabold text-slate-900 md:text-5xl">Our History</h2>
                            <div className="mb-6 h-1 w-12 rounded-full bg-emerald-600"></div>
                            <div className="space-y-5 text-slate-600">
                                <p>{about.about2 || ''}</p>
                                <div>{about.history || ''}</div>
                            </div>
                        </div>
                        <div className="relative">
                            <div className="absolute -inset-4 rounded-3xl bg-emerald-50"></div>
                            <img src="/images/hero_banner.jpg" alt="Cooperative" className="relative h-80 w-full rounded-2xl object-cover shadow-xl md:h-[420px]" />
                        </div>
                    </div>
                </div>
            </section>

            {/* Mission, Vision, Values */}
            <section className="bg-slate-950 py-24">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-14 text-center">
                        <span className="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">
                            <i className="fas fa-compass"></i> Our Foundation
                        </span>
                        <h2 className="mt-2 text-3xl font-bold text-white md:text-4xl">What Guides Us</h2>
                    </div>
                    <div className="grid gap-6 md:grid-cols-3">
                        {[
                            { title: 'Mission', icon: 'fas fa-bullseye', colorClass: 'from-emerald-500 to-emerald-400', bgClass: 'bg-emerald-500/20', textClass: 'text-emerald-400', text: about.mission || 'To provide accessible financial services.' },
                            { title: 'Vision', icon: 'fas fa-eye', colorClass: 'from-cyan-500 to-cyan-400', bgClass: 'bg-cyan-500/20', textClass: 'text-cyan-400', text: about.vision || 'To be the leading cooperative institution.' },
                            { title: 'Values', icon: 'fas fa-heart', colorClass: 'from-slate-400 to-slate-300', bgClass: 'bg-white/10', textClass: 'text-gray-300', text: null, values: about.values || [] },
                        ].map((item, i) => (
                            <div key={i} className="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                                <div className={`absolute left-0 top-0 h-1 w-full rounded-t-2xl bg-gradient-to-r ${item.colorClass}`}></div>
                                <div className="mb-6 flex items-center gap-4">
                                    <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${item.bgClass}`}>
                                        <i className={`${item.icon} text-xl ${item.textClass}`}></i>
                                    </div>
                                    <h3 className="text-xl font-bold text-white">{item.title}</h3>
                                </div>
                                {item.text ? (
                                    <p className="text-slate-300">{item.text}</p>
                                ) : (
                                    <ul className="space-y-3 text-slate-300">
                                        {(item.values as any[]).map((v, j) => (
                                            <li key={j} className="flex items-start gap-2">
                                                <i className="fas fa-check-circle mt-0.5 shrink-0 text-xs text-emerald-400"></i>
                                                {typeof v === 'string' ? v : v.title || ''}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Board of Directors */}
            {boardCards.length > 0 && board.is_visible !== false && (
                <section className="py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-12 text-center">
                            <h2 className="mb-4 text-4xl font-extrabold text-slate-900">Board of Directors</h2>
                            <p className="text-lg text-slate-600">The people leading our cooperative</p>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            {boardCards.map((card: any, i: number) => (
                                <div key={i} className="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-center shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                                    {card.image_url ? (
                                        <img src={card.image_url} alt={card.name} className="h-48 w-full object-cover" />
                                    ) : (
                                        <div className="flex h-48 items-center justify-center bg-gradient-to-br from-emerald-100 to-emerald-200">
                                            <i className="fas fa-user text-5xl text-emerald-300"></i>
                                        </div>
                                    )}
                                    <div className="p-5">
                                        <h3 className="font-bold text-slate-900">{card.name}</h3>
                                        <p className="text-sm text-emerald-600">{card.position}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Officers */}
            {officerCards.length > 0 && officers.is_visible !== false && (
                <section className="bg-slate-50 py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-12 text-center">
                            <h2 className="mb-4 text-4xl font-extrabold text-slate-900">Our Officers</h2>
                            <p className="text-lg text-slate-600">The team making it happen</p>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            {officerCards.map((card: any, i: number) => (
                                <div key={i} className="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-center shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                                    {card.image_url ? (
                                        <img src={card.image_url} alt={card.name} className="h-48 w-full object-cover" />
                                    ) : (
                                        <div className="flex h-48 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                                            <i className="fas fa-user-tie text-5xl text-slate-300"></i>
                                        </div>
                                    )}
                                    <div className="p-5">
                                        <h3 className="font-bold text-slate-900">{card.name}</h3>
                                        <p className="text-sm text-emerald-600">{card.position}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* CTA */}
            <section className="bg-emerald-600 py-16">
                <div className="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 className="mb-4 text-3xl font-extrabold text-white md:text-4xl">Ready to Join?</h2>
                    <p className="mb-8 text-lg text-emerald-100">Become part of our growing cooperative community.</p>
                    <Link to="/register" className="inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 text-base font-semibold text-emerald-700 shadow-xl transition-all hover:bg-emerald-50 hover:shadow-2xl hover:-translate-y-0.5">
                        Apply for Membership <i className="fas fa-arrow-right"></i>
                    </Link>
                </div>
            </section>
        </>
    );
}
