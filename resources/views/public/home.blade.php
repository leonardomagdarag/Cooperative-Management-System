<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlers Credit Cooperative</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-white">
    @include('partials.public.topNavbar')

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-20">

        <!-- Background Image -->
        <div class="absolute inset-0 z-0">
            {{-- Image --}}
            <img src="{{ asset('images/hero_banner.jpg') }}" alt="Hero background" class="w-full h-full object-cover"
                fetchpriority="high" decoding="async">
            <!-- Solid Overlay -->
            <div class="absolute inset-0 bg-slate-900/50"></div>
        </div>

        <div class="relative z-10 text-center px-4 py-20 max-w-6xl mx-auto">
            <h1 class="text-4xl md:text-6xl font-bold text-white mb-6 leading-tight animate_animated animate_fadeInUp">
                <span class="text-white">Settlers</span><br>
                Credit Cooperative
            </h1>
            <p class="text-lg md:text-xl text-gray-100 mb-12 max-w-3xl mx-auto">
                {!! config('content.home.hero_subtitle') !!}
            </p>
            <x-button-public label="Learn more" icon="fas fa-arrow-down ml-2" href="#about"
                extraClass="animate_animated animate__bounce" />
        </div>
    </section>

    <!-- About Preview Section -->
    <section id="about" class="py-16 px-4 bg-white">
        <div class="max-w-2xl mx-auto">
            <div class="text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">About Us</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-8"></div>
                <p class="text-lg text-gray-700 leading-relaxed mb-6 text-justify">
                    {!! config('content.about.about1') !!}
                </p>
                <p class="text-lg text-gray-700 leading-relaxed mb-8 text-justify">
                    {!! config('content.about.about2') !!}
                </p>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16 mb-16">
                    <div
                        class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="text-3xl font-bold text-slate-700 mb-2">
                            {!! config('content.home.active_members') !!}
                        </div>
                        <div class="text-sm text-gray-600">Active Members</div>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="text-3xl font-bold text-slate-700 mb-2">
                            {!! config('content.home.total_assets') !!}
                        </div>
                        <div class="text-sm text-gray-600">Total Assets</div>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="text-3xl font-bold text-slate-700 mb-2">
                            {!! config('content.home.years_service') !!}
                        </div>
                        <div class="text-sm text-gray-600">Years of Service</div>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="text-3xl font-bold text-slate-700 mb-2">
                            {!! config('content.home.service_done') !!}
                        </div>
                        <div class="text-sm text-gray-600">Services Done</div>
                    </div>
                </div>

                <x-button-public label="Learn more about us" icon="fas fa-arrow-right ml-2"
                    href="{{ route('about_page') }}" />
            </div>

        </div>
    </section>

    <!-- Mission & Vision Section -->
    <section class="py-16 px-4 bg-gray-50">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-2 gap-8">
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition-all">
                    <div class="w-14 h-14 bg-emerald-700 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-bullseye text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Our Mission</h3>
                    <p class="text-gray-700 leading-relaxed">
                        {!! config('content.about.mission') !!}
                    </p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition-all">
                    <div class="w-14 h-14 bg-slate-700 rounded-xl flex items-center justify-center mb-6">
                        <i class="fas fa-eye text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Our Vision</h3>
                    <p class="text-gray-700 leading-relaxed">
                        {!! config('content.about.vision') !!}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Membership Requirements Section -->
    <section class="py-16 px-4 bg-white">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Membership Requirements</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-6"></div>
                <p class="text-lg text-gray-600">Join our growing community of members</p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-8 md:p-10 shadow-sm">
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold">
                            1</div>
                        <p class="text-lg text-gray-800 pt-1">Must be at least 18 years old</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold">
                            2</div>
                        <p class="text-lg text-gray-800 pt-1">Proof of residence in the service area</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold">
                            3</div>
                        <p class="text-lg text-gray-800 pt-1">Valid identification (e.g., driver's license, passport)
                        </p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold">
                            4</div>
                        <p class="text-lg text-gray-800 pt-1">Initial deposit to open a savings account</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-10 h-10 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold">
                            5</div>
                        <p class="text-lg text-gray-800 pt-1">Agreement to abide by the cooperative's bylaws and
                            policies</p>
                    </div>
                </div>
                <div class="mt-10 text-center">
                    <a href="{{ route('register_page') }}"
                        class="inline-flex items-center px-6 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-102">
                        Join Now
                        <i class="fas fa-user-plus ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- News and Announcements Section -->
    <section class="py-16 px-4 bg-gray-50">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">News & Announcements</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-6"></div>
                <p class="text-lg text-gray-600">Stay updated with our latest news and updates</p>
            </div>

            <!-- Grid of News Cards -->
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                @foreach (config('content.news.news_items') as $index => $newsItem)
                    @if ($index < 6)
                        <!-- News Card {{ $index + 1 }} -->
                        <a href="{{ route('news_page') }}"
                            class="group bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                            <!-- Category Header -->
                            <div
                                class="h-1 
                            @if ($newsItem['category'] === 'Announcement') bg-slate-700
                            @elseif($newsItem['category'] === 'Achievement') bg-emerald-700
                            @else bg-gray-700 @endif">
                            </div>

                            <div class="p-6">
                                <!-- Category Badge & Date -->
                                <div class="flex items-center justify-between mb-4">
                                    <span
                                        class="px-3 py-1 
                                    @if ($newsItem['category'] === 'Announcement') bg-slate-100 text-slate-700
                                    @elseif($newsItem['category'] === 'Achievement') bg-emerald-100 text-emerald-700
                                    @else bg-gray-100 text-gray-700 @endif
                                    rounded-full text-xs font-semibold">
                                        {{ $newsItem['category'] }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i>{{ $newsItem['date'] }}
                                    </span>
                                </div>

                                <!-- Title -->
                                <h3
                                    class="text-xl font-bold text-gray-900 mb-3 line-clamp-2 group-hover:text-slate-700 transition-colors">
                                    {{ $newsItem['title'] }}
                                </h3>

                                <!-- Description -->
                                <p class="text-sm text-gray-600 leading-relaxed line-clamp-3 mb-4">
                                    {{ $newsItem['description'] }}
                                </p>

                                <!-- Read More -->
                                <div
                                    class="flex items-center text-slate-700 font-semibold text-sm group-hover:text-slate-800">
                                    Read more
                                    <i
                                        class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>

            <!-- View All Button -->
            <div class="text-center">
                <a href="{{ route('news_page') }}"
                    class="inline-flex items-center px-6 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-102">
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
    <section id="Contact" class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Get In Touch</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-6"></div>
                <p class="text-lg text-gray-600">We're here to help you achieve your financial goals</p>
            </div>
            <div class="grid md:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-8 shadow-sm">
                    <h3 class="text-2xl text-center font-bold text-gray-900 mb-8">Send us a Message</h3>
                    <form action="#" method="post" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Your
                                Name</label>
                            <input type="text" name="name" id="name" required
                                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Your
                                Email</label>
                            <input type="email" name="email" id="email" required
                                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all">
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Your
                                Message</label>
                            <textarea name="message" id="message" rows="4" required
                                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all resize-none"></textarea>
                        </div>
                        <button type="submit"
                            class="w-full px-6 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-[0.99]">
                            Send Message
                            <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </form>
                </div>

                <!-- Contact Information -->
                <div>
                    <div class="bg-gray-50 border border-gray-200 rounded-2xl p-8 shadow-sm mb-3">
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
                                        {!! config('content.contact.address') !!}
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
                                    <p class="text-gray-600">{!! config('content.contact.phone') !!}</p>
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
                                        {!! config('content.contact.email') !!}
                                    </a>
                                </div>
                            </div>
                            {{-- <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 bg-cyan-500 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-1">Office Hours</h4>
                                    <p class="text-gray-600">
                                        {!! config('content.contact.office_hours') !!}
                                    </p>
                                </div>
                            </div> --}}
                        </div>
                    </div>

                    <!-- Map Placeholder -->
                    <div class="bg-gray-200 rounded-3xl h-64 flex items-center justify-center">
                        <p class="text-gray-500">Map Location</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')

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
