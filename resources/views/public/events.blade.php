<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css'])
    <title>News & Events - Settlers Credit Cooperative</title>
</head>

<body class="bg-white">
    @include('partials.public.topNavbar')

    <!-- Hero Header Section -->
    <section class="relative pt-32 pb-16 px-4 bg-gray-50 overflow-hidden">
        <div class="relative max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">News & Events</h1>
            <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-8"></div>
            <p class="text-xl text-gray-700 leading-relaxed">
                Stay informed with the latest news, announcements, and upcoming events from Settlers Credit Cooperative.
            </p>
        </div>
    </section>

    <!-- News & Announcements Section -->
    <section class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="mb-12">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                        <i class="fas fa-newspaper text-white text-xl"></i>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Latest News & Announcements</h2>
                </div>
                <div class="w-28 h-1 bg-emerald-700 rounded-full"></div>
            </div>

            <div class="space-y-6">
                <!-- News Item 1 -->
                <div
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                    <div class="md:flex">
                        <div
                            class="md:w-1/4 h-48 md:h-auto bg-slate-700 relative overflow-hidden flex items-center justify-center">
                            <i class="fas fa-bullhorn text-5xl text-white opacity-30"></i>
                        </div>
                        <div class="p-8 md:w-3/4">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="px-4 py-1 bg-slate-100 text-slate-700 rounded-full text-sm font-semibold">
                                    Announcement
                                </span>
                                <span class="text-sm text-gray-500">
                                    <i class="fas fa-calendar mr-2"></i>March 5, 2026
                                </span>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">New Interest Rates for Savings Accounts
                            </h3>
                            <p class="text-gray-600 leading-relaxed mb-4">
                                We're excited to announce increased interest rates on all savings accounts starting
                                April 1, 2026.
                                Regular savings accounts will now earn 3.5% annually, while time deposits will earn up
                                to 5.5%
                                depending on the term. This is part of our commitment to providing better returns for
                                our members.
                            </p>
                            <a href="#"
                                class="text-emerald-600 hover:text-emerald-700 font-semibold inline-flex items-center">
                                Read more <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- News Item 2 -->
                <div
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                    <div class="md:flex">
                        <div
                            class="md:w-1/4 h-48 md:h-auto bg-emerald-700 relative overflow-hidden flex items-center justify-center">
                            <i class="fas fa-trophy text-5xl text-white opacity-30"></i>
                        </div>
                        <div class="p-8 md:w-3/4">
                            <div class="flex items-center gap-3 mb-4">
                                <span
                                    class="px-4 py-1 bg-emerald-100 text-emerald-700 rounded-full text-sm font-semibold">
                                    Achievement
                                </span>
                                <span class="text-sm text-gray-500">
                                    <i class="fas fa-calendar mr-2"></i>February 28, 2026
                                </span>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Cooperative Reaches 1,000 Active Members
                            </h3>
                            <p class="text-gray-600 leading-relaxed mb-4">
                                We're proud to announce that Settlers Credit Cooperative has reached a milestone of
                                1,000
                                active members! This achievement reflects our community's trust and commitment to
                                cooperative
                                principles. Thank you to all our members for being part of this journey.
                            </p>
                            <a href="#"
                                class="text-slate-700 hover:text-slate-900 font-semibold inline-flex items-center">
                                Read more <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- News Item 3 -->
                <div
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                    <div class="md:flex">
                        <div
                            class="md:w-1/4 h-48 md:h-auto bg-slate-700 relative overflow-hidden flex items-center justify-center">
                            <i class="fas fa-mobile-alt text-5xl text-white opacity-30"></i>
                        </div>
                        <div class="p-8 md:w-3/4">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="px-4 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-semibold">
                                    Update
                                </span>
                                <span class="text-sm text-gray-500">
                                    <i class="fas fa-calendar mr-2"></i>February 15, 2026
                                </span>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Mobile App Update: New Features Available
                            </h3>
                            <p class="text-gray-600 leading-relaxed mb-4">
                                Our mobile banking app has been updated with new features including instant loan
                                application
                                status tracking, digital receipts, and enhanced security features. Update your app today
                                to
                                enjoy these improvements.
                            </p>
                            <a href="#"
                                class="text-slate-700 hover:text-slate-900 font-semibold inline-flex items-center">
                                Read more <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Events Section (Combined Upcoming & Previous) -->
    <section class="py-16 px-4 bg-gray-50">
        <div class="max-w-6xl mx-auto">
            <div class="mb-12">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-white text-xl"></i>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-800">Events</h2>
                </div>
                <div class="w-24 h-1 bg-emerald-700 rounded"></div>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Upcoming Event 1 -->
                <div
                    class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200">
                    <div class="h-48 bg-slate-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-7xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 left-4 bg-emerald-700 text-white px-4 py-2 rounded-lg shadow-md">
                            <p class="text-xs font-bold uppercase">Upcoming</p>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                            <p class="text-sm font-bold text-slate-700">July 15, 2026</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-book text-emerald-700"></i>
                            <span class="text-sm font-semibold text-emerald-700">Workshop</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-3">Financial Literacy Workshop</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                            Join us for an informative workshop on financial literacy, covering budgeting, saving, and
                            investing tips.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock"></i>
                                <span>9:00 AM</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Main Office</span>
                            </div>
                        </div>
                        <button
                            class="w-full px-6 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            Register Now
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Upcoming Event 2 -->
                <div
                    class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200">
                    <div class="h-48 bg-emerald-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-users text-7xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 left-4 bg-slate-700 text-white px-4 py-2 rounded-lg shadow-md">
                            <p class="text-xs font-bold uppercase">Upcoming</p>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                            <p class="text-sm font-bold text-emerald-700">Aug 10, 2026</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-handshake text-slate-700"></i>
                            <span class="text-sm font-semibold text-slate-700">Annual Meeting</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-3">Annual Member Meeting</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                            All members are invited to discuss the cooperative's performance, future plans, and vote on
                            important matters.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock"></i>
                                <span>2:00 PM</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Conference Hall</span>
                            </div>
                        </div>
                        <button
                            class="w-full px-6 py-2.5 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            Register Now
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Upcoming Event 3 -->
                <div
                    class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200">
                    <div class="h-48 bg-slate-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-heart text-7xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 left-4 bg-emerald-700 text-white px-4 py-2 rounded-lg shadow-md">
                            <p class="text-xs font-bold uppercase">Upcoming</p>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                            <p class="text-sm font-bold text-slate-700">Sep 5, 2026</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-hands-helping text-emerald-700"></i>
                            <span class="text-sm font-semibold text-emerald-700">Community Service</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-3">Community Outreach Program</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                            Participate in our community outreach program aimed at supporting local initiatives and
                            giving back.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock"></i>
                                <span>8:00 AM</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Community Center</span>
                            </div>
                        </div>
                        <button
                            class="w-full px-6 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            Register Now
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Previous Event 1 -->
                <div
                    class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200 opacity-90">
                    <div class="h-48 bg-gray-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-gift text-7xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 left-4 bg-gray-600 text-white px-4 py-2 rounded-lg shadow-md">
                            <p class="text-xs font-bold uppercase">Completed</p>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                            <p class="text-sm font-bold text-gray-600">January 20, 2026</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-party-horn text-gray-500"></i>
                            <span class="text-sm font-semibold text-gray-600">Celebration</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-3">Member Appreciation Day</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                            We celebrated our members with fun activities, giveaways, and refreshments to show our
                            appreciation.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                                <span>Event Completed</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Previous Event 2 -->
                <div
                    class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200 opacity-90">
                    <div class="h-48 bg-gray-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-piggy-bank text-7xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 left-4 bg-gray-600 text-white px-4 py-2 rounded-lg shadow-md">
                            <p class="text-xs font-bold uppercase">Completed</p>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                            <p class="text-sm font-bold text-gray-600">December 2025</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-coins text-gray-500"></i>
                            <span class="text-sm font-semibold text-gray-600">Campaign</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-3">Holiday Savings Campaign</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                            Our holiday campaign encouraged members to save more with special incentives and rewards for
                            savings goals.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-cyan-500"></i>
                                <span>Event Completed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Subscription Section -->
    <section class="py-16 px-4 bg-white">
        <div class="max-w-4xl mx-auto text-center">
            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-10 shadow-sm">
                <div class="inline-block p-4 bg-white rounded-full shadow-md mb-6">
                    <i class="fas fa-envelope-open-text text-3xl text-emerald-700"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mb-4">Subscribe to Our Newsletter</h2>
                <p class="text-base text-gray-700 mb-8">
                    Stay updated with the latest news, announcements, and upcoming events. Subscribe to our newsletter
                    and never miss important updates from Settlers Credit Cooperative.
                </p>
                <div class="max-w-md mx-auto mb-6">
                    <div class="flex flex-col gap-4">
                        <input type="text" placeholder="Your full name"
                            class="w-full px-6 py-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all">
                        <input type="email" placeholder="Your email address"
                            class="w-full px-6 py-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all">
                        <button
                            class="w-full px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            Subscribe Now
                            <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </div>
                </div>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-lock mr-2"></i>We respect your privacy. Unsubscribe at any time.
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')
</body>

</html>

<!-- Hero Header Section -->
<section class="relative pt-32 pb-16 px-4 bg-gray-50 overflow-hidden">
    <div class="relative max-w-4xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-slate-800 mb-6">Events & Activities</h1>
        <div class="w-20 h-1 bg-emerald-700 mx-auto rounded mb-6"></div>
        <p class="text-lg text-gray-700 leading-relaxed">
            Stay updated with the latest events and activities organized by Settlers Credit Cooperative. We are
            committed to fostering community engagement and providing opportunities for our members to connect and grow.
        </p>
    </div>
</section>

<!-- Upcoming Events Section -->
<section class="py-16 px-4 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="mb-12">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-emerald-700 rounded-full flex items-center justify-center">
                    <i class="fas fa-star text-white text-xl"></i>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-800">Upcoming Events</h2>
            </div>
            <div class="w-24 h-1 bg-emerald-700 rounded"></div>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <!-- Event Card 1 -->
            <div
                class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200">
                <div class="h-48 bg-slate-700 relative overflow-hidden">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-7xl text-white opacity-20"></i>
                    </div>
                    <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                        <p class="text-sm font-bold text-slate-700">July 15, 2024</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-book text-emerald-700"></i>
                        <span class="text-sm font-semibold text-emerald-700">Workshop</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-3">Financial Literacy Workshop</h3>
                    <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                        Join us for an informative workshop on financial literacy, covering budgeting, saving, and
                        investing tips to help you manage your finances effectively.
                    </p>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-clock"></i>
                            <span>9:00 AM</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Main Office</span>
                        </div>
                    </div>
                    <button
                        class="w-full px-6 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                        Register Now
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>

            <!-- Event Card 2 -->
            <div
                class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200">
                <div class="h-48 bg-emerald-700 relative overflow-hidden">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <i class="fas fa-users text-7xl text-white opacity-20"></i>
                    </div>
                    <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                        <p class="text-sm font-bold text-emerald-700">Aug 10, 2024</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-handshake text-slate-700"></i>
                        <span class="text-sm font-semibold text-slate-700">Annual Meeting</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-3">Annual Member Meeting</h3>
                    <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                        All members are invited to attend our Annual Member Meeting to discuss the cooperative's
                        performance, future plans, and to vote on important matters.
                    </p>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-clock"></i>
                            <span>2:00 PM</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Conference Hall</span>
                        </div>
                    </div>
                    <button
                        class="w-full px-6 py-2.5 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                        Register Now
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>

            <!-- Event Card 3 -->
            <div
                class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all border border-gray-200">
                <div class="h-48 bg-slate-700 relative overflow-hidden">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <i class="fas fa-heart text-7xl text-white opacity-20"></i>
                    </div>
                    <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg">
                        <p class="text-sm font-bold text-slate-700">Sep 5, 2024</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-hands-helping text-emerald-700"></i>
                        <span class="text-sm font-semibold text-emerald-700">Community Service</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-3">Community Outreach Program</h3>
                    <p class="text-gray-600 mb-4 leading-relaxed text-sm">
                        Participate in our community outreach program aimed at supporting local initiatives and
                        giving back to the community we serve.
                    </p>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-clock"></i>
                            <span>8:00 AM</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Community Center</span>
                        </div>
                    </div>
                    <button
                        class="w-full px-6 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                        Register Now
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Previous Events Section -->
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="mb-12">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center">
                    <i class="fas fa-history text-white text-xl"></i>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-800">Previous Events</h2>
            </div>
            <div class="w-24 h-1 bg-slate-700 rounded"></div>
        </div>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Previous Event Card 1 -->
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                <div class="md:flex">
                    <div class="md:w-2/5 h-48 md:h-auto bg-gray-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-gift text-6xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6 md:w-3/5">
                        <div
                            class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-sm font-semibold mb-3">
                            <i class="fas fa-check-circle text-emerald-700 mr-1"></i> Completed
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Member Appreciation Day</h3>
                        <p class="text-sm text-gray-500 mb-3">
                            <i class="fas fa-calendar mr-2"></i>May 20, 2024
                        </p>
                        <p class="text-gray-600 leading-relaxed text-sm">
                            We celebrated our members with a day full of fun activities, giveaways, and refreshments
                            to show our appreciation for their continued support.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Previous Event Card 2 -->
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                <div class="md:flex">
                    <div class="md:w-2/5 h-48 md:h-auto bg-gray-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-piggy-bank text-6xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6 md:w-3/5">
                        <div
                            class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-sm font-semibold mb-3">
                            <i class="fas fa-check-circle text-slate-700 mr-1"></i> Completed
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Spring Savings Campaign</h3>
                        <p class="text-sm text-gray-500 mb-3">
                            <i class="fas fa-calendar mr-2"></i>April 1-30, 2024
                        </p>
                        <p class="text-gray-600 leading-relaxed text-sm">
                            Our Spring Savings Campaign encouraged members to save more with special incentives and
                            rewards for reaching their savings goals.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 px-4 bg-white">
    <div class="max-w-4xl mx-auto text-center">
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-10 shadow-sm">
            <div class="inline-block p-4 bg-white rounded-full shadow-md mb-6">
                <i class="fas fa-bell text-3xl text-emerald-700"></i>
            </div>
            <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mb-4">Stay Informed</h2>
            <p class="text-base text-gray-700 mb-8">
                Never miss an event! Subscribe to our newsletter to receive updates about upcoming events and
                activities.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center max-w-md mx-auto">
                <input type="email" placeholder="Your email address"
                    class="flex-1 px-6 py-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all">
                <button
                    class="px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                    Subscribe
                    <i class="fas fa-paper-plane ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
@include('partials.public.footer')
</body>

</html>
