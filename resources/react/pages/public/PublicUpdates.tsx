import { useEffect, useId, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import Autoplay from 'embla-carousel-autoplay';
import useEmblaCarousel from 'embla-carousel-react';
import { Link } from 'react-router';
import { api } from '../../api/client';
import FallbackImage from '../../components/FallbackImage';
import Modal from '../../components/ui/Modal';
import { useLanguage } from '../../contexts/LanguageContext';

type UpdateSection = 'news' | 'events';

interface PublicUpdate {
    id: number;
    title: string;
    content: string;
    type: 'news' | 'announcement' | 'event';
    type_label: string;
    category: string | null;
    image_url: string | null;
    created_at: string;
    creator_name: string;
    event_date: string | null;
    event_time: string | null;
    event_location: string | null;
    is_upcoming: boolean;
}

interface UpdatesResponse {
    data: PublicUpdate[];
    pagination: { last_page: number };
}

const dateFormat = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
const reducedMotionQuery = '(prefers-reduced-motion: reduce)';
const arrowClasses = 'inline-flex size-11 items-center justify-center rounded-full border border-emerald-900/15 bg-white text-emerald-900 transition-colors enabled:hover:border-emerald-700 enabled:hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-40';

export default function PublicUpdates({ preview = false, eventsDescription }: { preview?: boolean; eventsDescription?: string }) {
    const { t } = useLanguage();

    return (
        <>
            <UpdateFeed section="news" preview={preview} />
            <UpdateFeed section="events" preview={preview} description={eventsDescription} />
            {preview && (
                <div className="bg-white pb-8 text-center md:pb-10">
                    <Link to="/news" className="public-btn public-btn-primary">{t('View All News & Events')}</Link>
                </div>
            )}
        </>
    );
}

function UpdateFeed({ section, preview, description }: { section: UpdateSection; preview: boolean; description?: string }) {
    const { t } = useLanguage();
    const headingId = useId();
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<PublicUpdate | null>(null);
    const isEvents = section === 'events';
    const title = t(isEvents ? 'Events' : 'Latest News & Announcements');
    const { data, isPending, isError } = useQuery({
        queryKey: ['public-updates', section, page],
        queryFn: async ({ signal }) => {
            const response = await api.get<UpdatesResponse>('/api/announcements/public', {
                params: { section, page, per_page: 6 },
                signal,
            });
            return response.data;
        },
    });
    const updates = data?.data ?? [];
    const heading = <h2 id={headingId} className={`public-heading min-w-0 ${preview ? 'text-2xl md:text-3xl' : 'text-3xl md:text-5xl'}`}>{title}</h2>;
    const intro = description && <p className="public-copy mt-3 max-w-3xl">{t(description)}</p>;

    return (
        <section aria-labelledby={headingId} className={`${preview ? 'py-8 md:py-12' : 'public-section'} ${isEvents === preview ? 'bg-white' : 'public-section-soft'}`}>
            <div className={`public-shell ${preview ? 'max-w-5xl' : ''}`}>
                {isPending || isError || updates.length === 0 ? (
                    <>
                        <header className="mb-6">{heading}{intro}</header>
                        {isPending ? (
                            <p role="status" className="public-copy">{t('Loading updates…')}</p>
                        ) : (
                            <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                                {t(isError ? 'Updates are temporarily unavailable.' : isEvents ? 'No public events are available yet.' : 'No news or announcements are available yet.')}
                            </p>
                        )}
                    </>
                ) : (
                    <UpdateCarousel key={page} title={title} heading={heading} intro={intro} updates={updates} preview={preview} onSelect={setSelected} />
                )}

                {!preview && (page > 1 || (data?.pagination.last_page ?? 1) > 1) && (
                    <nav aria-label={t(isEvents ? 'Event pages' : 'News and announcement pages')} className="mt-6 flex justify-center gap-4">
                        <button type="button" onClick={() => setPage(page - 1)} disabled={page === 1} className="text-sm font-semibold text-emerald-700 disabled:opacity-40">
                            {t('Newer entries')}
                        </button>
                        <span className="text-sm text-slate-600" aria-live="polite">{t('Page')} {page}</span>
                        <button type="button" onClick={() => setPage(page + 1)} disabled={page >= (data?.pagination.last_page ?? 1)} className="text-sm font-semibold text-emerald-700 disabled:opacity-40">
                            {t('Older entries')}
                        </button>
                    </nav>
                )}
            </div>

            {selected && (
                <Modal open title={selected.title} size="lg" onClose={() => setSelected(null)}>
                    {selected.image_url && <FallbackImage src={selected.image_url} alt="" className="mb-4 aspect-video w-full rounded-xl object-cover" />}
                    <UpdateMetadata update={selected} />
                    <p className="mt-2 text-sm text-slate-600">{t('By')} {selected.creator_name}</p>
                    <p className="mt-4 whitespace-pre-wrap break-words leading-relaxed text-slate-700">{selected.content}</p>
                </Modal>
            )}
        </section>
    );
}

function UpdateCarousel({ title, heading, intro, updates, preview, onSelect }: {
    title: string;
    heading: ReactNode;
    intro: ReactNode;
    updates: PublicUpdate[];
    preview: boolean;
    onSelect: (update: PublicUpdate) => void;
}) {
    const { t } = useLanguage();
    const trackId = useId();
    const [autoplay] = useState(() => preview ? Autoplay({ delay: 6000, playOnInit: false }) : null);
    const [autoplayEnabled, setAutoplayEnabled] = useState(() => preview && !window.matchMedia(reducedMotionQuery).matches);
    const [viewportRef, carousel] = useEmblaCarousel({
        align: 'start',
        breakpoints: { [reducedMotionQuery]: { duration: 0 } },
    }, autoplay ? [autoplay] : []);
    const [snapCount, setSnapCount] = useState(0);
    const [selectedSnap, setSelectedSnap] = useState(0);

    useEffect(() => {
        if (!carousel) return;

        const syncSelection = () => setSelectedSnap(carousel.selectedScrollSnap());
        const syncSnaps = () => {
            setSnapCount(carousel.scrollSnapList().length);
            syncSelection();
        };
        syncSnaps();
        carousel.on('reInit', syncSnaps).on('select', syncSelection);
        return () => {
            carousel.off('reInit', syncSnaps).off('select', syncSelection);
        };
    }, [carousel]);

    useEffect(() => {
        if (!carousel || !autoplay) return;

        const motionPreference = window.matchMedia(reducedMotionQuery);
        const pause = () => setAutoplayEnabled(false);
        const pauseForReducedMotion = (event: MediaQueryListEvent) => { if (event.matches) pause(); };
        const syncAutoplay = () => {
            if (autoplayEnabled && carousel.scrollSnapList().length > 1) autoplay.play();
            else autoplay.stop();
        };

        syncAutoplay();
        carousel.on('reInit', syncAutoplay).on('pointerDown', pause);
        motionPreference.addEventListener('change', pauseForReducedMotion);
        return () => {
            carousel.off('reInit', syncAutoplay).off('pointerDown', pause);
            motionPreference.removeEventListener('change', pauseForReducedMotion);
        };
    }, [carousel, autoplay, autoplayEnabled]);

    function pauseAutoplay() {
        setAutoplayEnabled(false);
    }

    function goToSlide(index: number) {
        pauseAutoplay();
        carousel?.scrollTo(index);
    }

    return (
        <div role="group" aria-roledescription="carousel" aria-label={title}>
            <header className={preview ? 'mb-3 sm:mb-4' : 'mb-5 sm:mb-6'}>
                <div className="flex items-start justify-between gap-3 sm:gap-6">
                    {heading}
                    {snapCount > 1 && (
                        <div className="flex shrink-0 gap-2">
                            <button type="button" aria-label={t('Previous slide')} aria-controls={trackId} disabled={selectedSnap === 0} onFocus={pauseAutoplay} onClick={() => goToSlide(selectedSnap - 1)} className={arrowClasses}>
                                <i className="fas fa-chevron-left" aria-hidden="true" />
                            </button>
                            <button type="button" aria-label={t('Next slide')} aria-controls={trackId} disabled={selectedSnap === snapCount - 1} onFocus={pauseAutoplay} onClick={() => goToSlide(selectedSnap + 1)} className={arrowClasses}>
                                <i className="fas fa-chevron-right" aria-hidden="true" />
                            </button>
                        </div>
                    )}
                </div>
                {intro}
            </header>
            <div ref={viewportRef} onMouseEnter={pauseAutoplay} onFocusCapture={pauseAutoplay} className="overflow-hidden rounded-2xl">
                <ul id={trackId} className="flex touch-pan-y touch-pinch-zoom gap-5">
                    {updates.map(update => (
                        <li key={update.id} className="flex min-w-0 shrink-0 basis-[88%] max-sm:only:basis-full sm:basis-[calc((100%-1.25rem)/2)] lg:basis-[calc((100%-2.5rem)/3)]">
                            <article className="public-card relative flex w-full flex-col overflow-hidden focus-within:ring-2 focus-within:ring-inset focus-within:ring-emerald-700">
                                <div className={`flex items-center justify-center overflow-hidden bg-linear-to-br ${preview ? 'h-28 sm:h-32 lg:h-36' : 'h-52 sm:h-56 lg:h-60'} ${update.type === 'event' ? 'from-emerald-700 to-emerald-950' : 'from-slate-600 to-slate-900'}`}>
                                    {update.image_url ? (
                                        <FallbackImage src={update.image_url} alt="" className="pointer-events-none h-full w-full select-none object-cover" />
                                    ) : (
                                        <span className={`grid place-items-center border border-white/15 bg-white/10 text-white/80 ${preview ? 'size-12 rounded-2xl text-xl' : 'size-20 rounded-3xl text-3xl'}`}>
                                            <i aria-hidden="true" className={`fas ${update.type === 'event' ? 'fa-calendar-alt' : update.type === 'news' ? 'fa-newspaper' : 'fa-bullhorn'}`} />
                                        </span>
                                    )}
                                </div>
                                <div className={`flex flex-1 flex-col ${preview ? 'p-4' : 'p-5'}`}>
                                    <UpdateMetadata update={update} compact={preview} />
                                    <h3 className={`break-words font-bold text-slate-900 ${preview ? 'mt-2 line-clamp-2 text-base' : 'mt-3 text-xl'}`}>{update.title}</h3>
                                    <p className={`break-words text-sm leading-relaxed text-slate-600 ${preview ? 'my-2 line-clamp-2' : 'my-3 line-clamp-3'}`}>{update.content}</p>
                                    <button type="button" aria-label={`${t('Read more')} · ${update.title}`} onClick={() => onSelect(update)} className="mt-auto self-start rounded-sm pt-2 text-sm font-semibold text-emerald-700 hover:underline">
                                        {t('Read more')} <i aria-hidden="true" className="fas fa-arrow-right ml-1" />
                                    </button>
                                </div>
                            </article>
                        </li>
                    ))}
                </ul>
            </div>
            {snapCount > 1 && (
                <div role="group" aria-label={t('Slide navigation')} className={`flex justify-center gap-1 ${preview ? 'mt-2' : 'mt-3'}`}>
                    {autoplay && (
                        <button
                            type="button"
                            aria-label={t(autoplayEnabled ? 'Pause automatic scrolling' : 'Play automatic scrolling')}
                            aria-controls={trackId}
                            onClick={() => setAutoplayEnabled(enabled => !enabled)}
                            className="grid size-9 shrink-0 place-items-center rounded-full text-xs text-emerald-800 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            <i aria-hidden="true" className={`fas ${autoplayEnabled ? 'fa-pause' : 'fa-play'}`} />
                        </button>
                    )}
                    {Array.from({ length: snapCount }, (_, index) => (
                        <button
                            key={index}
                            type="button"
                            aria-label={t('Go to slide {number}').replace('{number}', String(index + 1))}
                            aria-controls={trackId}
                            aria-current={index === selectedSnap ? 'true' : undefined}
                            onFocus={pauseAutoplay}
                            onClick={() => goToSlide(index)}
                            className="group grid h-9 w-9 place-items-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        >
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-slate-400 motion-safe:transition-[width,background-color] group-hover:bg-emerald-600 group-aria-current:w-6 group-aria-current:bg-emerald-700" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function UpdateMetadata({ update, compact = false }: { update: PublicUpdate; compact?: boolean }) {
    const { t } = useLanguage();
    const isEvent = update.type === 'event';
    const date = isEvent ? update.event_date : update.created_at;

    return (
        <div className={`text-slate-600 ${compact ? 'space-y-1 text-xs' : 'space-y-2 text-sm'}`}>
            <div className="flex flex-wrap items-center gap-2">
                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${isEvent ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'}`}>
                    {t(update.type_label)}
                </span>
                {isEvent && date && <span>{t(update.is_upcoming ? 'Upcoming' : 'Past event')}</span>}
                {date && <time dateTime={date}>{dateFormat.format(new Date(isEvent ? `${date}T00:00:00` : date))}</time>}
            </div>
            {update.category && <p>{update.category}</p>}
            {isEvent && update.event_time && <p><i aria-hidden="true" className="fas fa-clock mr-2" />{update.event_time}</p>}
            {isEvent && update.event_location && <p><i aria-hidden="true" className="fas fa-map-marker-alt mr-2" />{update.event_location}</p>}
        </div>
    );
}
