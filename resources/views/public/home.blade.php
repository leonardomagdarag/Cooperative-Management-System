<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlers Credit Cooperative</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>

<body class="public-page">
    @include('partials.public.topNavbar')

    @php
        $homeContent = data_get($publicContent ?? [], 'home', []);
        $aboutContent = data_get($publicContent ?? [], 'about', []);
        $newsContent = data_get($publicContent ?? [], 'news', []);
        $contactContent = data_get($publicContent ?? [], 'contact', []);
        $memberRegistrationEnabled = (bool) data_get($publicContent ?? [], 'registration.online_member_registration_enabled', true);
        $newsItems = $publicNewsItems->take(6);
    @endphp

    <!-- Hero Section -->
    <section class="relative flex min-h-[92vh] items-center overflow-hidden pt-24">

        <!-- Background Image -->
        <div class="absolute inset-0 z-0">
            {{-- Image --}}
            <img src="{{ asset('images/hero_banner.jpg') }}" alt="Hero background" class="w-full h-full object-cover"
                fetchpriority="high" decoding="async">
            <div class="absolute inset-0 public-gradient-hero"></div>
        </div>

        <div class="public-shell relative z-10 py-20">
            <div class="max-w-3xl">
                <span class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/12 px-4 py-2 text-sm font-semibold text-white backdrop-blur">
                    <i class="fas fa-seedling text-emerald-200"></i>
                    Community-first financial services
                </span>
            <h1 class="mb-6 text-5xl font-extrabold leading-tight text-white md:text-7xl">
                Settlers Credit Cooperative
            </h1>
            <p class="mb-8 max-w-2xl text-lg leading-8 text-white/90 md:text-xl">
                {!! data_get($homeContent, 'hero_subtitle') !!}
            </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-button-public label="Explore membership" icon="fas fa-arrow-down" href="#about" />
                    <a href="{{ $memberRegistrationEnabled ? route('register_page') : '#Contact' }}"
                        class="public-btn public-btn-secondary">
                        Join now
                        <i class="fas fa-user-plus"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- About Preview Section -->
    <section id="about" class="public-section bg-white">
        <div class="public-shell">
            <div class="mx-auto max-w-3xl text-center">
                <span class="public-eyebrow mb-4"><i class="fas fa-leaf"></i> About the cooperative</span>
                <h2 class="public-heading mb-5 text-3xl md:text-5xl">Built around member progress</h2>
                <p class="public-copy mb-5 text-lg text-left md:text-center">
                    {!! data_get($aboutContent, 'about1') !!}
                </p>
                <p class="public-copy mb-8 text-lg text-left md:text-center">
                    {!! data_get($aboutContent, 'about2') !!}
                </p>
            </div>

                <!-- Stats -->
                <div class="my-14 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="public-card public-card-hover p-6">
                        <div class="mb-2 text-3xl font-extrabold text-emerald-700">
                            {!! data_get($homeContent, 'active_members') !!}
                        </div>
                        <div class="text-sm font-medium text-slate-600">Active Members</div>
                    </div>
                    <div class="public-card public-card-hover p-6">
                        <div class="mb-2 text-3xl font-extrabold text-emerald-700">
                            {!! data_get($homeContent, 'total_assets') !!}
                        </div>
                        <div class="text-sm font-medium text-slate-600">Total Assets</div>
                    </div>
                    <div class="public-card public-card-hover p-6">
                        <div class="mb-2 text-3xl font-extrabold text-emerald-700">
                            {!! data_get($homeContent, 'years_service') !!}
                        </div>
                        <div class="text-sm font-medium text-slate-600">Years of Service</div>
                    </div>
                    <div class="public-card public-card-hover p-6">
                        <div class="mb-2 text-3xl font-extrabold text-emerald-700">
                            {!! data_get($homeContent, 'service_done') !!}
                        </div>
                        <div class="text-sm font-medium text-slate-600">Services Done</div>
                    </div>
                </div>

            <div class="text-center">
                <x-button-public label="Learn more about us" icon="fas fa-arrow-right ml-2"
                    href="{{ route('about_page') }}" />
            </div>
        </div>
    </section>

    <!-- Mission & Vision Section -->
    <section class="public-section public-section-soft">
        <div class="public-shell">
            <div class="grid md:grid-cols-2 gap-8">
                <div class="public-card public-card-hover p-8">
                    <div class="w-14 h-14 bg-emerald-700 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-bullseye text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Our Mission</h3>
                    <p class="text-gray-700 leading-relaxed">
                        {!! data_get($aboutContent, 'mission') !!}
                    </p>
                </div>
                <div class="public-card public-card-hover p-8">
                    <div class="w-14 h-14 bg-slate-700 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-eye text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Our Vision</h3>
                    <p class="text-gray-700 leading-relaxed">
                        {!! data_get($aboutContent, 'vision') !!}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Membership Requirements Section -->
    <section class="public-section bg-white">
        <div class="public-shell max-w-4xl">
            <div class="text-center mb-12">
                <span class="public-eyebrow mb-4"><i class="fas fa-id-card"></i> Membership</span>
                <h2 class="public-heading mb-4 text-3xl md:text-5xl">Ready when you are</h2>
                <p class="public-copy text-lg">Join our growing community of members</p>
            </div>
            <div class="public-card p-8 md:p-10">
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center font-bold">
                            1</div>
                        <p class="text-lg text-gray-800 pt-1">Must be at least 18 years old</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center font-bold">
                            2</div>
                        <p class="text-lg text-gray-800 pt-1">Proof of residence in the service area</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center font-bold">
                            3</div>
                        <p class="text-lg text-gray-800 pt-1">Valid identification (e.g., driver's license, passport)
                        </p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center font-bold">
                            4</div>
                        <p class="text-lg text-gray-800 pt-1">Initial deposit to open a savings account</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center font-bold">
                            5</div>
                        <p class="text-lg text-gray-800 pt-1">Agreement to abide by the cooperative's bylaws and
                            policies</p>
                    </div>
                </div>
                <div class="mt-10 text-center">
                    <a href="{{ $memberRegistrationEnabled ? route('register_page') : '#Contact' }}"
                        class="public-btn public-btn-primary">
                        Join Now
                        <i class="fas fa-user-plus ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- News and Announcements Section -->
    <section class="public-section public-section-soft">
        <div class="public-shell">
            <div class="text-center mb-12">
                <span class="public-eyebrow mb-4"><i class="fas fa-newspaper"></i> Updates</span>
                <h2 class="public-heading mb-4 text-3xl md:text-5xl">News & Announcements</h2>
                <p class="public-copy text-lg">Stay updated with our latest news and updates</p>
            </div>

            <!-- Grid of News Cards -->
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                @foreach ($newsItems as $newsItem)
                    <a href="{{ route('news_page') }}"
                        class="group public-card public-card-hover overflow-hidden flex flex-col">
                        {{-- Fixed-height image / colour banner --}}
                        <div class="h-40 w-full shrink-0 overflow-hidden relative
                            @if ($newsItem->image_path) bg-gray-200
                            @elseif ($newsItem->category === 'Achievement') bg-emerald-700
                            @elseif ($newsItem->category === 'Update') bg-blue-700
                            @else bg-slate-700 @endif">
                            @if ($newsItem->image_path)
                                <img src="{{ asset('storage/' . $newsItem->image_path) }}"
                                    alt="{{ $newsItem->title }}"
                                    class="w-full h-full object-cover">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <i class="fas fa-{{ $newsItem->type === 'event' ? 'calendar-alt' : 'bullhorn' }} text-5xl text-white opacity-20"></i>
                                </div>
                            @endif
                        </div>

                        <div class="p-6 flex flex-col flex-1">
                            <div class="flex items-center justify-between mb-4">
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-semibold @if ($newsItem->category === 'Achievement') bg-emerald-100 text-emerald-700 @elseif($newsItem->category === 'Update') bg-blue-100 text-blue-700 @else bg-slate-100 text-slate-700 @endif">
                                    {{ $newsItem->category ?? $newsItem->type_label }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-calendar mr-1"></i>{{ $newsItem->created_at->format('M d, Y') }}
                                </span>
                            </div>

                            <h3
                                class="text-xl font-bold text-gray-900 mb-3 line-clamp-2 group-hover:text-slate-700 transition-colors">
                                {{ $newsItem->title }}
                            </h3>

                            <p class="text-sm text-gray-600 leading-relaxed line-clamp-3 mb-4 flex-1">
                                {{ $newsItem->content }}
                            </p>

                            <div
                                class="flex items-center text-slate-700 font-semibold text-sm group-hover:text-slate-800">
                                Read more
                                <i
                                    class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- View All Button -->
            <div class="text-center">
                <a href="{{ route('news_page') }}"
                    class="public-btn public-btn-primary">
                    View All News & Events
                    <i class="fas fa-newspaper ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- Stories Section - Hidden but kept for future use
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Member Stories</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded mb-6"></div>
                <p class="text-lg text-gray-600">Hear from our valued members</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <img src="https://picsum.photos/seed/picsum/200" alt="Random Pic 1"
                        class="h-48 w-full object-cover">

                    <div class="p-6">
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Building Dreams Together</h4>
                        <p class="text-gray-600 mb-4">How our cooperative helped me achieve my dream of owning a home.
                        </p>
                        <a href="{{ route('stories_page') }}"
                            class="text-primary1 hover:text-emerald-700 font-semibold inline-flex items-center">
                            Read More <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                    </div>
                </div>
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <img src="https://picsum.photos/seed/picsum/200" alt="Random Pic 2"
                        class="h-48 w-full object-cover">
                    <div class="p-6">
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Growing My Business</h4>
                        <p class="text-gray-600 mb-4">The support and guidance that helped expand my small business.
                        </p>
                        <a href="{{ route('stories_page') }}"
                            class="text-primary2 hover:text-cyan-700 font-semibold inline-flex items-center">
                            Read More <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                    </div>
                </div>
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <img src="https://picsum.photos/seed/picsum/200" alt="Random Pic 3"
                        class="h-48 w-full object-cover">

                    <div class="p-6">
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Securing My Future</h4>
                        <p class="text-gray-600 mb-4">Financial planning services that gave me peace of mind.</p>
                        <a href="{{ route('stories_page') }}"
                            class="text-primary1 hover:text-emerald-700 font-semibold inline-flex items-center">
                            Read More <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="text-center mt-12">
                <a href="{{ route('stories_page') }}"
                    class="inline-flex items-center px-8 py-4 bg-white hover:bg-gray-50 text-primary1 font-semibold rounded-full shadow-lg hover:shadow-xl transition-all border-2 border-emerald-200">
                    View All Stories
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>
    --}}

    <!-- Contact Section -->
    <section id="Contact" class="public-section bg-white">
        <div class="public-shell">
            <div class="text-center mb-12">
                <span class="public-eyebrow mb-4"><i class="fas fa-envelope"></i> Contact</span>
                <h2 class="public-heading mb-4 text-3xl md:text-5xl">Get In Touch</h2>
                <p class="public-copy text-lg">We're here to help you achieve your financial goals</p>
            </div>
            <div class="grid md:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="public-card p-8">
                    <h3 class="text-2xl text-center font-bold text-gray-900 mb-8">Send us a Message</h3>
                    @if (session('success'))
                        <div
                            class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-sm">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('contact.send') }}" method="POST" class="space-y-6">
                        @csrf
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Your
                                Name</label>
                            <input type="text" name="name" id="name" required
                                value="{{ old('name') }}"
                                class="public-input">
                            @error('name')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Your
                                Email</label>
                            <input type="email" name="email" id="email" required
                                value="{{ old('email') }}"
                                class="public-input">
                            @error('email')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Your
                                Message</label>
                            <textarea name="message" id="message" rows="4" required
                                class="public-input resize-none">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            @if (config('services.turnstile.site_key'))
                                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"
                                    data-theme="light" data-callback="turnstileCallback"></div>
                            @else
                                <p class="text-sm text-red-600">Captcha is not configured. Please contact support.</p>
                            @endif
                            @error('cf-turnstile-response')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" id="contactSubmitBtn"
                            class="public-btn public-btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                            Send Message
                            <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </form>
                </div>

                <!-- Contact Information -->
                <div>
                    <div class="public-card p-8 mb-3">
                        <h3 class="text-2xl font-bold text-center text-gray-900 mb-6">Contact Information</h3>
                        <div class="space-y-8">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-map-marker-alt text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-1">Address</h4>
                                    <a href="#googlemaps" class="text-slate-700 hover:text-slate-900">
                                        {!! data_get($contactContent, 'address') !!}
                                    </a>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-emerald-700 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-phone text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-1">Phone Numbers</h4>
                                    <p class="text-gray-600">{!! data_get($contactContent, 'phone') !!}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-envelope text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-1">Email</h4>
                                    <a href="#gmail" class="text-slate-700 hover:text-slate-900">
                                        {!! data_get($contactContent, 'email') !!}
                                    </a>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-cyan-500 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-1">Office Hours</h4>
                                    <p class="text-gray-600">
                                        {!! data_get($contactContent, 'office_hours') !!}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map Placeholder -->
                    @if (filled(data_get($contactContent, 'map_embed')))
                        <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
                            <iframe src="{{ data_get($contactContent, 'map_embed') }}" class="h-64 w-full"
                                style="border:0;" allowfullscreen loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    @else
                        <div class="bg-gray-200 rounded-3xl h-64 flex items-center justify-center">
                            <p class="text-gray-500">Map Location</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')

    <script>
        // Contact form: enable submit button only when Turnstile succeeds
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.querySelector('form[action="{{ route('contact.send') }}"]');
            const contactBtn = document.getElementById('contactSubmitBtn');

            if (contactForm && contactBtn) {
                // Disable initially
                contactBtn.disabled = true;

                // Listen for Turnstile success
                window.turnstileCallback = function() {
                    contactBtn.disabled = false;
                };

                // Re-disable on form reset or error
                contactForm.addEventListener('submit', function(e) {
                    // Button stays enabled for submission
                });
            }
        });
    </script>

    <style>
        @keyframes blob {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(30px, -50px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
        }

        .animate-blob {
            animation: blob 7s infinite;
        }

        .animation-delay-2000 {
            animation-delay: 2s;
        }

        .animation-delay-4000 {
            animation-delay: 4s;
        }
    </style>
</body>

</html>
