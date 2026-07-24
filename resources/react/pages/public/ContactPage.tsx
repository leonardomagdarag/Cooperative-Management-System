import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function ContactPage() {
    const { data } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const contact = data?.data?.form?.contact || {};
    const [form, setForm] = useState({ name: '', email: '', message: '' });
    const [submitted, setSubmitted] = useState(false);

    const submitMutation = useMutation({
        mutationFn: () => api.post('/contact', form),
        onSuccess: () => setSubmitted(true),
    });

    return (
        <>
            <section className="relative overflow-hidden bg-slate-950 pb-20 pt-32">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-950 to-emerald-950/30"></div>
                <div className="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <h1 className="mb-6 text-5xl font-extrabold text-white md:text-6xl">Contact Us</h1>
                    <p className="text-lg text-slate-300">We're here to help you achieve your financial goals</p>
                </div>
            </section>

            <section className="py-16">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-12 md:grid-cols-2">
                        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                            <h3 className="mb-6 text-xl font-bold text-slate-900">Send us a Message</h3>
                            {submitted ? (
                                <div className="rounded-xl bg-emerald-50 p-6 text-center">
                                    <i className="fas fa-check-circle text-4xl text-emerald-500"></i>
                                    <p className="mt-3 font-medium text-emerald-700">Message sent! We'll get back to you soon.</p>
                                </div>
                            ) : (
                                <form onSubmit={e => { e.preventDefault(); submitMutation.mutate(); }} className="space-y-5">
                                    <div><label className="mb-1.5 block text-sm font-medium text-slate-700">Your Name</label><input type="text" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" /></div>
                                    <div><label className="mb-1.5 block text-sm font-medium text-slate-700">Your Email</label><input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" /></div>
                                    <div><label className="mb-1.5 block text-sm font-medium text-slate-700">Message</label><textarea rows={5} value={form.message} onChange={e => setForm(f => ({ ...f, message: e.target.value }))} required className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" /></div>
                                    <button type="submit" disabled={submitMutation.isPending} className="w-full rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 hover:bg-emerald-700 disabled:opacity-50">
                                        {submitMutation.isPending ? 'Sending...' : 'Send Message'} <i className="fas fa-paper-plane ml-2"></i>
                                    </button>
                                </form>
                            )}
                        </div>

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
                                            <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${item.color} text-white`}><i className={item.icon}></i></div>
                                            <div><p className="text-sm font-semibold text-slate-900">{item.label}</p><p className="text-sm text-slate-500">{item.value}</p></div>
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
        </>
    );
}
