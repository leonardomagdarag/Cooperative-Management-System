<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>News & Events - Settlers Credit Cooperative</title>
</head>

<body class="public-page">
    @include('partials.public.topNavbar')

    @php
        $newsContent = data_get($publicContent ?? [], 'news', []);
        $eventsContent = data_get($publicContent ?? [], 'events', []);
        $newsItems = $publicNewsItems;
        $iconMap = [
            'Achievement' => 'trophy',
            'Update' => 'mobile-alt',
        ];
    @endphp

    <!-- Hero Header Section -->
    <section class="relative overflow-hidden bg-slate-950 px-4 pb-16 pt-32">
        <div class="relative max-w-4xl mx-auto text-center">
            <span class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                <i class="fas fa-newspaper text-xs"></i> Cooperative updates
            </span>
            <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-6">News & Events</h1>
            <p class="text-xl text-slate-300 leading-relaxed">
                {{ data_get($newsContent, 'hero_description') }}
            </p>
        </div>
    </section>

    <!-- News & Announcements Section -->
    <section class="public-section bg-white">
        <div class="public-shell">
            <div class="mb-12">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                        <i class="fas fa-newspaper text-white text-xl"></i>
                    </div>
                    <h2 class="public-heading text-3xl md:text-5xl">Latest News & Announcements</h2>
                </div>
                <div class="w-28 h-1 bg-emerald-700 rounded-full"></div>
            </div>

            <div class="space-y-6">
                @forelse ($newsItems as $newsItem)
                    <div
                        class="public-card public-card-hover overflow-hidden">
                        <div class="md:flex">
                            {{-- Fixed-size image / colour panel --}}
                            <div
                                class="md:w-1/4 h-48 md:h-auto shrink-0 relative overflow-hidden flex items-center justify-center
                                @if ($newsItem->image_path) bg-gray-200
                                @elseif ($newsItem->category === 'Achievement') bg-emerald-700
                                @elseif ($newsItem->category === 'Update') bg-blue-700
                                @else bg-slate-700 @endif">
                                @if ($newsItem->image_path)
                                    <img src="{{ asset('storage/' . $newsItem->image_path) }}"
                                        alt="{{ $newsItem->title }}"
                                        class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-{{ $iconMap[$newsItem->category ?? ''] ?? 'bullhorn' }} text-5xl text-white opacity-30"></i>
                                @endif
                            </div>
                            <div class="p-8 md:w-3/4">
                                <div class="flex items-center gap-3 mb-4">
                                    <span
                                        class="px-4 py-1 rounded-full text-sm font-semibold @if ($newsItem->category === 'Achievement') bg-emerald-100 text-emerald-700 @elseif($newsItem->category === 'Update') bg-blue-100 text-blue-700 @else bg-slate-100 text-slate-700 @endif">
                                        {{ $newsItem->category ?? $newsItem->type_label }}
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        <i
                                            class="fas fa-calendar mr-2"></i>{{ $newsItem->created_at->format('M d, Y') }}
                                    </span>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ $newsItem->title }}</h3>
                                <p class="text-gray-600 leading-relaxed mb-4">
                                    {{ $newsItem->content }}
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div
                        class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                        No news items are available yet.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- Events Section (Upcoming & Past from DB) -->
    @php
        $upcoming = $publicEventItems->filter(fn($e) => $e->is_upcoming);
        $past = $publicEventItems->reject(fn($e) => $e->is_upcoming);
        $categoryIcons = [
            'Workshop' => 'book',
            'Annual Meeting' => 'handshake',
            'Community Service' => 'hands-helping',
            'Celebration' => 'gift',
            'Campaign' => 'coins',
            'Training' => 'chalkboard-teacher',
            'Seminar' => 'microphone',
            'General Assembly' => 'users',
        ];
        $bgColors = ['bg-slate-700', 'bg-emerald-700', 'bg-cyan-700', 'bg-indigo-700'];
    @endphp
    <section class="public-section public-section-soft">
        <div class="public-shell">
            <div class="mb-12">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-white text-xl"></i>
                    </div>
                    <h2 class="public-heading text-3xl md:text-5xl">Events</h2>
                </div>
                <div class="w-24 h-1 bg-emerald-700 rounded"></div>
                <p class="mt-6 max-w-4xl text-base text-gray-600">
                    {{ data_get($eventsContent, 'events') }}
                </p>
            </div>

            {{-- Upcoming Events --}}
            @if ($upcoming->isNotEmpty())
                <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span> Upcoming
                </h3>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                    @foreach ($upcoming as $i => $event)
                        @php $bg = $bgColors[$i % count($bgColors)]; @endphp
                        <div
                            class="public-card public-card-hover overflow-hidden flex flex-col">
                            {{-- Fixed-height image / colour banner --}}
                            <div class="h-48 shrink-0 relative overflow-hidden {{ $event->image_path ? 'bg-gray-200' : $bg }}">
                                @if ($event->image_path)
                                    <img src="{{ asset('storage/' . $event->image_path) }}"
                                        alt="{{ $event->title }}"
                                        class="w-full h-full object-cover">
                                @else
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i
                                            class="fas fa-{{ $categoryIcons[$event->category ?? ''] ?? 'calendar-alt' }} text-7xl text-white opacity-20"></i>
                                    </div>
                                @endif
                                <div
                                    class="absolute top-4 left-4 bg-emerald-600 text-white px-3 py-1.5 rounded-lg shadow text-xs font-bold uppercase">
                                    Upcoming
                                </div>
                                <div
                                    class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-sm font-bold text-slate-700">
                                    {{ $event->event_date->format('M d, Y') }}
                                </div>
                            </div>
                            <div class="p-6 flex flex-col flex-1">
                                @if ($event->category)
                                    <div class="flex items-center gap-2 mb-3">
                                        <i
                                            class="fas fa-{{ $categoryIcons[$event->category] ?? 'tag' }} text-emerald-700 text-sm"></i>
                                        <span
                                            class="text-sm font-semibold text-emerald-700">{{ $event->category }}</span>
                                    </div>
                                @endif
                                <h3 class="text-lg font-bold text-slate-800 mb-3">{{ $event->title }}</h3>
                                <p class="text-gray-600 mb-4 leading-relaxed text-sm line-clamp-3 flex-1">
                                    {{ $event->content }}</p>
                                <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                    @if ($event->event_time)
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-clock"></i><span>{{ $event->event_time }}</span>
                                        </div>
                                    @endif
                                    @if ($event->event_location)
                                        <div class="flex items-center gap-1.5">
                                            <i
                                                class="fas fa-map-marker-alt"></i><span>{{ $event->event_location }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Past Events --}}
            @if ($past->isNotEmpty())
                <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span> Past Events
                </h3>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($past as $event)
                        <div
                            class="public-card public-card-hover overflow-hidden opacity-90 flex flex-col">
                            {{-- Fixed-height image / grey banner --}}
                            <div class="h-48 shrink-0 relative overflow-hidden {{ $event->image_path ? 'bg-gray-300' : 'bg-gray-400' }}">
                                @if ($event->image_path)
                                    <img src="{{ asset('storage/' . $event->image_path) }}"
                                        alt="{{ $event->title }}"
                                        class="w-full h-full object-cover opacity-80">
                                @else
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i
                                            class="fas fa-{{ $categoryIcons[$event->category ?? ''] ?? 'calendar-check' }} text-7xl text-white opacity-20"></i>
                                    </div>
                                @endif
                                <div
                                    class="absolute top-4 left-4 bg-gray-600 text-white px-3 py-1.5 rounded-lg shadow text-xs font-bold uppercase">
                                    Completed
                                </div>
                                <div
                                    class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-sm font-bold text-gray-600">
                                    {{ $event->event_date->format('M d, Y') }}
                                </div>
                            </div>
                            <div class="p-6 flex flex-col flex-1">
                                @if ($event->category)
                                    <div class="flex items-center gap-2 mb-3">
                                        <i
                                            class="fas fa-{{ $categoryIcons[$event->category] ?? 'tag' }} text-gray-500 text-sm"></i>
                                        <span class="text-sm font-semibold text-gray-600">{{ $event->category }}</span>
                                    </div>
                                @endif
                                <h3 class="text-lg font-bold text-slate-800 mb-3">{{ $event->title }}</h3>
                                <p class="text-gray-600 mb-4 leading-relaxed text-sm line-clamp-3 flex-1">
                                    {{ $event->content }}</p>
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <i class="fas fa-check-circle text-emerald-500"></i>
                                    <span>Event Completed</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($publicEventItems->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center text-gray-500">
                    <i class="fas fa-calendar-alt text-4xl text-gray-300 mb-4 block"></i>
                    No events are scheduled yet. Check back soon!
                </div>
            @endif
        </div>
    </section>

    <!-- Newsletter Subscription Section -->
    <section class="public-section bg-white">
        <div class="mx-auto max-w-4xl px-4 text-center">
            <div class="public-card p-10">
                <div class="inline-block p-4 bg-white rounded-full shadow-md mb-6">
                    <i class="fas fa-envelope-open-text text-3xl text-emerald-700"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mb-4">
                    {{ data_get($newsContent, 'newsletter.title') }}
                </h2>
                <p class="text-base text-gray-700 mb-8">
                    {{ data_get($newsContent, 'newsletter.description') }}
                </p>
                <div class="max-w-md mx-auto mb-6">
                    <div class="flex flex-col gap-4">
                        <input type="text" placeholder="Your full name"
                            class="public-input">
                        <input type="email" placeholder="Your email address"
                            class="public-input">
                        <button
                            class="public-btn public-btn-primary w-full">
                            Subscribe Now
                            <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </div>
                </div>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-lock mr-2"></i>{{ data_get($newsContent, 'newsletter.privacy_note') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')
</body>

</html>
