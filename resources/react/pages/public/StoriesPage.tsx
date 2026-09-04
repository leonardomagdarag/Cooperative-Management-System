const stories = [
    { title: 'Growing My Business', quote: 'The business loan helped me expand from a small stall to a full-fledged store.', name: 'Juan Cruz', role: 'Business Owner', tag: 'Business Loan', icon: 'fa-store', tone: 'bg-slate-700' },
    { title: 'Education for My Children', quote: 'The education loan made college accessible for our family and helped my children pursue their dreams.', name: 'Rosa Reyes', role: 'Parent & Member', tag: 'Education Loan', icon: 'fa-graduation-cap', tone: 'bg-emerald-700' },
    { title: 'Securing My Future', quote: 'The savings program helped me build an emergency fund and plan for retirement.', name: 'Pedro Garcia', role: 'Retired Member', tag: 'Savings Plan', icon: 'fa-piggy-bank', tone: 'bg-slate-700' },
    { title: 'My First Car', quote: 'With an auto loan from the cooperative, I purchased my first vehicle and changed my daily commute.', name: 'Ana Lopez', role: 'Working Professional', tag: 'Auto Loan', icon: 'fa-car', tone: 'bg-emerald-700' },
    { title: 'Emergency Support', quote: 'The cooperative’s quick loan process helped me get the funds I needed when my family faced an emergency.', name: 'Carlos Mendoza', role: 'Long-time Member', tag: 'Emergency Loan', icon: 'fa-hands-helping', tone: 'bg-slate-700' },
];

export default function StoriesPage() {
    return (
        <div className="public-page">
            <section className="relative overflow-hidden bg-slate-950 px-4 pb-16 pt-32">
                <div className="relative mx-auto max-w-4xl text-center">
                    <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300"><i className="fas fa-quote-right text-xs"></i> Member experiences</span>
                    <h1 className="mb-6 text-4xl font-extrabold text-white md:text-6xl">Member Stories</h1>
                    <p className="text-xl leading-relaxed text-slate-300">Discover how Settlers Credit Cooperative has made a difference through financial empowerment and community support.</p>
                </div>
            </section>
            <section className="public-section bg-white"><div className="public-shell">
                <span className="mb-8 inline-block rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold text-slate-700">Featured Story</span>
                <div className="public-card overflow-hidden md:flex">
                    <div className="flex h-72 items-center justify-center bg-slate-700 md:h-auto md:w-1/2"><i className="fas fa-home text-8xl text-white opacity-20"></i></div>
                    <div className="p-8 md:w-1/2 md:p-12"><h2 className="mb-4 text-2xl font-bold text-gray-900 md:text-3xl">From Dream to Reality: Maria’s Home Story</h2><p className="mb-6 text-lg leading-relaxed text-gray-700">“After 15 years of renting, I finally achieved my dream of owning a home thanks to Settlers Credit Cooperative. Their flexible loan options made the impossible possible.”</p><div className="mb-6 flex items-center gap-4"><div className="flex h-14 w-14 items-center justify-center rounded-full bg-slate-700"><i className="fas fa-user text-xl text-white"></i></div><div><p className="font-bold text-gray-900">Maria Santos</p><p className="text-sm text-gray-600">Member since 2010</p></div></div><span className="rounded-full border border-gray-200 px-4 py-2 text-sm font-semibold text-emerald-700">Home Loan · Success Story</span></div>
                </div>
            </div></section>
            <section className="public-section public-section-soft"><div className="public-shell"><h2 className="public-heading mb-10 text-center text-3xl md:text-5xl">More Success Stories</h2><div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">{stories.map(story => <article key={story.title} className="public-card public-card-hover overflow-hidden"><div className={`flex h-48 items-center justify-center ${story.tone}`}><i className={`fas ${story.icon} text-7xl text-white opacity-20`}></i></div><div className="p-6"><h3 className="mb-3 text-xl font-bold text-gray-900">{story.title}</h3><p className="mb-5 leading-relaxed text-gray-600">“{story.quote}”</p><div className="mb-4 flex items-center gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100"><i className="fas fa-user text-emerald-600"></i></div><div><p className="text-sm font-semibold text-gray-900">{story.name}</p><p className="text-xs text-gray-500">{story.role}</p></div></div><span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{story.tag}</span></div></article>)}</div></div></section>
        </div>
    );
}
