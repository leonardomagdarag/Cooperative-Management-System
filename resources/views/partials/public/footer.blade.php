<footer class="bg-gray-900 text-white py-16 px-4">
    <div class="max-w-6xl mx-auto">
        <!-- Main Footer Content -->
        <div class="grid md:grid-cols-3 gap-12 mb-12">
            <!-- Brand Section -->
            {{-- <div class="md:col-span-1">
                <div class="flex items-center gap-3 mb-6">
                    <div class="rounded-full overflow-hidden flex items-center justify-center bg-white p-1"
                        style="width: 3.5rem; height: 3.5rem;">
                        <img src="{{ asset('images/Logo.png') }}" alt="Settlers Logo" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-emerald-400">Settlers</h3>
                        <p class="text-xs text-gray-400">Credit Cooperative</p>
                    </div>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Empowering our community through financial excellence and member-focused services.
                </p>
            </div> --}}

            <!-- Quick Links -->
            <div>
                <h4 class="font-semibold mb-6 text-gray-300 text-lg">Quick Links</h4>
                <ul class="space-y-3 text-gray-400">
                    <li>
                        <a href="{{ route('home_page') }}"
                            class="hover:text-gray-200 transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about_page') }}"
                            class="hover:text-gray-200 transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> About Us
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('news_page') }}"
                            class="hover:text-gray-200 transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> News
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('home_page') }}#Contact"
                            class="hover:text-gray-200 transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> Contact
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('login_page') }}"
                            class="hover:text-gray-200 transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> Login
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Connect With Us -->
            <div>
                <h4 class="font-semibold mb-6 text-gray-300 text-lg">Connect With Us</h4>
                <div class="flex gap-3 mb-6">
                    <a href="#facebook_link" aria-label="Facebook"
                        class="w-11 h-11 bg-gray-800 hover:bg-gray-700 rounded-full flex items-center justify-center transition-all hover:scale-105">
                        <i class="fab fa-facebook text-lg"></i>
                    </a>
                    <a href="#gmail" aria-label="Gmail"
                        class="w-11 h-11 bg-gray-800 hover:bg-gray-700 rounded-full flex items-center justify-center transition-all hover:scale-105">
                        <i class="fas fa-envelope text-lg"></i>
                    </a>
                    <a href="#google_map" aria-label="GooglMap"
                        class="w-11 h-11 bg-gray-800 hover:bg-gray-700 rounded-full flex items-center justify-center transition-all hover:scale-105">
                        <i class="fas fa-map text-lg"></i>
                    </a>
                </div>
                <a href="#gmail_link" class=" text-sm text-gray-400 hover:text-gray-200">
                    <i class="fas fa-envelope mr-2"></i>
                    {!! config('content.contact.email') !!}
                </a>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-gray-800 pt-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; 2024 Settlers Credit Cooperative. All rights reserved.
            </p>
        </div>
    </div>
</footer>
