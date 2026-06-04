<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Member Stories - Settlers Credit Cooperative</title>
</head>

<body class="public-page">
    @include('partials.public.topNavbar')

    <!-- Hero Header Section -->
    <section class="relative overflow-hidden bg-slate-950 px-4 pb-16 pt-32">
        <div class="relative max-w-4xl mx-auto text-center ">
            <span class="public-eyebrow mb-5 border-emerald-400/30 bg-emerald-500/10 text-emerald-300">
                <i class="fas fa-quote-right"></i> Member experiences
            </span>
            <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-6 ">Member Stories</h1>
            <p class="text-xl text-slate-300 leading-relaxed">
                Discover how Settlers Credit Cooperative has made a difference in the lives of our members through
                financial empowerment and community support.
            </p>
        </div>
    </section>

    <!-- Featured Story Section -->
    <section class="public-section bg-white">
        <div class="public-shell">
            <div class="inline-block px-4 py-2 bg-gray-100 text-slate-700 rounded-full text-sm font-semibold mb-8">
                Featured Story
            </div>
            <div class="public-card overflow-hidden">
                <div class="md:flex">
                    <div class="md:w-1/2 h-96 bg-slate-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-home text-8xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="md:w-1/2 p-12">
                        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">From Dream to Reality: Maria's
                            Home Story</h2>
                        <p class="text-lg text-gray-700 leading-relaxed mb-6">
                            "After 15 years of renting, I finally achieved my dream of owning a home thanks to Settlers
                            Credit Cooperative. Their flexible loan options and supportive staff made the impossible
                            possible.
                            Today, my family has a place we can truly call our own."
                        </p>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-16 h-16 bg-slate-700 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-2xl text-white"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">Maria Santos</p>
                                <p class="text-sm text-gray-600">Member since 2010</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span
                                class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-slate-700 font-semibold">Home
                                Loan</span>
                            <span
                                class="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm text-emerald-700 font-semibold">Success
                                Story</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stories Grid Section -->
    <section class="public-section public-section-soft">
        <div class="public-shell">
            <div class="text-center mb-12">
                <h2 class="public-heading text-3xl md:text-5xl mb-4">More Success Stories</h2>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Story Card 1 -->
                <div
                    class="public-card public-card-hover overflow-hidden">
                    <div class="h-56 bg-slate-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-store text-7xl text-white opacity-20"></i>
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
                            class="inline-block px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-semibold">Business
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 2 -->
                <div
                    class="public-card public-card-hover overflow-hidden">
                    <div class="h-56 bg-emerald-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-7xl text-white opacity-20"></i>
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
                            class="inline-block px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">Education
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 3 -->
                <div
                    class="public-card public-card-hover overflow-hidden">
                    <div class="h-56 bg-slate-700 relative overflow-hidden">
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
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-slate-700"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Pedro Garcia</p>
                                <p class="text-xs text-gray-500">Retired Member</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Savings
                            Plan</span>
                    </div>
                </div>

                <!-- Story Card 4 -->
                <div
                    class="public-card public-card-hover overflow-hidden">
                    <div class="h-56 bg-emerald-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-car text-7xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">My First Car</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "I never thought I could afford a car, but with the auto loan from the cooperative, I
                            purchased my first vehicle. It changed my daily commute completely."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-slate-700"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Ana Lopez</p>
                                <p class="text-xs text-gray-500">Working Professional</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">Auto
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 5 -->
                <div
                    class="public-card public-card-hover overflow-hidden">
                    <div class="h-56 bg-slate-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-hands-helping text-7xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Emergency Support</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "When a family emergency struck, the cooperative's quick loan process helped me get the
                            funds I needed immediately. They truly care about their members."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-slate-700"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Carlos Mendoza</p>
                                <p class="text-xs text-gray-500">Long-time Member</p>
                            </div>
                        </div>
                        <span
                            class="inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Emergency
                            Loan</span>
                    </div>
                </div>

                <!-- Story Card 6 -->
                <div
                    class="public-card public-card-hover overflow-hidden">
                    <div class="h-56 bg-emerald-700 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-seedling text-7xl text-white opacity-20"></i>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Starting Fresh</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed">
                            "After losing my job, the cooperative helped me start a small agricultural business. Now I'm
                            self-employed and thriving in what I love to do."
                        </p>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                <i class="fas fa-user text-slate-700"></i>
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
    <section class="public-section bg-white">
        <div class="public-shell">
            <div class="text-center mb-16">
                <h2 class="public-heading text-4xl md:text-5xl mb-4">What Our Members Say</h2>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Testimonial 1 -->
                <div class="public-card p-8">
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
                        <div class="w-14 h-14 bg-slate-700 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-xl text-white"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800">Sofia Martinez</p>
                            <p class="text-sm text-gray-600">Member since 2015</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="public-card p-8">
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
                        <div class="w-14 h-14 bg-emerald-700 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-xl text-white"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800">David Ramos</p>
                            <p class="text-sm text-gray-600">Member since 2012</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="public-section px-4 bg-emerald-800">
        <div class="max-w-4xl mx-auto text-center text-white">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Share Your Story</h2>
            <p class="text-lg mb-8">
                Have you been helped by Settlers Credit Cooperative? We'd love to hear your story and share it with
                our community!
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home_page') }}#Contact"
                    class="public-btn bg-white text-emerald-700 shadow-md hover:bg-emerald-50">
                    Submit Your Story
                    <i class="fas fa-pen ml-2"></i>
                </a>
                <a href="{{ route('home_page') }}"
                    class="public-btn public-btn-secondary">
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
