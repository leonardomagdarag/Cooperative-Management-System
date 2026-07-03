import { useState, useEffect } from 'react';
import { Outlet, Link, useLocation } from 'react-router';

export default function PublicLayout() {
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const location = useLocation();

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => { setMobileOpen(false); }, [location]);

    const navLinks = [
        { to: '/', label: 'Home', icon: 'fas fa-home' },
        { to: '/about', label: 'About', icon: 'fas fa-info-circle' },
        { to: '/news', label: 'News', icon: 'fas fa-newspaper' },
        { to: '/contact', label: 'Contact', icon: 'fas fa-envelope' },
    ];

    return (
        <div className="min-h-screen bg-white">
            {/* Navbar */}
            <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${scrolled ? 'bg-white/90 shadow-lg backdrop-blur-xl' : 'bg-transparent'}`}>
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-20 items-center justify-between">
                        {/* Logo */}
                        <Link to="/" className="flex items-center gap-3">
                            <img src="/images/Logo.png" alt="Settlers Logo" className="h-10 w-10 rounded-full object-cover" />
                            <div>
                                <h1 className={`text-lg font-extrabold transition-colors ${scrolled ? 'text-slate-900' : 'text-white'}`}>Settlers</h1>
                                <p className={`text-xs font-medium transition-colors ${scrolled ? 'text-emerald-600' : 'text-emerald-300'}`}>Credit Cooperative</p>
                            </div>
                        </Link>

                        {/* Desktop Nav */}
                        <div className="hidden items-center gap-1 lg:flex">
                            {navLinks.map(link => (
                                <Link key={link.to} to={link.to} className={`rounded-lg px-4 py-2 text-sm font-semibold transition-colors ${location.pathname === link.to ? (scrolled ? 'text-emerald-600 bg-emerald-50' : 'text-emerald-300') : (scrolled ? 'text-slate-600 hover:text-emerald-600' : 'text-white/80 hover:text-white')}`}>
                                    {link.label}
                                </Link>
                            ))}
                            <Link to="/login" className="ml-4 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition-all hover:bg-emerald-700 hover:shadow-emerald-600/40">
                                <i className="fas fa-sign-in-alt mr-2"></i>Login
                            </Link>
                        </div>

                        {/* Mobile toggle */}
                        <button onClick={() => setMobileOpen(!mobileOpen)} className={`rounded-lg p-2 lg:hidden ${scrolled ? 'text-slate-700' : 'text-white'}`}>
                            <i className={`fas ${mobileOpen ? 'fa-times' : 'fa-bars'} text-xl`}></i>
                        </button>
                    </div>
                </div>

                {/* Mobile Menu */}
                {mobileOpen && (
                    <div className="border-t border-slate-200 bg-white shadow-lg lg:hidden">
                        <div className="space-y-1 px-4 py-4">
                            {navLinks.map(link => (
                                <Link key={link.to} to={link.to} className={`block rounded-lg px-4 py-3 font-semibold transition-colors ${location.pathname === link.to ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'}`}>
                                    <i className={`${link.icon} mr-3 w-5 text-center`}></i>{link.label}
                                </Link>
                            ))}
                            <Link to="/login" className="mt-2 block rounded-lg bg-emerald-600 px-4 py-3 text-center font-semibold text-white">
                                <i className="fas fa-sign-in-alt mr-2"></i>Login
                            </Link>
                        </div>
                    </div>
                )}
            </nav>

            {/* Content */}
            <Outlet />

            {/* Footer */}
            <footer className="bg-slate-950 text-white">
                <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="grid gap-10 md:grid-cols-3">
                        <div>
                            <div className="mb-4 flex items-center gap-3">
                                <img src="/images/Logo.png" alt="Logo" className="h-10 w-10 rounded-full" />
                                <div>
                                    <h3 className="text-lg font-extrabold">Settlers</h3>
                                    <p className="text-xs font-medium text-emerald-400">Credit Cooperative</p>
                                </div>
                            </div>
                            <p className="max-w-sm text-sm leading-relaxed text-slate-400">Member-focused financial services for savings, loans, and community growth.</p>
                        </div>
                        <div>
                            <h4 className="mb-4 text-lg font-semibold">Quick Links</h4>
                            <ul className="space-y-2 text-slate-400">
                                {navLinks.map(l => <li key={l.to}><Link to={l.to} className="hover:text-white transition-colors"><i className="fas fa-chevron-right mr-2 text-xs"></i>{l.label}</Link></li>)}
                                <li><Link to="/login" className="hover:text-white transition-colors"><i className="fas fa-chevron-right mr-2 text-xs"></i>Login</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="mb-4 text-lg font-semibold">Connect</h4>
                            <div className="mb-4 flex gap-3">
                                <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 transition-all hover:-translate-y-1 hover:bg-emerald-600"><i className="fab fa-facebook"></i></a>
                                <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 transition-all hover:-translate-y-1 hover:bg-emerald-600"><i className="fas fa-envelope"></i></a>
                                <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 transition-all hover:-translate-y-1 hover:bg-emerald-600"><i className="fas fa-map-marker-alt"></i></a>
                            </div>
                        </div>
                    </div>
                    <div className="mt-12 border-t border-white/10 pt-8 text-center text-sm text-slate-500">
                        &copy; {new Date().getFullYear()} Settlers Credit Cooperative. All rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
