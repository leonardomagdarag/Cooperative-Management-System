<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>About Us - Settlers Credit Cooperative</title>
</head>

<body class="bg-white">
    @include('partials.public.topNavbar')

    @php
        $aboutContent = data_get($publicContent ?? [], 'about', []);
        $boardContent = data_get($publicContent ?? [], 'board', []);
        $officersContent = data_get($publicContent ?? [], 'officers', []);
        $memberStatements = data_get($publicContent ?? [], 'member_statements', []);
        $boardVisible = (bool) data_get($boardContent, 'is_visible', true);
        $boardCards = collect(data_get($boardContent, 'cards', []))
            ->filter(
                fn($card) => filled($card['name'] ?? null) ||
                    filled($card['position'] ?? null) ||
                    filled($card['image_url'] ?? null),
            )
            ->values();
        $officersVisible = (bool) data_get($officersContent, 'is_visible', true);
        $officerCards = collect(data_get($officersContent, 'cards', []))
            ->filter(
                fn($card) => filled($card['name'] ?? null) ||
                    filled($card['position'] ?? null) ||
                    filled($card['image_url'] ?? null),
            )
            ->values();
        $statementCards = [
            ['key' => 'statement1', 'quoteClass' => 'text-slate-700', 'avatarClass' => 'bg-slate-700'],
            ['key' => 'statement2', 'quoteClass' => 'text-emerald-700', 'avatarClass' => 'bg-emerald-700'],
            ['key' => 'statement3', 'quoteClass' => 'text-slate-700', 'avatarClass' => 'bg-slate-700'],
        ];
    @endphp

    <!-- Hero Header Section -->
    <section class="relative pt-32 pb-20 px-4 overflow-hidden bg-gradient-to-b from-slate-900 to-slate-800">
        {{-- Subtle grid overlay --}}
        <div
            class="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.03%22%3E%3Cpath%20d%3D%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')] opacity-60">
        </div>
        {{-- Emerald glow --}}
        <div
            class="pointer-events-none absolute -top-32 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-emerald-700/20 blur-3xl">
        </div>

        <div class="relative max-w-4xl mx-auto text-center">
            <span
                class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-semibold text-emerald-300">
                <i class="fas fa-leaf text-xs"></i> Our Cooperative Story
            </span>
            <h1 class="text-4xl md:text-6xl font-bold text-white mb-6 leading-tight tracking-tight">About Us</h1>
            <div class="w-16 h-1 bg-emerald-400 mx-auto rounded-full mb-8"></div>
            <p class="text-lg md:text-xl text-gray-300 leading-relaxed max-w-3xl mx-auto">
                {!! data_get($aboutContent, 'about1') !!}
            </p>
        </div>
    </section>

    <!-- History Section -->
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-2 gap-14 items-center">
                <div>
                    <span
                        class="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        <i class="fas fa-history text-xs"></i> Our Journey
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 leading-tight">Our History</h2>
                    <div class="w-12 h-1 bg-emerald-600 rounded-full mb-6"></div>
                    <div class="space-y-5 text-base text-gray-600 leading-relaxed">
                        <p>{!! data_get($aboutContent, 'about2') !!}</p>
                        <div>{!! data_get($aboutContent, 'history') !!}</div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-4 rounded-3xl bg-emerald-50 -z-10"></div>
                    <img src="{{ asset('images/hero_banner.jpg') }}" alt="Coop member Pictures"
                        class="w-full h-80 md:h-[420px] object-cover rounded-2xl shadow-xl" loading="lazy"
                        decoding="async">
                </div>
            </div>
        </div>
    </section>

    <!-- Mission, Vision & Values Section -->
    <section class="py-20 px-4 bg-slate-900">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-14">
                <span
                    class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">
                    <i class="fas fa-compass text-xs"></i> Our Foundation
                </span>
                <h2 class="text-3xl md:text-4xl font-bold text-white mt-2">What Guides Us</h2>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <!-- Mission -->
                <div
                    class="group relative overflow-hidden rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                    <div
                        class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-500 to-emerald-400 rounded-t-2xl">
                    </div>
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20">
                            <i class="fas fa-bullseye text-xl text-emerald-400"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white">Mission</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">
                        {!! data_get($aboutContent, 'mission') !!}
                    </p>
                </div>

                <!-- Vision -->
                <div
                    class="group relative overflow-hidden rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                    <div
                        class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-cyan-500 to-cyan-400 rounded-t-2xl">
                    </div>
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-cyan-500/20">
                            <i class="fas fa-eye text-xl text-cyan-400"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white">Vision</h3>
                    </div>
                    <p class="text-gray-300 leading-relaxed">
                        {!! data_get($aboutContent, 'vision') !!}
                    </p>
                </div>

                <!-- Values -->
                <div
                    class="group relative overflow-hidden rounded-2xl bg-white/5 border border-white/10 p-8 backdrop-blur-sm transition-all hover:bg-white/10">
                    <div
                        class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-slate-400 to-slate-300 rounded-t-2xl">
                    </div>
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10">
                            <i class="fas fa-heart text-xl text-gray-300"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white">Values</h3>
                    </div>
                    <ul class="space-y-3 text-gray-300">
                        @foreach (data_get($aboutContent, 'values', []) as $value)
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-0.5 shrink-0 text-emerald-400 text-xs"></i>
                                <span class="text-sm"><strong
                                        class="text-white">{{ $value['title'] ?? '' }}</strong>{{ isset($value['description']) ? ': ' . $value['description'] : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    @if ($boardVisible && $boardCards->isNotEmpty())
        <!-- Board of Directors Section -->
        <section class="py-20 px-4 bg-white">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span
                        class="mb-4 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        <i class="fas fa-chess-king text-xs"></i> Leadership
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Board of Directors</h2>
                    <p class="text-base text-gray-500 max-w-xl mx-auto">Meet the dedicated leaders guiding our
                        cooperative towards growth and service.</p>
                </div>

                <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-6">
                    @foreach ($boardCards as $member)
                        @php
                            $image = $member['image_url'] ?? '';
                            $imageSrc = filled($image)
                                ? (filter_var($image, FILTER_VALIDATE_URL)
                                    ? $image
                                    : asset(ltrim($image, '/')))
                                : null;
                        @endphp
                        <article
                            class="group overflow-hidden rounded-2xl bg-white border border-gray-200 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-emerald-200">
                            <div class="relative">
                                @if ($imageSrc)
                                    <img src="{{ $imageSrc }}" alt="{{ $member['name'] ?? 'Board member' }}"
                                        class="h-64 w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        loading="lazy" decoding="async">
                                @else
                                    <div
                                        class="flex h-64 items-center justify-center bg-gradient-to-br from-slate-800 to-slate-700">
                                        <div
                                            class="flex h-24 w-24 items-center justify-center rounded-full bg-white/10 ring-4 ring-white/20">
                                            <i class="fas fa-user text-4xl text-white/60"></i>
                                        </div>
                                    </div>
                                @endif
                                <div
                                    class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/50 to-transparent">
                                </div>
                            </div>
                            <div class="p-5 text-center">
                                <h3 class="text-base font-bold text-gray-900 leading-snug">{{ $member['name'] ?? '' }}
                                </h3>
                                <p class="mt-1 text-sm font-semibold text-emerald-600">{{ $member['position'] ?? '' }}
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($officersVisible && $officerCards->isNotEmpty())
        <!-- Officers Section -->
        <section class="py-20 px-4 bg-gray-50">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span
                        class="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        <i class="fas fa-users text-xs"></i> Management
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Our Officers</h2>
                    <p class="text-base text-gray-500 max-w-xl mx-auto">The dedicated professionals managing our daily
                        operations and member services.</p>
                </div>

                <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-6">
                    @foreach ($officerCards as $officer)
                        @php
                            $image = $officer['image_url'] ?? '';
                            $imageSrc = filled($image)
                                ? (filter_var($image, FILTER_VALIDATE_URL)
                                    ? $image
                                    : asset(ltrim($image, '/')))
                                : null;
                        @endphp
                        <article
                            class="group overflow-hidden rounded-2xl bg-white border border-gray-200 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-emerald-200">
                            <div class="relative">
                                @if ($imageSrc)
                                    <img src="{{ $imageSrc }}" alt="{{ $officer['name'] ?? 'Officer' }}"
                                        class="h-64 w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        loading="lazy" decoding="async">
                                @else
                                    <div
                                        class="flex h-64 items-center justify-center bg-gradient-to-br from-emerald-800 to-emerald-700">
                                        <div
                                            class="flex h-24 w-24 items-center justify-center rounded-full bg-white/10 ring-4 ring-white/20">
                                            <i class="fas fa-user-tie text-4xl text-white/60"></i>
                                        </div>
                                    </div>
                                @endif
                                <div
                                    class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/50 to-transparent">
                                </div>
                            </div>
                            <div class="p-5 text-center">
                                <h3 class="text-base font-bold text-gray-900 leading-snug">{{ $officer['name'] ?? '' }}
                                </h3>
                                <p class="mt-1 text-sm font-semibold text-emerald-600">
                                    {{ $officer['position'] ?? '' }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- Member Statements Section -->
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-14">
                <span
                    class="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <i class="fas fa-quote-right text-xs"></i> Testimonials
                </span>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Member Stories</h2>
                <p class="text-base text-gray-500 max-w-xl mx-auto">Hear from our members about their experience with
                    our cooperative.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                @foreach ($statementCards as $statement)
                    <div
                        class="relative overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 p-8 shadow-sm transition-all hover:shadow-md">
                        <div
                            class="absolute -top-3 -left-3 text-[7rem] leading-none font-serif font-bold text-emerald-100 select-none pointer-events-none">
                            &ldquo;</div>
                        <p class="relative text-gray-700 text-sm leading-relaxed mb-8">
                            {!! data_get($memberStatements, $statement['key'] . '.statement') !!}
                        </p>
                        <div class="flex items-center gap-4 border-t border-gray-200 pt-6">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $statement['avatarClass'] }} shadow-sm">
                                <i class="fas fa-user text-sm text-white"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">
                                    {!! data_get($memberStatements, $statement['key'] . '.name') !!}
                                </p>
                                <p class="text-xs text-gray-500">
                                    Member since {!! data_get($memberStatements, $statement['key'] . '.member_since') !!}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section
        class="py-20 px-4 bg-gradient-to-br from-emerald-700 via-emerald-600 to-cyan-700 relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22%3E%3Cg%20fill%3D%22%23ffffff%22%20fill-opacity%3D%220.05%22%3E%3Cpath%20d%3D%22M36%2034v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6%2034v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6%204V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')]">
        </div>
        <div class="relative max-w-3xl mx-auto text-center text-white">
            <h2 class="text-3xl md:text-4xl font-bold mb-4 leading-tight">Ready to Join Our Community?</h2>
            <p class="text-lg text-white/90 mb-10 max-w-xl mx-auto">
                Become part of a cooperative that puts your financial well-being and community growth first.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home_page') }}#Contact"
                    class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-white text-emerald-700 font-semibold rounded-xl shadow-md hover:shadow-xl hover:-translate-y-0.5 transition-all">
                    <i class="fas fa-envelope text-sm"></i> Contact Us
                </a>
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-transparent border-2 border-white/70 text-white font-semibold rounded-xl hover:bg-white/10 hover:border-white hover:-translate-y-0.5 transition-all">
                    <i class="fas fa-sign-in-alt text-sm"></i> Member Login
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')
</body>

</html>
