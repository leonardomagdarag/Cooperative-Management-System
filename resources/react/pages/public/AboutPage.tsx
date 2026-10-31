import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { api } from '../../api/client';

export default function AboutPage() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const about = data?.data?.form?.about || {};
    const aboutImage = about.about_image || '/images/hero_banner.jpg';
    const board = data?.data?.form?.board || {};
    const officers = data?.data?.form?.officers || {};
    const memberStatements = data?.data?.form?.member_statements || {};

    const boardVisible = board.is_visible !== false;
    const officersVisible = officers.is_visible !== false;
    const boardCards = (board.cards || []).filter((c: any) => c.name || c.position || c.image_url);
    const officerCards = (officers.cards || []).filter((c: any) => c.name || c.position || c.image_url);

    const statementCards = [
        { key: 'statement1', quoteClass: 'text-slate-700', avatarClass: 'bg-slate-700' },
        { key: 'statement2', quoteClass: 'text-emerald-700', avatarClass: 'bg-emerald-700' },
        { key: 'statement3', quoteClass: 'text-slate-700', avatarClass: 'bg-slate-700' },
    ];

    const communityMilestones = [
        { year: '1999', title: 'The Calauan Housing Project', description: 'The site that would become Southville 7 was completed as a housing project for government employees.' },
        { year: '2006', title: 'The site becomes Southville 7', description: 'The site began welcoming families from outside Calauan, including families displaced by a major fire and infrastructure projects. It was renamed Southville 7 as it shifted toward housing relocated informal settlers.' },
        { year: '2009', title: 'Families displaced by Typhoon Ondoy', description: 'Southville 7 opened to thousands of families affected by the typhoon. The National Housing Authority and ABS-CBN Lingkod Kapamilya Foundation began working together on community management and support.' },
        { year: '2012', title: 'Partnerships for community development', description: 'The Asian Development Bank’s Japan Fund for Poverty Reduction approved a grant supporting community integration, partnerships, innovation, and social entrepreneurship.' },
        { year: '2013', title: 'Local livelihood initiatives', description: 'Community partners launched the Community Innovation Fund to help residents build small businesses and strengthen self-help capacity.' },
        { year: '2015', title: 'Community water partnership', description: 'Construction of communal water stations was completed in March, and clean water began flowing in May. Homeowners’ associations took part in operating the stations, which also generated income for community activities.' },
    ];

    return (
        <div className="public-page">
            <section className="relative overflow-hidden bg-slate-950 px-4 pb-20 pt-32">
                <div className="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.03%22%3E%3Cpath%20d%3D%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')] opacity-60"></div>
                <div className="relative mx-auto max-w-4xl text-center">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                        <i className="fas fa-leaf text-xs"></i> Settlers Credit Cooperative
                    </span>
                    <h1 className="text-4xl md:text-6xl font-extrabold text-white mb-6 leading-tight tracking-tight">About Us</h1>
                    <div className="w-16 h-1 bg-emerald-400 mx-auto rounded-full mb-8"></div>
                    <p className="text-lg md:text-xl text-gray-300 leading-relaxed max-w-3xl mx-auto">
                        {about.about1 || 'Settlers Credit Cooperative is dedicated to serving our community.'}
                    </p>
                </div>
            </section>

            <section className="public-section bg-white">
                <div className="public-shell">
                    <div className="grid md:grid-cols-2 gap-14 items-center">
                        <div>
                            <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <i className="fas fa-history text-xs"></i> Our Journey
                            </span>
                            <h2 className="public-heading text-3xl md:text-5xl mb-4">Our History</h2>
                            <div className="w-12 h-1 bg-emerald-600 rounded-full mb-6"></div>
                            <div className="space-y-5 text-base text-gray-600 leading-relaxed">
                                <p>{about.about2 || ''}</p>
                                <div>{about.history || ''}</div>
                            </div>
                        </div>
                        <div className="relative">
                            <div className="absolute -inset-4 rounded-3xl bg-emerald-50 -z-10"></div>
                            <FallbackImage src={aboutImage} alt="Cooperative member Pictures" className="w-full h-80 md:h-[420px] object-cover rounded-2xl shadow-xl" />
                        </div>
                    </div>
                </div>
            </section>

            <section className="public-section bg-slate-50">
                <div className="public-shell">
                    <div className="mx-auto mb-12 max-w-3xl text-center">
                        <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <i className="fas fa-map-marker-alt text-xs"></i> Southville 7 Community Background
                        </span>
                        <h2 className="public-heading mb-4 text-3xl md:text-4xl">A community shaped by resilience</h2>
                        <p className="text-base leading-relaxed text-gray-600">
                            The timeline below summarizes the history of Southville 7 as a resettlement community, based on an Asian Development Bank publication. It describes the community and its development partners; it is not a timeline of Settlers Credit Cooperative.
                        </p>
                    </div>
                    <ol className="relative mx-auto max-w-4xl border-s border-emerald-200">
                        {communityMilestones.map((milestone, index) => (
                            <li key={milestone.year + milestone.title} className="ms-8 pb-9 last:pb-0">
                                <span className="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 ring-8 ring-slate-50">
                                    <i className={`fas ${index === communityMilestones.length - 1 ? 'fa-seedling' : 'fa-check'} text-[9px] text-white`} aria-hidden="true"></i>
                                </span>
                                <p className="mb-1 text-sm font-bold uppercase tracking-wider text-emerald-700">{milestone.year}</p>
                                <h3 className="mb-2 text-lg font-bold text-gray-900">{milestone.title}</h3>
                                <p className="leading-relaxed text-gray-600">{milestone.description}</p>
                            </li>
                        ))}
                    </ol>
                    <p className="mx-auto mt-10 max-w-4xl text-sm leading-relaxed text-gray-500">
                        The cooperative has rebranded from Southville 7 Credit Cooperative to Settlers Credit Cooperative, as provided by the cooperative. The ADB report does not document the cooperative’s history or rebranding. Its milestones below refer to Southville 7 as a community.{' '}
                        <a className="font-semibold text-emerald-700 underline underline-offset-2 hover:text-emerald-800" href="https://www.adb.org/sites/default/files/publication/220531/transforming-southville-7.pdf" target="_blank" rel="noreferrer">Read the ADB publication, “Southville 7: Making Resettlement Work”</a>.
                    </p>
                </div>
            </section>

            <section className="public-section bg-slate-950">
                <div className="public-shell">
                    <div className="text-center mb-14">
                        <span className="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">
                            <i className="fas fa-compass text-xs"></i> Our Foundation
                        </span>
                        <h2 className="text-3xl md:text-4xl font-bold text-white mt-2">What Guides Us</h2>
                    </div>

                    <div className="grid md:grid-cols-3 gap-6">
                        <div className="group relative overflow-hidden rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                            <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-500 to-emerald-400 rounded-t-2xl"></div>
                            <div className="flex items-center gap-4 mb-6">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20">
                                    <i className="fas fa-bullseye text-xl text-emerald-400"></i>
                                </div>
                                <h3 className="text-xl font-bold text-white">Mission</h3>
                            </div>
                            <p className="text-gray-300 leading-relaxed">{about.mission || 'To provide accessible financial services.'}</p>
                        </div>

                        <div className="group relative overflow-hidden rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                            <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-cyan-500 to-cyan-400 rounded-t-2xl"></div>
                            <div className="flex items-center gap-4 mb-6">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-cyan-500/20">
                                    <i className="fas fa-eye text-xl text-cyan-400"></i>
                                </div>
                                <h3 className="text-xl font-bold text-white">Vision</h3>
                            </div>
                            <p className="text-gray-300 leading-relaxed">{about.vision || 'To be the leading cooperative institution.'}</p>
                        </div>

                        <div className="group relative overflow-hidden rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                            <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-slate-400 to-slate-300 rounded-t-2xl"></div>
                            <div className="flex items-center gap-4 mb-6">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10">
                                    <i className="fas fa-heart text-xl text-gray-300"></i>
                                </div>
                                <h3 className="text-xl font-bold text-white">Values</h3>
                            </div>
                            <ul className="space-y-3 text-gray-300">
                                {(about.values || []).map((v: any, i: number) => (
                                    <li key={i} className="flex items-start gap-2">
                                        <i className="fas fa-check-circle mt-0.5 shrink-0 text-emerald-400 text-xs"></i>
                                        <span className="text-sm">
                                            <strong className="text-white">{v.title || ''}</strong>{v.description ? `: ${v.description}` : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {boardVisible && boardCards.length > 0 && (
                <section className="public-section bg-white">
                    <div className="public-shell">
                        <div className="text-center mb-14">
                            <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                <i className="fas fa-chess-king text-xs"></i> Leadership
                            </span>
                            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Board of Directors</h2>
                            <p className="text-base text-gray-500 max-w-xl mx-auto">Meet the dedicated leaders guiding our cooperative towards growth and service.</p>
                        </div>

                        <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-6">
                            {boardCards.map((card: any, i: number) => (
                                <article key={i} className="group public-card public-card-hover overflow-hidden">
                                    <div className="relative">
                                        {card.image_url ? (
                                            <>
                                                <FallbackImage src={card.image_url} alt={card.name} className="h-64 w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                                <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/50 to-transparent"></div>
                                            </>
                                        ) : (
                                            <div className="flex h-64 items-center justify-center bg-gradient-to-br from-slate-800 to-slate-700">
                                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-white/10 ring-4 ring-white/20">
                                                    <i className="fas fa-user text-4xl text-white/60"></i>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    <div className="p-5 text-center">
                                        <h3 className="text-base font-bold text-gray-900 leading-snug">{card.name || ''}</h3>
                                        <p className="mt-1 text-sm font-semibold text-emerald-600">{card.position || ''}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {officersVisible && officerCards.length > 0 && (
                <section className="public-section public-section-soft">
                    <div className="public-shell">
                        <div className="text-center mb-14">
                            <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <i className="fas fa-users text-xs"></i> Management
                            </span>
                            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Our Officers</h2>
                            <p className="text-base text-gray-500 max-w-xl mx-auto">The dedicated professionals managing our daily operations and member services.</p>
                        </div>

                        <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-6">
                            {officerCards.map((card: any, i: number) => (
                                <article key={i} className="group public-card public-card-hover overflow-hidden">
                                    <div className="relative">
                                        {card.image_url ? (
                                            <>
                                                <FallbackImage src={card.image_url} alt={card.name} className="h-64 w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                                <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/50 to-transparent"></div>
                                            </>
                                        ) : (
                                            <div className="flex h-64 items-center justify-center bg-gradient-to-br from-emerald-800 to-emerald-700">
                                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-white/10 ring-4 ring-white/20">
                                                    <i className="fas fa-user-tie text-4xl text-white/60"></i>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    <div className="p-5 text-center">
                                        <h3 className="text-base font-bold text-gray-900 leading-snug">{card.name || ''}</h3>
                                        <p className="mt-1 text-sm font-semibold text-emerald-600">{card.position || ''}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            <section className="public-section bg-white">
                <div className="public-shell">
                    <div className="text-center mb-14">
                        <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <i className="fas fa-quote-right text-xs"></i> Testimonials
                        </span>
                        <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Member Stories</h2>
                        <p className="text-base text-gray-500 max-w-xl mx-auto">Hear from our members about their experience with our cooperative.</p>
                    </div>

                    <div className="grid md:grid-cols-3 gap-6">
                        {statementCards.map((s, i) => {
                            const stmt = (memberStatements as any)[s.key] || {};
                            return (
                                <div key={i} className="public-card public-card-hover relative overflow-hidden p-8">
                                    <div className="absolute -top-3 -left-3 text-[7rem] leading-none font-serif font-bold text-emerald-100 select-none pointer-events-none">&ldquo;</div>
                                    <p className="relative text-gray-700 text-sm leading-relaxed mb-8">{stmt.statement || ''}</p>
                                    <div className="flex items-center gap-4 border-t border-gray-200 pt-6">
                                        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${s.avatarClass} shadow-sm`}>
                                            <i className="fas fa-user text-sm text-white"></i>
                                        </div>
                                        <div>
                                            <p className="text-sm font-bold text-gray-900">{stmt.name || ''}</p>
                                            <p className="text-xs text-gray-500">Member since {stmt.member_since || ''}</p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section className="public-section px-4 bg-emerald-800 relative overflow-hidden">
                <div className="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.05%22%3E%3Cpath%20d%3D%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')]"></div>
                <div className="relative max-w-3xl mx-auto text-center text-white">
                    <h2 className="text-3xl md:text-4xl font-bold mb-4 leading-tight">Ready to Join Our Community?</h2>
                    <p className="text-lg text-white/90 mb-10 max-w-xl mx-auto">Become part of a cooperative that puts your financial well-being and community growth first.</p>
                    <div className="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link to="/#Contact" className="public-btn bg-white text-emerald-700 shadow-md hover:bg-emerald-50">
                            <i className="fas fa-envelope text-sm"></i> Contact Us
                        </Link>
                        <Link to="/login" className="public-btn public-btn-secondary">
                            <i className="fas fa-sign-in-alt text-sm"></i> Member Login
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}
import FallbackImage from '../../components/FallbackImage';
