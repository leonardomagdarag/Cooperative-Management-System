<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css'])
    <title>About Us - Settlers Credit Cooperative</title>
</head>

<body class="bg-white">
    @include('partials.public.topNavbar')

    <!-- Hero Header Section -->
    <section class="relative pt-32 pb-16 px-4 bg-gray-50 overflow-hidden">
        <div class="relative max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">About Us</h1>
            <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-8"></div>
            <p class="text-xl text-gray-700 leading-relaxed">
                {!! config('content.about.about1') !!}
            </p>
        </div>
    </section>

    <!-- History Section -->
    <section class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <div
                        class="inline-block px-4 py-2 bg-gray-100 text-slate-700 rounded-full text-sm font-semibold mb-6">
                        Our Journey
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-6">Our History</h2>
                    <p class="text-lg text-gray-700 leading-relaxed mb-6">
                        {!! config('content.about.about1') !!}
                    </p>
                    <p class="text-lg text-gray-700 leading-relaxed">
                        {!! config('content.about.about2') !!}
                    </p>
                </div>
                <div class="relative">
                    <img src="{{ asset('images/hero_banner.jpg') }}" alt="Coop member Pictures"
                        class="w-full h-full object-cover rounded-2xl shadow-lg" loading="lazy" decoding="async">

                </div>
            </div>
        </div>
    </section>

    <!-- Mission, Vision & Values Section -->
    <section class="py-16 px-4 bg-gray-50">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Mission -->
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition-all">
                    <div class="flex flex-row justify-center items-center mb-8">
                        <div class="w-14 h-14 bg-emerald-700 rounded-xl flex items-center justify-center mr-6">
                            <i class="fas fa-bullseye text-2xl text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 ">Our Mission</h2>
                    </div>

                    <p class="text-gray-700 leading-relaxed">
                        {!! config('content.about.mission') !!}
                    </p>
                </div>

                <!-- Vision -->
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition-all">
                    <div class="flex flex-row justify-center items-center mb-8">
                        <div class="w-14 h-14 bg-slate-700 rounded-xl flex items-center justify-center mr-6">
                            <i class="fas fa-eye text-2xl text-white"></i>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-900 ">Our Vision</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        {!! config('content.about.vision') !!}
                    </p>
                </div>

                <!-- Values -->
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition-all">
                    <div class="flex flex-row justify-center items-center mb-8">
                        <div class="w-14 h-14 bg-emerald-700 rounded-xl flex items-center justify-center mr-6">
                            <i class="fas fa-heart text-2xl text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900">Our Values</h2>
                    </div>

                    <ul class="space-y-3 text-gray-700">
                        <li class="flex items-start gap-2">
                            <span><strong>Integrity:</strong> Honesty in all dealings</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span><strong>Member-Centric:</strong> Members first, always</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span><strong>Community Focus:</strong> Building together</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span><strong>Innovation:</strong> Continuous improvement</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span><strong>Collaboration:</strong> Strength in unity</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Board of Directors Section -->
    <section class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Board of Directors</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-6"></div>
                <p class="text-lg text-gray-600">Meet the dedicated leaders guiding our cooperative</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <!-- Board Member 1 -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-slate-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.chairperson.name') !!}</h3>
                    <p class="text-emerald-700 font-semibold text-center">{!! config('content.board.chairperson.title') !!}</p>
                </div>

                <!-- Board Member 2 -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-emerald-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.vice_chairperson.name') !!}</h3>
                    <p class="text-slate-700 font-semibold text-center">{!! config('content.board.vice_chairperson.title') !!}</p>
                </div>

                <!-- Board Member 3 -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-slate-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.treasurer.name') !!}</h3>
                    <p class="text-emerald-700 font-semibold text-center">{!! config('content.board.treasurer.title') !!}</p>
                </div>

                <!-- Board Member 4 -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-emerald-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.secretary.name') !!}</h3>
                    <p class="text-slate-700 font-semibold text-center">{!! config('content.board.secretary.title') !!}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Officers Section -->
    <section class="py-16 px-4 bg-gray-50">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Our Officers</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded-full mb-6"></div>
                <p class="text-lg text-gray-600">The dedicated professionals managing our daily operations</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Officer 1 -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-slate-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user-tie text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.officers.officer1.name') !!}</h3>
                    <p class="text-emerald-700 font-semibold text-center">{!! config('content.officers.officer1.title') !!}</p>
                </div>

                <!-- Officer 2 -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-emerald-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user-tie text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.officers.officer2.name') !!}</h3>
                    <p class="text-slate-700 font-semibold text-center">{!! config('content.officers.officer2.title') !!}</p>
                </div>

                <!-- Officer 3 -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-slate-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user-tie text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.officers.officer3.name') !!}</h3>
                    <p class="text-emerald-700 font-semibold text-center">{!! config('content.officers.officer3.title') !!}</p>
                </div>

                <!-- Officer 4 -->
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <div
                            class="w-28 h-28 bg-emerald-700 rounded-full mx-auto flex items-center justify-center shadow-md">
                            <i class="fas fa-user-tie text-4xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.officers.officer4.name') !!}</h3>
                    <p class="text-slate-700 font-semibold text-center">{!! config('content.officers.officer4.title') !!}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Member Statements Section -->
    <section class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-800 mb-4">Member Statements</h2>
                <div class="w-20 h-1 bg-emerald-700 mx-auto rounded mb-6"></div>
                <p class="text-base text-gray-600">Hear from our members about their experience with our cooperative
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Statement 1 -->
                <div
                    class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <i class="fas fa-quote-left text-3xl text-slate-700 opacity-50"></i>
                    </div>
                    <p class="text-gray-700 mb-6 leading-relaxed">
                        {!! config('content.member_statements.statement1.statement') !!}
                    </p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800">{!! config('content.member_statements.statement1.name') !!}</h4>
                            <p class="text-sm text-gray-600">Member since {!! config('content.member_statements.statement1.member_since') !!}</p>
                        </div>
                    </div>
                </div>

                <!-- Statement 2 -->
                <div
                    class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <i class="fas fa-quote-left text-3xl text-emerald-700 opacity-50"></i>
                    </div>
                    <p class="text-gray-700 mb-6 leading-relaxed">
                        {!! config('content.member_statements.statement2.statement') !!}
                    </p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-emerald-700 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800">{!! config('content.member_statements.statement2.name') !!}</h4>
                            <p class="text-sm text-gray-600">Member since {!! config('content.member_statements.statement2.member_since') !!}</p>
                        </div>
                    </div>
                </div>

                <!-- Statement 3 -->
                <div
                    class="bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all">
                    <div class="mb-6">
                        <i class="fas fa-quote-left text-3xl text-slate-700 opacity-50"></i>
                    </div>
                    <p class="text-gray-700 mb-6 leading-relaxed">
                        {!! config('content.member_statements.statement3.statement') !!}
                    </p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800">{!! config('content.member_statements.statement3.name') !!}</h4>
                            <p class="text-sm text-gray-600">Member since {!! config('content.member_statements.statement3.member_since') !!}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 px-4 bg-emerald-700">
        <div class="max-w-4xl mx-auto text-center text-white">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Join Our Community</h2>
            <p class="text-lg mb-8">
                Become part of a cooperative that values your financial well-being and community growth
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home_page') }}#Contact"
                    class="px-6 py-3 bg-white text-emerald-700 font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                    Contact Us
                    <i class="fas fa-envelope ml-2"></i>
                </a>
                <a href="{{ route('login') }}"
                    class="px-6 py-3 bg-transparent border-2 border-white text-white font-semibold rounded-lg shadow-md hover:bg-white hover:text-emerald-700 transition-all">
                    Member Login
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.public.footer')
</body>

</html>
