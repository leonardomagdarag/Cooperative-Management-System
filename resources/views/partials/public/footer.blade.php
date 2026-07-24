<footer class="bg-slate-950 text-white py-16 px-4" x-data="{ activePolicyModal: null }">
    <div class="public-shell">
        <!-- Main Footer Content -->
        <div class="grid gap-10 md:grid-cols-[1.3fr_0.8fr_1fr] mb-12">
            <!-- Brand Section -->
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <div class="rounded-full overflow-hidden flex items-center justify-center bg-white p-1"
                        style="width: 3.5rem; height: 3.5rem;">
                        <img src="{{ asset('images/Logo.png') }}" alt="Settlers Logo" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-white">Settlers</h3>
                        <p class="text-xs font-medium text-emerald-300">Credit Cooperative</p>
                    </div>
                </div>
                <p class="max-w-sm text-sm leading-relaxed text-slate-400">
                    Member-focused financial services for savings, loans, and community growth.
                </p>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="font-semibold mb-6 text-slate-200 text-lg">Quick Links</h4>
                <ul class="space-y-3 text-slate-400">
                    <li>
                        <a href="{{ route('home_page') }}"
                            class="hover:text-white transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about_page') }}"
                            class="hover:text-white transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> About Us
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('news_page') }}"
                            class="hover:text-white transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> News
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('home_page') }}#Contact"
                            class="hover:text-white transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> Contact
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('login_page') }}"
                            class="hover:text-white transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-chevron-right text-xs"></i> Login
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Connect With Us -->
            <div>
                <h4 class="font-semibold mb-6 text-slate-200 text-lg">Connect With Us</h4>
                <div class="flex gap-3 mb-6">
                    <a href="#facebook_link" aria-label="Facebook"
                        class="w-11 h-11 rounded-full border border-white/10 bg-white/5 flex items-center justify-center transition-all hover:-translate-y-0.5 hover:bg-emerald-600">
                        <i class="fab fa-facebook text-lg"></i>
                    </a>
                    <a href="#gmail" aria-label="Gmail"
                        class="w-11 h-11 rounded-full border border-white/10 bg-white/5 flex items-center justify-center transition-all hover:-translate-y-0.5 hover:bg-emerald-600">
                        <i class="fas fa-envelope text-lg"></i>
                    </a>
                    <a href="#google_map" aria-label="Google Map"
                        class="w-11 h-11 rounded-full border border-white/10 bg-white/5 flex items-center justify-center transition-all hover:-translate-y-0.5 hover:bg-emerald-600">
                        <i class="fas fa-map text-lg"></i>
                    </a>
                </div>
                <a href="#gmail_link" class="text-sm text-slate-400 hover:text-white">
                    <i class="fas fa-envelope mr-2"></i>
                    {!! data_get($publicContent ?? [], 'contact.email') !!}
                </a>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-white/10 pt-8">
            <div class="mb-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-slate-400">
                <button type="button" @click="activePolicyModal = 'terms'"
                    class="inline-flex items-center gap-2 transition hover:text-white">
                    <i class="fas fa-file-contract text-xs"></i>
                    Terms and Agreement
                </button>
                <button type="button" @click="activePolicyModal = 'privacy'"
                    class="inline-flex items-center gap-2 transition hover:text-white">
                    <i class="fas fa-shield-halved text-xs"></i>
                    Privacy Policy
                </button>
            </div>
            <p class="text-center text-slate-500 text-sm">
                &copy; 2024 Settlers Credit Cooperative. All rights reserved.
            </p>
        </div>
    </div>

    @include('partials.policies.modal')
</footer>
