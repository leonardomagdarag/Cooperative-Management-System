<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Member Stories - Settlers Credit Cooperative</title>
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
        <div class="relative max-w-4xl mx-auto text-center ">
            <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6 ">Member Stories</h1>
            <div class="w-24 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 mx-auto rounded-full mb-8"></div>
            <p class="text-xl text-gray-700 leading-relaxed">
                Discover how Settlers Credit Cooperative has made a difference in the lives of our members through
                financial empowerment and community support.
            </p>
        </div>
    </section>

    <!-- Featured Story Section -->
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="inline-block px-4 py-2 bg-emerald-100 text-emerald-700 rounded-full text-sm font-semibold mb-8">
                Featured Story
            </div>
            <div class="bg-linear-to-br from-emerald-50 to-cyan-50 rounded-3xl overflow-hidden shadow-2xl">
                <div class="md:flex">
                    <div class="md:w-1/2 h-96 bg-linear-to-br from-emerald-400 to-cyan-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-home text-9xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="md:w-1/2 p-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">From Dream to Reality: Maria's
                            Home Story</h2>
                        <p class="text-lg text-gray-700 leading-relaxed mb-6">
                            "After 15 years of renting, I finally achieved my dream of owning a home thanks to Settlers
                            Credit Cooperative. Their flexible loan options and supportive staff made the impossible
                            possible.
                            Today, my family has a place we can truly call our own."
                        </p>
                        <div class="flex items-center gap-4 mb-6">
                            <div
                                class="w-16 h-16 bg-linear-to-br from-emerald-500 to-cyan-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-2xl text-white"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">Maria Santos</p>
                                <p class="text-sm text-gray-600">Member since 2010</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="px-4 py-2 bg-white rounded-full text-sm text-emerald-600 font-semibold">Home
                                Loan</span>
                            <span class="px-4 py-2 bg-white rounded-full text-sm text-cyan-600 font-semibold">Success
                                Story</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stories Grid Section -->
    <section class="py-20 px-4 bg-linear-to-br from-cyan-50 to-emerald-50">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">More Success Stories</h2>
                <div class="w-24 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 mx-auto rounded-full"></div>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Story Card 1 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="h-56 bg-linear-to-br from-emerald-400 to-cyan-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-store text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Growing My Business</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "The business loan I received helped me expand from a small stall to a full-fledged store.
                            The cooperative believed in my vision when others didn't."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Juan Cruz</p>
                                <p class="text-xs text-gray-500">Business Owner</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full text-xs font-semibold">Business
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 2 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="h-56 bg-linear-to-br from-cyan-400 to-blue-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Education for My Children</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "Thanks to the education loan, my three children are now in college pursuing their dreams.
                            This cooperative made education accessible for our family."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-cyan-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Rosa Reyes</p>
                                <p class="text-xs text-gray-500">Parent & Member</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-cyan-100 text-cyan-600 rounded-full text-xs font-semibold">Education
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 3 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="h-56 bg-linear-to-br from-emerald-400 to-emerald-500 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-piggy-bank text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Securing My Future</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "The savings program helped me build an emergency fund and plan for retirement. I feel
                            financially secure knowing I'm prepared for the future."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Pedro Garcia</p>
                                <p class="text-xs text-gray-500">Retired Member</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full text-xs font-semibold">Savings
                            Plan</span>
                    </div>
                </div>

                <!-- Story Card 4 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="h-56 bg-linear-to-br from-cyan-400 to-cyan-500 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-car text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">My First Car</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "I never thought I could afford a car, but with the auto loan from the cooperative, I
                            purchased my first vehicle. It changed my daily commute completely."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-cyan-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Ana Lopez</p>
                                <p class="text-xs text-gray-500">Working Professional</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-cyan-100 text-cyan-600 rounded-full text-xs font-semibold">Auto
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 5 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="h-56 bg-linear-to-br from-emerald-400 to-cyan-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-hands-helping text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Emergency Support</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "When a family emergency struck, the cooperative's quick loan process helped me get the
                            funds I needed immediately. They truly care about their members."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Carlos Mendoza</p>
                                <p class="text-xs text-gray-500">Long-time Member</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full text-xs font-semibold">Emergency
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 6 -->
                <div
                    class="bg-white rounded-3xl overflow-hidden shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="h-56 bg-linear-to-br from-cyan-400 to-emerald-400 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-seedling text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Starting Fresh</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "After losing my job, the cooperative helped me start a small agricultural business. Now I'm
                            self-employed and thriving in what I love to do."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-cyan-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Liza Fernandez</p>
                                <p class="text-xs text-gray-500">Entrepreneur</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-cyan-100 text-cyan-600 rounded-full text-xs font-semibold">Business
                            Loan</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">What Our Members Say</h2>
                <div class="w-24 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 mx-auto rounded-full"></div>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-linear-to-br from-emerald-50 to-cyan-50 rounded-3xl p-8 shadow-xl">
                    <div class="flex gap-2 mb-4">
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                    </div>
                    <p class="text-lg text-gray-700 italic mb-6 leading-relaxed">
                        "The staff at Settlers are like family. They took the time to understand my needs and
                        provided personalized solutions. I've recommended this cooperative to all my friends and
                        family."
                    </p>
                    <div class="flex items-center gap-4">
                        <div
                            class="w-14 h-14 bg-linear-to-br from-emerald-400 to-cyan-400 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-xl text-white"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">Sofia Martinez</p>
                            <p class="text-sm text-gray-600">Member since 2015</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-linear-to-br from-cyan-50 to-emerald-50 rounded-3xl p-8 shadow-xl">
                    <div class="flex gap-2 mb-4">
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                    </div>
                    <p class="text-lg text-gray-700 italic mb-6 leading-relaxed">
                        "Best decision I ever made was joining this cooperative. The low interest rates and flexible
                        payment terms made a huge difference in my financial journey. Highly recommended!"
                    </p>
                    <div class="flex items-center gap-4">
                        <div
                            class="w-14 h-14 bg-linear-to-br from-cyan-400 to-blue-400 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-xl text-white"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">David Ramos</p>
                            <p class="text-sm text-gray-600">Member since 2012</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 px-4 bg-linear-to-r from-emerald-500 to-cyan-500">
        <div class="max-w-4xl mx-auto text-center text-white">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">Share Your Story</h2>
            <p class="text-xl mb-10 opacity-90">
                Have you been helped by Settlers Credit Cooperative? We'd love to hear your story and share it with
                our community!
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home_page') }}#Contact"
                    class="px-8 py-4 bg-white text-emerald-600 font-semibold rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
                    Submit Your Story
                    <i class="fas fa-pen ml-2"></i>
                </a>
                <a href="{{ route('home_page') }}"
                    class="px-8 py-4 bg-transparent border-2 border-white text-white font-semibold rounded-full shadow-lg hover:bg-white hover:text-emerald-600 transition-all transform hover:scale-105">
                    Back to Home
                    <i class="fas fa-home ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')
</body>

</html>
