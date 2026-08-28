import { useState, useEffect, useRef, useCallback } from 'react';
import { Outlet, Link, useLocation, useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';

export default function PublicLayout() {
    const [isShrunk, setIsShrunk] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [isHovering, setIsHovering] = useState(false);
    const [policyModal, setPolicyModal] = useState<'terms' | 'privacy' | null>(null);
    const location = useLocation();
    const navigate = useNavigate();
    const ticking = useRef(false);

    const { data: pc } = useQuery({
        queryKey: ['public-content'],
        queryFn: () => api.get('/api/public-content'),
    });

    const termsContent = pc?.data?.form?.policies?.terms_and_conditions;
    const privacyContent = pc?.data?.form?.policies?.privacy_policy;

    useEffect(() => { setMobileOpen(false); }, [location]);

    useEffect(() => {
        let frame: number | null = null;
        const handleScroll = () => {
            if (ticking.current) return;
            ticking.current = true;
            frame = requestAnimationFrame(() => {
                setIsShrunk(window.scrollY > 50 && !isHovering);
                ticking.current = false;
            });
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', handleScroll);
            if (frame !== null) cancelAnimationFrame(frame);
            ticking.current = false;
        };
    }, [isHovering]);

    useEffect(() => {
        if (!isHovering) {
            setIsShrunk(window.scrollY > 50);
        } else {
            setIsShrunk(false);
        }
    }, [isHovering]);

    const isHome = location.pathname === '/';

    const scrollToContact = useCallback((e: React.MouseEvent) => {
        if (isHome) {
            e.preventDefault();
            const el = document.getElementById('Contact');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        }
    }, [isHome]);

    const navLinkClass = (path: string) => {
        const active = location.pathname === path;
        return `relative px-4 py-2 font-semibold transition-all duration-200 ${
            active
                ? 'text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-600'
                : 'text-gray-700 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100'
        }`;
    };

    const mobileLinkClass = (path: string) =>
        `block rounded-xl border-l-4 px-4 py-3 font-semibold transition-all ${
            location.pathname === path
                ? 'border-emerald-600 bg-emerald-50 text-emerald-700'
                : 'border-transparent text-gray-700 hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700'
        }`;

    const navbarHeight = isShrunk ? '3.5rem' : '5rem';
    const logoSize = isShrunk ? '2rem' : '3rem';
    const headingSize = isShrunk ? '1rem' : '1.25rem';
    const headingLine = isShrunk ? '1.5rem' : '1.75rem';
    const subtitleOpacity = isShrunk ? 0 : 1;

    return (
        <div className="min-h-screen bg-white">
            <nav
                className="fixed top-0 left-0 right-0 z-50 border-b border-emerald-900/10 bg-white/90 backdrop-blur-xl shadow-sm transition-all duration-300"
                style={{ ['--navbar-height' as string]: navbarHeight }}
                onMouseEnter={() => setIsHovering(true)}
                onMouseLeave={() => setIsHovering(false)}
            >
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between transition-all duration-300" style={{ height: navbarHeight }}>
                        <Link to="/" className="flex items-center gap-3 shrink-0">
                            <div
                                className="rounded-full overflow-hidden flex items-center justify-center transition-all duration-300 shrink-0"
                                style={{ width: logoSize, height: logoSize }}
                            >
                                <img src="/images/Logo.png" alt="Settlers Logo" className="w-full h-full object-cover" width="48" height="48" />
                            </div>
                            <div className="transition-all duration-300">
                                <h1 className="font-extrabold text-slate-900 transition-all duration-300" style={{ fontSize: headingSize, lineHeight: headingLine }}>Settlers</h1>
                                <p className="text-xs font-medium text-emerald-700 transition-all duration-300" style={{ opacity: subtitleOpacity, maxHeight: subtitleOpacity ? '1rem' : 0 }}>Credit Cooperative</p>
                            </div>
                        </Link>

                        <div className="hidden lg:flex items-center ml-auto">
                            <div className="flex items-center gap-1 mr-6">
                                <Link to="/" className={navLinkClass('/')}><i className="fas fa-home mr-2"></i>Home</Link>
                                <Link to="/about" className={navLinkClass('/about')}><i className="fas fa-info-circle mr-2"></i>About Us</Link>
                                <Link to="/news" className={navLinkClass('/news')}><i className="fas fa-newspaper mr-2"></i>News</Link>
                                <a
                                    href={isHome ? '#Contact' : '/#Contact'}
                                    onClick={scrollToContact}
                                    className="relative px-4 py-2 font-semibold text-gray-700 transition-all duration-200 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100"
                                >
                                    <i className="fas fa-envelope mr-2"></i>Contact
                                </a>
                            </div>
                            <Link to="/login" className="public-btn public-btn-primary px-5 py-2.5">
                                <i className="fas fa-sign-in-alt mr-2"></i>Login
                            </Link>
                        </div>

                        <button
                            onClick={() => setMobileOpen(!mobileOpen)}
                            className="lg:hidden p-2 text-gray-700 hover:text-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-lg"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {mobileOpen ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>
                    </div>
                </div>

                {mobileOpen && (
                    <div className="lg:hidden border-t border-emerald-900/10 bg-white/95 shadow-lg backdrop-blur-xl">
                        <div className="px-4 py-4 space-y-2">
                            <Link to="/" className={mobileLinkClass('/')}><i className="fas fa-home mr-3"></i>Home</Link>
                            <Link to="/about" className={mobileLinkClass('/about')}><i className="fas fa-info-circle mr-3"></i>About Us</Link>
                            <Link to="/news" className={mobileLinkClass('/news')}><i className="fas fa-newspaper mr-3"></i>News</Link>
                            <a
                                href={isHome ? '#Contact' : '/#Contact'}
                                onClick={(e) => { scrollToContact(e); setMobileOpen(false); }}
                                className="block rounded-xl border-l-4 border-transparent px-4 py-3 font-semibold text-gray-700 transition-all hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700"
                            >
                                <i className="fas fa-envelope mr-3"></i>Contact
                            </a>
                            <Link to="/login" className="public-btn public-btn-primary w-full">
                                <i className="fas fa-sign-in-alt mr-2"></i>Login
                            </Link>
                        </div>
                    </div>
                )}
            </nav>

            <Outlet />

            <footer className="bg-slate-950 text-white py-16 px-4">
                <div className="public-shell">
                    <div className="grid gap-10 md:grid-cols-[1.3fr_0.8fr_1fr] mb-12">
                        <div>
                            <div className="flex items-center gap-3 mb-6">
                                <div className="rounded-full overflow-hidden flex items-center justify-center bg-white p-1" style={{ width: '3.5rem', height: '3.5rem' }}>
                                    <img src="/images/Logo.png" alt="Settlers Logo" className="w-full h-full object-cover" />
                                </div>
                                <div>
                                    <h3 className="text-xl font-extrabold text-white">Settlers</h3>
                                    <p className="text-xs font-medium text-emerald-300">Credit Cooperative</p>
                                </div>
                            </div>
                            <p className="max-w-sm text-sm leading-relaxed text-slate-400">
                                Member-focused financial services for savings, loans, and community growth.
                            </p>
                        </div>

                        <div>
                            <h4 className="font-semibold mb-6 text-slate-200 text-lg">Quick Links</h4>
                            <ul className="space-y-3 text-slate-400">
                                <li><Link to="/" className="hover:text-white transition-colors inline-flex items-center gap-2"><i className="fas fa-chevron-right text-xs"></i>Home</Link></li>
                                <li><Link to="/about" className="hover:text-white transition-colors inline-flex items-center gap-2"><i className="fas fa-chevron-right text-xs"></i>About Us</Link></li>
                                <li><Link to="/news" className="hover:text-white transition-colors inline-flex items-center gap-2"><i className="fas fa-chevron-right text-xs"></i>News</Link></li>
                                <li><a href={isHome ? '#Contact' : '/#Contact'} onClick={scrollToContact} className="hover:text-white transition-colors inline-flex items-center gap-2"><i className="fas fa-chevron-right text-xs"></i>Contact</a></li>
                                <li><Link to="/login" className="hover:text-white transition-colors inline-flex items-center gap-2"><i className="fas fa-chevron-right text-xs"></i>Login</Link></li>
                            </ul>
                        </div>

                        <div>
                            <h4 className="font-semibold mb-6 text-slate-200 text-lg">Connect With Us</h4>
                            <div className="flex gap-3 mb-6">
                                <a href="#facebook_link" aria-label="Facebook" className="w-11 h-11 rounded-full border border-white/10 bg-white/5 flex items-center justify-center transition-all hover:-translate-y-0.5 hover:bg-emerald-600"><i className="fab fa-facebook text-lg"></i></a>
                                <a href="#gmail" aria-label="Gmail" className="w-11 h-11 rounded-full border border-white/10 bg-white/5 flex items-center justify-center transition-all hover:-translate-y-0.5 hover:bg-emerald-600"><i className="fas fa-envelope text-lg"></i></a>
                                <a href="#google_map" aria-label="Google Map" className="w-11 h-11 rounded-full border border-white/10 bg-white/5 flex items-center justify-center transition-all hover:-translate-y-0.5 hover:bg-emerald-600"><i className="fas fa-map text-lg"></i></a>
                            </div>
                            <a href="#gmail_link" className="text-sm text-slate-400 hover:text-white">
                                <i className="fas fa-envelope mr-2"></i>
                                Contact us
                            </a>
                        </div>
                    </div>

                    <div className="border-t border-white/10 pt-8">
                        <div className="mb-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-slate-400">
                            <button type="button" onClick={() => setPolicyModal('terms')} className="inline-flex items-center gap-2 transition hover:text-white">
                                <i className="fas fa-file-contract text-xs"></i>Terms and Agreement
                            </button>
                            <button type="button" onClick={() => setPolicyModal('privacy')} className="inline-flex items-center gap-2 transition hover:text-white">
                                <i className="fas fa-shield-halved text-xs"></i>Privacy Policy
                            </button>
                        </div>
                        <p className="text-center text-slate-500 text-sm">&copy; {new Date().getFullYear()} Settlers Credit Cooperative. All rights reserved.</p>
                    </div>
                </div>
            </footer>

            {policyModal && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" onClick={() => setPolicyModal(null)}>
                    <div className="relative max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-8 shadow-2xl" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setPolicyModal(null)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                            <i className="fas fa-times text-xl"></i>
                        </button>
                        <h2 className="text-2xl font-bold text-slate-900 mb-4">
                            {policyModal === 'terms' ? 'Terms and Agreement' : 'Privacy Policy'}
                        </h2>
                        {(() => {
                            const content = policyModal === 'terms' ? termsContent : privacyContent;
                            return content ? (
                                <div className="text-sm text-slate-600 leading-relaxed space-y-3" dangerouslySetInnerHTML={{ __html: content }} />
                            ) : (
                                <p className="text-sm text-slate-500">This policy content is not currently available. Please contact the cooperative for assistance.</p>
                            );
                        })()}
                    </div>
                </div>
            )}
        </div>
    );
}
