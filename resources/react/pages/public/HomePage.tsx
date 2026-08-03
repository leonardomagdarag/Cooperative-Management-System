import { useState } from 'react';
import { Link } from 'react-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function HomePage() {
    return (
        <div className="public-page">
            <HeroSection />
            <AboutPreview />
            <MissionVision />
            <MembershipSection />
            <NewsSection />
            <ContactSection />
            <div className="public-section bg-white"></div>
        </div>
    );
}

function HeroSection() {
    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const home = pc?.data?.form?.home || {};
    const registrationEnabled = pc?.data?.form?.registration?.online_member_registration_enabled !== false;

    return (
        <section className="relative flex min-h-[100vh] items-center overflow-hidden pt-24">
            <div className="absolute inset-0 z-0">
                <img src="/images/hero_banner.jpg" alt="Hero background" className="w-full h-full object-cover" fetchPriority="high" />
                <div className="absolute inset-0 public-gradient-hero"></div>
            </div>

            <div className="public-shell relative z-10 py-20">
                <div className="max-w-3xl">
                    
                    <h1 className="mb-6 text-5xl font-extrabold leading-tight text-white md:text-7xl">
                        Settlers Credit Cooperative
                    </h1>
                    <p className="mb-8 max-w-2xl text-lg leading-8 text-white/90 md:text-xl">
                        {home.hero_subtitle || 'Building stronger communities through accessible, member-driven financial services.'}
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <a href="#about" className="public-btn public-btn-primary">
                            Explore membership
                        </a>
                        <Link to={registrationEnabled ? '/register' : '#Contact'} className="public-btn public-btn-secondary">
                            Join now
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

function AboutPreview() {
    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const about = pc?.data?.form?.about || {};
    const home = pc?.data?.form?.home || {};

    return (
        <section id="about" className="public-section bg-white">
            <div className="public-shell">
                <div className="mx-auto max-w-3xl text-center">
                    <span className="public-eyebrow mb-4">About the cooperative</span>
                    <h2 className="public-heading mb-5 text-3xl md:text-5xl">Built around member progress</h2>
                    <p className="public-copy mb-5 text-lg text-left md:text-center">
                        {about.about1 || 'Settlers Credit Cooperative is a member-owned financial institution dedicated to providing accessible savings, credit, and financial services to our community.'}
                    </p>
                    <p className="public-copy mb-8 text-lg text-left md:text-center">
                        {about.about2 || 'We believe in empowering our members through cooperative principles, transparent governance, and community-driven growth.'}
                    </p>
                </div>

                <div className="my-14 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="public-card public-card-hover p-6">
                        <div className="mb-2 text-3xl font-extrabold text-emerald-700">
                            {home.active_members || '500+'}
                        </div>
                        <div className="text-sm font-medium text-slate-600">Active Members</div>
                    </div>
                    <div className="public-card public-card-hover p-6">
                        <div className="mb-2 text-3xl font-extrabold text-emerald-700">
                            {home.total_assets || '₱10M+'}
                        </div>
                        <div className="text-sm font-medium text-slate-600">Total Assets</div>
                    </div>
                    <div className="public-card public-card-hover p-6">
                        <div className="mb-2 text-3xl font-extrabold text-emerald-700">
                            {home.years_service || '10+'}
                        </div>
                        <div className="text-sm font-medium text-slate-600">Years of Service</div>
                    </div>
                    <div className="public-card public-card-hover p-6">
                        <div className="mb-2 text-3xl font-extrabold text-emerald-700">
                            {home.service_done || '1,000+'}
                        </div>
                        <div className="text-sm font-medium text-slate-600">Services Done</div>
                    </div>
                </div>

                <div className="text-center">
                    <Link to="/about" className="public-btn public-btn-primary">
                        Learn more about us
                    </Link>
                </div>
            </div>
        </section>
    );
}

function MissionVision() {
    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const about = pc?.data?.form?.about || {};

    return (
        <section className="public-section public-section-soft">
            <div className="public-shell">
                <div className="grid md:grid-cols-2 gap-8">
                    <div className="public-card public-card-hover p-8">
                        <div className="w-14 h-14 bg-emerald-700 rounded-xl flex items-center justify-center mb-6">
                            <i className="fas fa-bullseye text-2xl text-white"></i>
                        </div>
                        <h3 className="text-2xl font-bold text-gray-900 mb-4">Our Mission</h3>
                        <p className="text-gray-700 leading-relaxed">
                            {about.mission || 'To provide accessible and sustainable financial services that empower our members and strengthen the community.'}
                        </p>
                    </div>
                    <div className="public-card public-card-hover p-8">
                        <div className="w-14 h-14 bg-slate-700 rounded-xl flex items-center justify-center mb-6">
                            <i className="fas fa-eye text-2xl text-white"></i>
                        </div>
                        <h3 className="text-2xl font-bold text-gray-900 mb-4">Our Vision</h3>
                        <p className="text-gray-700 leading-relaxed">
                            {about.vision || 'To be the leading cooperative institution, fostering financial literacy and economic growth for every member.'}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}

function MembershipSection() {
    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const registrationEnabled = pc?.data?.form?.registration?.online_member_registration_enabled !== false;

    const requirements = [
        'Must be at least 18 years old',
        'Proof of residence in the service area',
        'Valid identification (e.g., driver\'s license, passport)',
        'Initial deposit to open a savings account',
        'Agreement to abide by the cooperative\'s bylaws and policies',
    ];

    return (
        <section className="public-section bg-white">
            <div className="public-shell max-w-4xl">
                <div className="text-center mb-12">
                    <span className="public-eyebrow mb-4"><i className="fas fa-id-card"></i> Membership</span>
                    <h2 className="public-heading mb-4 text-3xl md:text-5xl">Ready when you are</h2>
                    <p className="public-copy text-lg">Join our growing community of members</p>
                </div>
                <div className="public-card p-8 md:p-10">
                    <div className="space-y-6">
                        {requirements.map((req, i) => (
                            <div key={i} className="flex items-start gap-4">
                                <div className="shrink-0 w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center font-bold">{i + 1}</div>
                                <p className="text-lg text-gray-800 pt-1">{req}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-10 text-center">
                        <Link to={registrationEnabled ? '/register' : '#Contact'} className="public-btn public-btn-primary">
                            Join Now
                            <i className="fas fa-user-plus ml-2"></i>
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
        <section className="public-section public-section-soft">
            <div className="public-shell">
                <div className="text-center mb-12">
                    <span className="public-eyebrow mb-4">Updates</span>
                    <h2 className="public-heading mb-4 text-3xl md:text-5xl">News & Announcements</h2>
                    <p className="public-copy text-lg">Stay updated with our latest news and updates</p>
                </div>

                <div className="grid md:grid-cols-3 gap-6 mb-12">
                    {announcements.slice(0, 6).map((a: any) => {
                        const iconMap: Record<string, string> = { Achievement: 'trophy', Update: 'mobile-alt' };
                        const category = a.type || a.category;
                        const isAchievement = category === 'Achievement';
                        const isUpdate = category === 'Update';
                        const bannerBg = a.image_url ? 'bg-gray-200' : isAchievement ? 'bg-emerald-700' : isUpdate ? 'bg-blue-700' : 'bg-slate-700';
                        const badgeBg = isAchievement ? 'bg-emerald-100 text-emerald-700' : isUpdate ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700';

                        return (
                            <Link key={a.id} to="/news" className="group public-card public-card-hover overflow-hidden flex flex-col">
                                <div className={`h-40 w-full shrink-0 overflow-hidden relative ${bannerBg}`}>
                                    {a.image_url ? (
                                        <img src={a.image_url} alt={a.title} className="w-full h-full object-cover" />
                                    ) : (
                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <i className={`fas fa-${iconMap[category] || 'bullhorn'} text-5xl text-white opacity-20`}></i>
                                        </div>
                                    )}
                                </div>
                                <div className="p-6 flex flex-col flex-1">
                                    <div className="flex items-center justify-between mb-4">
                                        <span className={`px-3 py-1 rounded-full text-xs font-semibold ${badgeBg}`}>
                                            {category || a.type_label || 'News'}
                                        </span>
                                        <span className="text-xs text-gray-500">
                                            <i className="fas fa-calendar mr-1"></i>{new Date(a.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                                        </span>
                                    </div>
                                    <h3 className="text-xl font-bold text-gray-900 mb-3 line-clamp-2 group-hover:text-slate-700 transition-colors">
                                        {a.title}
                                    </h3>
                                    <p className="text-sm text-gray-600 leading-relaxed line-clamp-3 mb-4 flex-1">
                                        {a.content}
                                    </p>
                                    <div className="flex items-center text-slate-700 font-semibold text-sm group-hover:text-slate-800">
                                        Read more
                                        <i className="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>

                <div className="text-center">
                    <Link to="/news" className="public-btn public-btn-primary">
                        View All News & Events
                        <i className="fas fa-newspaper ml-2"></i>
                    </Link>
                </div>
            </div>
        </section>
    );
}

function ContactSection() {
    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const contact = pc?.data?.form?.contact || {};
    const [form, setForm] = useState({ name: '', email: '', message: '' });
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submitMutation = useMutation({
        mutationFn: () => api.post('/contact', form),
        onSuccess: () => setSubmitted(true),
        onError: (err: any) => setError(err?.response?.data?.message || 'Failed to send message. Please try again.'),
    });

    return (
        <section id="Contact" className="public-section bg-white">
            <div className="public-shell">
                <div className="text-center mb-12">
                    <span className="public-eyebrow mb-4">Contact</span>
                    <h2 className="public-heading mb-4 text-3xl md:text-5xl">Get In Touch</h2>
                    <p className="public-copy text-lg">We're here to help you achieve your financial goals</p>
                </div>
                <div className="grid md:grid-cols-2 gap-12">
                    <div className="public-card p-8">
                        <h3 className="text-2xl text-center font-bold text-gray-900 mb-8">Send us a Message</h3>
                        {submitted ? (
                            <div className="rounded-xl bg-emerald-50 p-6 text-center">
                                <i className="fas fa-check-circle text-4xl text-emerald-500"></i>
                                <p className="mt-3 font-medium text-emerald-700">Message sent! We'll get back to you soon.</p>
                            </div>
                        ) : (
                            <form onSubmit={e => { e.preventDefault(); setError(null); submitMutation.mutate(); }} className="space-y-6">
                                    {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</div>}
                                <div>
                                    <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
                                    <input type="text" id="name" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required className="public-input" />
                                </div>
                                <div>
                                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">Your Email</label>
                                    <input type="email" id="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required className="public-input" />
                                </div>
                                <div>
                                    <label htmlFor="message" className="block text-sm font-medium text-gray-700 mb-2">Your Message</label>
                                    <textarea id="message" rows={4} value={form.message} onChange={e => setForm(f => ({ ...f, message: e.target.value }))} required className="public-input resize-none" />
                                </div>
                                <button type="submit" disabled={submitMutation.isPending} className="public-btn public-btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed">
                                    {submitMutation.isPending ? 'Sending...' : 'Send Message'}
                                    <i className="fas fa-paper-plane ml-2"></i>
                                </button>
                            </form>
                        )}
                    </div>

                    <div>
                        <div className="public-card p-8 mb-3">
                            <h3 className="text-2xl font-bold text-center text-gray-900 mb-6">Contact Information</h3>
                            <div className="space-y-8">
                                <div className="flex items-center gap-4">
                                    <div className="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center shrink-0">
                                        <i className="fas fa-map-marker-alt text-white"></i>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900 mb-1">Address</h4>
                                        <a href="#googlemaps" className="text-slate-700 hover:text-slate-900">
                                            {contact.address || 'Settlers, Philippines'}
                                        </a>
                                    </div>
                                </div>
                                <div className="flex items-center gap-4">
                                    <div className="w-12 h-12 bg-emerald-700 rounded-full flex items-center justify-center shrink-0">
                                        <i className="fas fa-phone text-white"></i>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900 mb-1">Phone Numbers</h4>
                                        <p className="text-gray-600">{contact.phone || '+63 XXX XXX XXXX'}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-4">
                                    <div className="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center shrink-0">
                                        <i className="fas fa-envelope text-white"></i>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900 mb-1">Email</h4>
                                        <a href="#gmail" className="text-slate-700 hover:text-slate-900">{contact.email || 'info@settlerscoop.com'}</a>
                                    </div>
                                </div>
                                <div className="flex items-center gap-4">
                                    <div className="w-12 h-12 bg-cyan-500 rounded-full flex items-center justify-center shrink-0">
                                        <i className="fas fa-clock text-white"></i>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900 mb-1">Office Hours</h4>
                                        <p className="text-gray-600">{contact.office_hours || 'Mon-Fri 8:00 AM - 5:00 PM'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {contact.map_embed ? (
                            <div className="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
                                <iframe src={contact.map_embed} className="h-64 w-full" style={{ border: 0 }} allowFullScreen loading="lazy" referrerPolicy="no-referrer-when-downgrade" />
                            </div>
                        ) : (
                            <div className="bg-gray-200 rounded-3xl h-64 flex items-center justify-center">
                                <p className="text-gray-500">Map Location</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
