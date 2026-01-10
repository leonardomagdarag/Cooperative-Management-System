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
            <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6">About Us</h1>
            <div class="w-24 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 mx-auto rounded-full mb-8"></div>
            <p class="text-xl text-gray-700 leading-relaxed">
                {!! config('content.about.about1') !!}
            </p>
        </div>
    </section>

    <!-- History Section -->
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <div
                        class="inline-block px-4 py-2 bg-emerald-100 text-emerald-700 rounded-full text-sm font-semibold mb-6">
                        Our Journey
                    </div>
                    <h2 class="text-4xl font-bold text-gray-900 mb-6">Our History</h2>
                    <p class="text-lg text-gray-700 leading-relaxed mb-6">
                        {!! config('content.about.about1') !!}
                    </p>
                    <p class="text-lg text-gray-700 leading-relaxed">
                        {!! config('content.about.about2') !!}
                    </p>
                </div>
                <div class="relative">
                    <img src="{{ asset('images/hero_banner.jpg') }}" alt="Coop member Pictures"
                        class="w-full h-full object-cover rounded-3xl shadow-xl">

                </div>
            </div>
        </div>
    </section>

    <!-- Mission, Vision & Values Section -->
    <section class="py-20 px-4 bg-linear-to-br from-emerald-50 to-cyan-50">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Mission -->
                <div
                    class="bg-white rounded-3xl p-8 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="flex flex-row justify-center items-center mb-8">
                        <div
                            class="w-16 h-16 bg-linear-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center mr-6">
                            <i class="fas fa-bullseye text-3xl text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 ">Our Mission</h2>
                    </div>

                    <p class="text-gray-700 leading-relaxed">
                        {!! config('content.about.mission') !!}
                    </p>
                </div>

                <!-- Vision -->
                <div
                    class="bg-white rounded-3xl p-8 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="flex flex-row justify-center items-center mb-8">
                        <div
                            class="w-16 h-16 bg-linear-to-br from-cyan-500 to-cyan-600 rounded-2xl flex items-center justify-center mr-6">
                            <i class="fas fa-eye text-3xl text-white"></i>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-900 ">Our Vision</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        {!! config('content.about.vision') !!}
                    </p>
                </div>

                <!-- Values -->
                <div
                    class="bg-white rounded-3xl p-8 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="flex flex-row justify-center items-center mb-8">
                        <div
                            class="w-16 h-16 bg-linear-to-br from-emerald-500 to-cyan-500 rounded-2xl flex items-center justify-center mr-6">
                            <i class="fas fa-heart text-3xl text-white"></i>
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
    <section class="py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Board of Directors</h2>
                <div class="w-24 h-1 bg-linear-to-r from-emerald-500 to-cyan-500 mx-auto rounded-full mb-6"></div>
                <p class="text-lg text-gray-600">Meet the dedicated leaders guiding our cooperative</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <!-- Board Member 1 -->
                <div
                    class="bg-linear-to-br from-emerald-50 to-cyan-50 rounded-3xl p-6 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="mb-6">
                        <div
                            class="w-32 h-32 bg-linear-to-br from-emerald-400 to-cyan-400 rounded-full mx-auto flex items-center justify-center shadow-lg">
                            <i class="fas fa-user text-5xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.chairperson.name') !!}</h3>
                    <p class="text-emerald-600 font-semibold text-center mb-4">{!! config('content.board.chairperson.title') !!}</p>
                    <p class="text-sm text-gray-600 text-center">{!! config('content.board.chairperson.motto') !!}
                    </p>
                </div>

                <!-- Board Member 2 -->
                <div
                    class="bg-linear-to-br from-cyan-50 to-emerald-50 rounded-3xl p-6 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="mb-6">
                        <div
                            class="w-32 h-32 bg-linear-to-br from-cyan-400 to-blue-400 rounded-full mx-auto flex items-center justify-center shadow-lg">
                            <i class="fas fa-user text-5xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.vice_chairperson.name') !!}</h3>
                    <p class="text-cyan-600 font-semibold text-center mb-4">{!! config('content.board.vice_chairperson.title') !!}</p>
                    <p class="text-sm text-gray-600 text-center">{!! config('content.board.vice_chairperson.motto') !!}</p>
                </div>

                <!-- Board Member 3 -->
                <div
                    class="bg-linear-to-br from-emerald-50 to-cyan-50 rounded-3xl p-6 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="mb-6">
                        <div
                            class="w-32 h-32 bg-linear-to-br from-emerald-400 to-emerald-500 rounded-full mx-auto flex items-center justify-center shadow-lg">
                            <i class="fas fa-user text-5xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.treasurer.name') !!}</h3>
                    <p class="text-emerald-600 font-semibold text-center mb-4">{!! config('content.board.treasurer.title') !!}</p>
                    <p class="text-sm text-gray-600 text-center">{!! config('content.board.treasurer.motto') !!}</p>
                </div>

                <!-- Board Member 4 -->
                <div
                    class="bg-linear-to-br from-cyan-50 to-blue-50 rounded-3xl p-6 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2">
                    <div class="mb-6">
                        <div
                            class="w-32 h-32 bg-linear-to-br from-cyan-400 to-cyan-500 rounded-full mx-auto flex items-center justify-center shadow-lg">
                            <i class="fas fa-user text-5xl text-white"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{!! config('content.board.secretary.name') !!}</h3>
                    <p class="text-cyan-600 font-semibold text-center mb-4">{!! config('content.board.secretary.title') !!}</p>
                    <p class="text-sm text-gray-600 text-center">{!! config('content.board.secretary.motto') !!}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 px-4 bg-linear-to-r from-emerald-500 to-cyan-500">
        <div class="max-w-4xl mx-auto text-center text-white">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">Join Our Community</h2>
            <p class="text-xl mb-10 opacity-90">
                Become part of a cooperative that values your financial well-being and community growth
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home_page') }}#Contact"
                    class="px-8 py-4 bg-white text-emerald-600 font-semibold rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
                    Contact Us
                    <i class="fas fa-envelope ml-2"></i>
                </a>
                <a href="{{ route('login') }}"
                    class="px-8 py-4 bg-transparent border-2 border-white text-white font-semibold rounded-full shadow-lg hover:bg-white hover:text-emerald-600 transition-all transform hover:scale-105">
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
