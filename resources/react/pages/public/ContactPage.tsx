import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function ContactPage() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const contact = data?.data?.form?.contact || {};
    const [form, setForm] = useState({ name: '', email: '', message: '' });
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submitMutation = useMutation({
        mutationFn: () => api.post('/contact', form),
        onSuccess: () => setSubmitted(true),
        onError: (err: any) => setError(err?.response?.data?.message || 'Failed to send message. Please try again.'),
    });

    return (
        <div className="public-page min-h-screen">
            <section className="public-section bg-white">
                <div className="public-shell">
                    <div className="text-center mb-12">
                        <span className="public-eyebrow mb-4"><i className="fas fa-envelope"></i> Contact</span>
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
                                            <a href="#gmail" className="text-slate-700 hover:text-slate-900">
                                                {contact.email || 'info@settlerscoop.com'}
                                            </a>
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
        </div>
    );
}
