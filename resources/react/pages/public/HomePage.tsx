import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function HomePage() {
    return (
        <>
            <HeroSection />
            <StatsSection />
            <AboutPreview />
            <MissionVision />
            <MembershipSection />
            <NewsSection />
            <ContactSection />
        </>
    );
}

function HeroSection() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const home = data?.data?.form?.home || {};
    const registrationEnabled = data?.data?.form?.registration?.online_member_registration_enabled !== false;

    return (
        <section className="relative flex min-h-screen items-center overflow-hidden bg-slate-950">
            {/* Animated background */}
            <div className="absolute inset-0">
                <img src="/images/hero_banner.jpg" alt="" className="h-full w-full object-cover opacity-40" />
                <div className="absolute inset-0 bg-gradient-to-br from-slate-950/90 via-slate-950/70 to-emerald-950/50"></div>
                {/* Decorative blobs */}
                <div className="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
                <div className="absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-emerald-500/5 blur-3xl"></div>
            </div>

            <div className="relative mx-auto max-w-7xl px-4 py-32 sm:px-6 lg:px-8">
                <div className="max-w-3xl">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 backdrop-blur-sm">
                        <i className="fas fa-seedling"></i>
                        Community-first financial services
                    </span>
                    <h1 className="mb-6 text-5xl font-extrabold leading-tight text-white md:text-7xl">
                        Settlers Credit<br />
                        <span className="bg-gradient-to-r from-emerald-400 to-emerald-200 bg-clip-text text-transparent">Cooperative</span>
                    </h1>
                    <p className="mb-10 max-w-2xl text-lg leading-relaxed text-white/80 md:text-xl">
                        {home.hero_subtitle || 'Building stronger communities through accessible, member-driven financial services. Your savings, your growth, your cooperative.'}
                    </p>
                    <div className="flex flex-col gap-4 sm:flex-row">
                        <Link to={registrationEnabled ? '/register' : '/contact'} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-xl shadow-emerald-600/25 transition-all hover:bg-emerald-700 hover:shadow-emerald-600/40 hover:-translate-y-0.5">
                            Join Now <i className="fas fa-arrow-right"></i>
                        </Link>
                        <a href="#about" className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-8 py-4 text-base font-semibold text-white backdrop-blur-sm transition-all hover:bg-white/20">
                            Learn More <i className="fas fa-chevron-down"></i>
                        </a>
                    </div>
                </div>
            </div>

            {/* Scroll indicator */}
            <div className="absolute bottom-8 left-1/2 -translate-x-1/2">
                <div className="flex h-10 w-6 items-center justify-center rounded-full border-2 border-white/30">
                    <div className="h-2 w-1 animate-bounce rounded-full bg-white/60"></div>
                </div>
            </div>
        </section>
    );
}

function StatsSection() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const home = data?.data?.form?.home || {};

    const stats = [
        { value: home.active_members || '500+', label: 'Active Members', icon: 'fas fa-users' },
        { value: home.total_assets || '₱10M+', label: 'Total Assets', icon: 'fas fa-chart-line' },
        { value: home.years_service || '10+', label: 'Years of Service', icon: 'fas fa-calendar-check' },
        { value: home.service_done || '1,000+', label: 'Services Done', icon: 'fas fa-handshake' },
    ];

    return (
        <section className="relative -mt-20 z-10 px-4 sm:px-6 lg:px-8">
            <div className="mx-auto max-w-6xl">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {stats.map((stat, i) => (
                        <div key={i} className="rounded-2xl border border-white/10 bg-white/95 p-6 shadow-xl backdrop-blur-sm transition-all hover:-translate-y-1 hover:shadow-2xl">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100">
                                <i className={`${stat.icon} text-emerald-600`}></i>
                            </div>
                            <p className="text-2xl font-extrabold text-slate-900 md:text-3xl">{stat.value}</p>
                            <p className="mt-1 text-sm font-medium text-slate-500">{stat.label}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function AboutPreview() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const about = data?.data?.form?.about || {};

    return (
        <section id="about" className="py-24">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-3xl text-center">
                    <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-1.5 text-sm font-semibold text-emerald-700">
                        <i className="fas fa-leaf"></i> About the cooperative
                    </span>
                    <h2 className="mb-6 text-4xl font-extrabold text-slate-900 md:text-5xl">Built around member progress</h2>
                    <p className="mb-6 text-lg leading-relaxed text-slate-600">
                        {about.about1 || 'Settlers Credit Cooperative is a member-owned financial institution dedicated to providing accessible savings, credit, and financial services to our community.'}
                    </p>
                    <p className="mb-10 text-lg leading-relaxed text-slate-600">
                        {about.about2 || 'We believe in empowering our members through cooperative principles, transparent governance, and community-driven growth.'}
                    </p>
                    <Link to="/about" className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition-all hover:bg-slate-800 hover:shadow-lg">
                        Learn more about us <i className="fas fa-arrow-right"></i>
                    </Link>
                </div>
            </div>
        </section>
    );
}

function MissionVision() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const about = data?.data?.form?.about || {};

    return (
        <section className="bg-slate-50 py-24">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid gap-8 md:grid-cols-2">
                    <div className="group rounded-2xl border border-slate-200 bg-white p-8 shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                        <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-lg shadow-emerald-500/25">
                            <i className="fas fa-bullseye text-2xl text-white"></i>
                        </div>
                        <h3 className="mb-4 text-2xl font-bold text-slate-900">Our Mission</h3>
                        <p className="leading-relaxed text-slate-600">
                            {about.mission || 'To provide accessible and sustainable financial services that empower our members and strengthen the community.'}
                        </p>
                    </div>
                    <div className="group rounded-2xl border border-slate-200 bg-white p-8 shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                        <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-700 to-slate-800 shadow-lg shadow-slate-700/25">
                            <i className="fas fa-eye text-2xl text-white"></i>
                        </div>
                        <h3 className="mb-4 text-2xl font-bold text-slate-900">Our Vision</h3>
                        <p className="leading-relaxed text-slate-600">
                            {about.vision || 'To be the leading cooperative institution, fostering financial literacy and economic growth for every member.'}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}

function MembershipSection() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const registrationEnabled = data?.data?.form?.registration?.online_member_registration_enabled !== false;

    const requirements = [
        'Must be at least 18 years old',
        'Proof of residence in the service area',
        'Valid identification (e.g., driver\'s license, passport)',
        'Initial deposit to open a savings account',
        'Agreement to abide by the cooperative\'s bylaws and policies',
    ];

    return (
        <section className="py-24">
            <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div className="text-center mb-12">
                    <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-1.5 text-sm font-semibold text-emerald-700">
                        <i className="fas fa-id-card"></i> Membership
                    </span>
                    <h2 className="mb-4 text-4xl font-extrabold text-slate-900 md:text-5xl">Ready when you are</h2>
                    <p className="text-lg text-slate-600">Join our growing community of members</p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm md:p-10">
                    <div className="space-y-6">
                        {requirements.map((req, i) => (
                            <div key={i} className="flex items-start gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-sm font-bold text-white shadow-md shadow-emerald-500/25">
                                    {i + 1}
                                </div>
                                <p className="pt-2 text-lg text-slate-700">{req}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-10 text-center">
                        <Link to={registrationEnabled ? '/register' : '/contact'} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-600/25 transition-all hover:bg-emerald-700 hover:shadow-emerald-600/40 hover:-translate-y-0.5">
                            Join Now <i className="fas fa-user-plus"></i>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

function NewsSection() {
    const { data } = useQuery({ queryKey: ['announcements-public'], queryFn: () => api.get('/api/announcements/public', { params: { per_page: 6 } }) });
    const announcements = data?.data?.data || [];

    if (announcements.length === 0) return null;

    return (
        <section className="bg-slate-50 py-24">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-12 text-center">
                    <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1.5 text-sm font-semibold text-emerald-700">
                        <i className="fas fa-newspaper"></i> Updates
                    </span>
                    <h2 className="mb-4 text-4xl font-extrabold text-slate-900 md:text-5xl">News & Announcements</h2>
                    <p className="text-lg text-slate-600">Stay updated with our latest news</p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {announcements.slice(0, 3).map((a: any) => (
                        <Link key={a.id} to="/news" className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-lg hover:-translate-y-1">
                            {a.image_url ? (
                                <img src={a.image_url} alt={a.title} className="h-48 w-full object-cover" />
                            ) : (
                                <div className="flex h-48 items-center justify-center bg-gradient-to-br from-emerald-500 to-emerald-700">
                                    <i className="fas fa-bullhorn text-5xl text-white/20"></i>
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
                        </Link>
                    ))}
                </div>

                <div className="mt-10 text-center">
                    <Link to="/news" className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition-all hover:bg-slate-50 hover:shadow-md">
                        View All News <i className="fas fa-arrow-right"></i>
                    </Link>
                </div>
            </div>
        </section>
    );
}

function ContactSection() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const contact = data?.data?.form?.contact || {};
    const [form, setForm] = useState({ name: '', email: '', message: '' });
    const [submitted, setSubmitted] = useState(false);

    const submitMutation = useMutation({
        mutationFn: () => api.post('/contact', form),
        onSuccess: () => setSubmitted(true),
    });

    return (
        <section id="Contact" className="py-24">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-12 text-center">
                    <span className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-1.5 text-sm font-semibold text-emerald-700">
                        <i className="fas fa-envelope"></i> Contact
                    </span>
                    <h2 className="mb-4 text-4xl font-extrabold text-slate-900 md:text-5xl">Get In Touch</h2>
                    <p className="text-lg text-slate-600">We're here to help you achieve your financial goals</p>
                </div>

                <div className="grid gap-12 md:grid-cols-2">
                    {/* Contact Form */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                        <h3 className="mb-6 text-xl font-bold text-slate-900">Send us a Message</h3>
                        {submitted ? (
                            <div className="rounded-xl bg-emerald-50 p-6 text-center">
                                <i className="fas fa-check-circle text-4xl text-emerald-500"></i>
                                <p className="mt-3 font-medium text-emerald-700">Message sent! We'll get back to you soon.</p>
                            </div>
                        ) : (
                            <form onSubmit={e => { e.preventDefault(); submitMutation.mutate(); }} className="space-y-5">
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-slate-700">Your Name</label>
                                    <input type="text" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm transition-all focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-slate-700">Your Email</label>
                                    <input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm transition-all focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-slate-700">Message</label>
                                    <textarea rows={4} value={form.message} onChange={e => setForm(f => ({ ...f, message: e.target.value }))} required className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm transition-all focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" />
                                </div>
                                <button type="submit" disabled={submitMutation.isPending} className="w-full rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition-all hover:bg-emerald-700 disabled:opacity-50">
                                    {submitMutation.isPending ? 'Sending...' : 'Send Message'} <i className="fas fa-paper-plane ml-2"></i>
                                </button>
                            </form>
                        )}
                    </div>

                    {/* Contact Info */}
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 className="mb-6 text-xl font-bold text-slate-900">Contact Information</h3>
                            <div className="space-y-6">
                                {[
                                    { icon: 'fas fa-map-marker-alt', color: 'bg-slate-700', label: 'Address', value: contact.address || 'Settlers, Philippines' },
                                    { icon: 'fas fa-phone', color: 'bg-emerald-600', label: 'Phone', value: contact.phone || '+63 XXX XXX XXXX' },
                                    { icon: 'fas fa-envelope', color: 'bg-slate-700', label: 'Email', value: contact.email || 'info@settlerscoop.com' },
                                    { icon: 'fas fa-clock', color: 'bg-cyan-500', label: 'Office Hours', value: contact.office_hours || 'Mon-Fri 8:00 AM - 5:00 PM' },
                                ].map((item, i) => (
                                    <div key={i} className="flex items-start gap-4">
                                        <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${item.color} text-white`}>
                                            <i className={item.icon}></i>
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">{item.label}</p>
                                            <p className="text-sm text-slate-500">{item.value}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {contact.map_embed && (
                            <div className="overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
                                <iframe src={contact.map_embed} className="h-64 w-full" style={{ border: 0 }} allowFullScreen loading="lazy" />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
