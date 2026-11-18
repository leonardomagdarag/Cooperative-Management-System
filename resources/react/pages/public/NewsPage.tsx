import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import PublicUpdates from './PublicUpdates';

export default function NewsPage() {
    const { data: pc } = useQuery({ queryKey: ['public-content'], queryFn: () => api.get('/api/public-content') });
    const newsContent = pc?.data?.form?.news || {};
    const eventsContent = pc?.data?.form?.events || {};

    return (
        <div className="public-page">
            <section className="relative overflow-hidden bg-slate-950 px-4 pb-16 pt-32">
                <div className="relative mx-auto max-w-4xl text-center">
                    <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                        <i className="fas fa-newspaper text-xs"></i> Cooperative updates
                    </span>
                    <h1 className="mb-6 text-4xl font-medium text-white md:text-6xl">News & Events</h1>
                    <p className="text-xl text-slate-300 leading-relaxed">
                        {newsContent.hero_description || 'Stay updated with our latest news and events'}
                    </p>
                </div>
            </section>
            <PublicUpdates eventsDescription={eventsContent.events || 'Join us at our upcoming events and activities.'} />
        </div>
    );
}
