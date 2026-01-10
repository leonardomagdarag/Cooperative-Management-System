<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Events - Settlers Credit Cooperative</title>
</head>

<body class="bg-white">
    @include('partials.public.topNavbar')

    <!-- Hero Header Section -->
    <section class="relative pt-40 pb-20 px-4 bg-linear-to-br from-emerald-50 via-cyan-50 to-blue-50 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-40 -right-40 w-96 h-96 bg-emerald-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20">
            </div>
            <div
                class="absolute -bottom-40 -left-40 w-96 h-96 bg-cyan-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20">
            </div>
        </div>
        <div class="relative max-w-4xl mx-auto text-center">
            <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6">Events & Activities</h1>
            <div class="w-24 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 mx-auto rounded-full mb-8"></div>
                        <p class="text-xl text-gray-700 leading-relaxed">
                Stay updated with the latest events and activities organized by Settlers Credit Cooperative. We are
                committed to fostering community engagement and providing opportunities for our members to connect and grow.
            </p>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="mb-16">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-star text-white text-xl"></i>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900">Upcoming Events</h2>
                </div>
                <div class="w-32 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 rounded-full"></div>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Event Card 1 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-gray-100">
                    <div class="h-56 bg-linear-to-br from-emerald-400 to-cyan-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-8xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-full">
                            <p class="text-sm font-bold text-emerald-600">July 15, 2024</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-book text-emerald-500"></i>
                            <span class="text-sm font-semibold text-emerald-600">Workshop</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Financial Literacy Workshop</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            Join us for an informative workshop on financial literacy, covering budgeting, saving, and
                            investing tips to help you manage your finances effectively.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-6">
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
                            class="w-full px-6 py-3 bg-linear-to-r from-emerald-500 to-cyan-500 hover:from-emerald-600 hover:to-cyan-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            Register Now
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Event Card 2 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-gray-100">
                    <div class="h-56 bg-linear-to-br from-cyan-400 to-blue-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-users text-8xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-full">
                            <p class="text-sm font-bold text-cyan-600">Aug 10, 2024</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-handshake text-cyan-500"></i>
                            <span class="text-sm font-semibold text-cyan-600">Annual Meeting</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Annual Member Meeting</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            All members are invited to attend our Annual Member Meeting to discuss the cooperative's
                            performance, future plans, and to vote on important matters.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-6">
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
                            class="w-full px-6 py-3 bg-linear-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            Register Now
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Event Card 3 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-gray-100">
                    <div class="h-56 bg-linear-to-br from-emerald-400 to-emerald-500 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-heart text-8xl text-white opacity-20"></i>
                        </div>
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-full">
                            <p class="text-sm font-bold text-emerald-600">Sep 5, 2024</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-hands-helping text-emerald-500"></i>
                            <span class="text-sm font-semibold text-emerald-600">Community Service</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Community Outreach Program</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            Participate in our community outreach program aimed at supporting local initiatives and
                            giving back to the community we serve.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-6">
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
                            class="w-full px-6 py-3 bg-linear-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            Register Now
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Previous Events Section -->
    <section class="py-20 px-4 bg-linear-to-br from-emerald-50 to-cyan-50">
        <div class="max-w-6xl mx-auto">
            <div class="mb-16">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-cyan-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-history text-white text-xl"></i>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900">Previous Events</h2>
                </div>
                <div class="w-32 h-1 bg-linear-to-r from-cyan-500 to-emerald-500 rounded-full"></div>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Previous Event Card 1 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="md:flex">
                        <div
                            class="md:w-2/5 h-64 md:h-auto bg-linear-to-br from-cyan-300 to-emerald-300 relative overflow-hidden">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-gift text-7xl text-white opacity-20"></i>
                            </div>
                        </div>
                        <div class="p-6 md:w-3/5">
                            <div
                                class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-semibold mb-3">
                                <i class="fas fa-check-circle text-emerald-500 mr-1"></i> Completed
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Member Appreciation Day</h3>
                            <p class="text-sm text-gray-500 mb-3">
                                <i class="fas fa-calendar mr-2"></i>May 20, 2024
                            </p>
                            <p class="text-gray-600 leading-relaxed">
                                We celebrated our members with a day full of fun activities, giveaways, and refreshments
                                to show our appreciation for their continued support.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Previous Event Card 2 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="md:flex">
                        <div
                            class="md:w-2/5 h-64 md:h-auto bg-linear-to-br from-emerald-300 to-cyan-300 relative overflow-hidden">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-piggy-bank text-7xl text-white opacity-20"></i>
                            </div>
                        </div>
                        <div class="p-6 md:w-3/5">
                            <div
                                class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-semibold mb-3">
                                <i class="fas fa-check-circle text-cyan-500 mr-1"></i> Completed
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Spring Savings Campaign</h3>
                            <p class="text-sm text-gray-500 mb-3">
                                <i class="fas fa-calendar mr-2"></i>April 1-30, 2024
                            </p>
                            <p class="text-gray-600 leading-relaxed">
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
    <section class="py-20 px-4 bg-white">
        <div class="max-w-4xl mx-auto text-center">
            <div class="bg-linear-to-br from-emerald-50 to-cyan-50 rounded-3xl p-12 shadow-xl">
                <div class="inline-block p-4 bg-white rounded-full shadow-lg mb-6">
                    <i class="fas fa-bell text-4xl text-emerald-600"></i>
                </div>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Stay Informed</h2>
                <p class="text-lg text-gray-700 mb-8">
                    Never miss an event! Subscribe to our newsletter to receive updates about upcoming events and
                    activities.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center max-w-md mx-auto">
                    <input type="email" placeholder="Your email address"
                        class="flex-1 px-6 py-4 bg-white border border-gray-200 rounded-full focus:ring-2 focus:ring-emerald-400 focus:border-transparent outline-none transition-all">
                    <button
                        class="px-8 py-4 bg-linear-to-r from-emerald-500 to-cyan-500 hover:from-emerald-600 hover:to-cyan-600 text-white font-semibold rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
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
