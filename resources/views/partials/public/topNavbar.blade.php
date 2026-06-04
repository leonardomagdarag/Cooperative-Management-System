@php
    if (!function_exists('sectionLink')) {
        function sectionLink($section)
        {
            return request()->routeIs('home_page') ? "#$section" : route('home_page') . "#$section";
        }
    }
@endphp

<nav id="navbar"
    class="fixed top-0 left-0 right-0 z-50 border-b border-emerald-900/10 bg-white/90 backdrop-blur-xl shadow-sm transition-all duration-300"
    style="--navbar-height: 5rem;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div id="nav-container" class="flex justify-between items-center transition-all duration-300"
            style="height: var(--navbar-height);">
            <!-- Logo -->
            <div class="flex items-center gap-3 shrink-0">
                <div id="logo-icon"
                    class="rounded-full transition-all duration-300 overflow-hidden flex items-center justify-center"
                    style="width: 3rem; height: 3rem;">
                    <img src="{{ asset('images/Logo.png') }}" alt="Settlers Logo" class="w-full h-full object-cover"
                        width="48" height="48" decoding="async">
                </div>
                <div id="logo-text" class="transition-all duration-300">
                    <h1 id="logo-heading" class="font-extrabold text-slate-900 transition-all duration-300"
                        style="font-size: 1.25rem; line-height: 1.75rem;">
                        Settlers</h1>
                    <p id="logo-subtitle" class="text-xs font-medium text-emerald-700 transition-all duration-300" style="opacity: 1;">
                        Credit Cooperative</p>
                </div>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden lg:flex items-center ml-auto">
                <div class="flex items-center gap-1 mr-6">
                    <a href="{{ route('home_page') }}"
                        class="relative px-4 py-2 font-semibold transition-all duration-200 {{ request()->routeIs('home_page') ? 'text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-600' : 'text-gray-700 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100' }}">
                        <i class="fas fa-home mr-2"></i>Home
                    </a>
                    <a href="{{ route('about_page') }}"
                        class="relative px-4 py-2 font-semibold transition-all duration-200 {{ request()->routeIs('about_page') ? 'text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-600' : 'text-gray-700 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100' }}">
                        <i class="fas fa-info-circle mr-2"></i>About Us
                    </a>
                    <a href="{{ route('news_page') }}"
                        class="relative px-4 py-2 font-semibold transition-all duration-200 {{ request()->routeIs('news_page') ? 'text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-600' : 'text-gray-700 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100' }}">
                        <i class="fas fa-newspaper mr-2"></i>News
                    </a>
                    {{-- <a href="{{ route('stories_page') }}"
                        class="relative px-4 py-2 font-medium transition-all duration-200 {{ request()->routeIs('stories_page') ? 'text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-600' : 'text-gray-700 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100' }}">
                        Stories
                    </a> --}}
                    <a href="{{ sectionLink('Contact') }}"
                        class="relative px-4 py-2 font-semibold text-gray-700 transition-all duration-200 hover:text-emerald-700 after:absolute after:left-4 after:right-4 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-emerald-500 after:scale-x-0 after:origin-center after:transition-transform after:duration-200 hover:after:scale-x-100">
                        <i class="fas fa-envelope mr-2"></i>Contact
                    </a>
                </div>
                <a href="{{ route('login') }}"
                    class="public-btn public-btn-primary ml-4 px-5 py-2.5">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button"
                class="lg:hidden p-2 text-gray-700 hover:text-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"></path>
                    <path id="close-icon" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden lg:hidden border-t border-emerald-900/10 bg-white/95 shadow-lg backdrop-blur-xl">
        <div class="px-4 py-4 space-y-2">
            <a href="{{ route('home_page') }}"
                class="block rounded-xl border-l-4 px-4 py-3 font-semibold transition-all {{ request()->routeIs('home_page') ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-transparent text-gray-700 hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700' }}">
                <i class="fas fa-home mr-3"></i>Home
            </a>
            <a href="{{ route('about_page') }}"
                class="block rounded-xl border-l-4 px-4 py-3 font-semibold transition-all {{ request()->routeIs('about_page') ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-transparent text-gray-700 hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700' }}">
                <i class="fas fa-info-circle mr-3"></i>About Us
            </a>
            <a href="{{ route('news_page') }}"
                class="block rounded-xl border-l-4 px-4 py-3 font-semibold transition-all {{ request()->routeIs('news_page') ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-transparent text-gray-700 hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700' }}">
                <i class="fas fa-newspaper mr-3"></i>News
            </a>
            {{-- <a href="{{ route('stories_page') }}"
                class="block rounded-xl border-l-4 px-4 py-3 font-medium transition-all {{ request()->routeIs('stories_page') ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-transparent text-gray-700 hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700' }}">
                Stories
            </a> --}}
            <a href="{{ sectionLink('Contact') }}"
                class="block rounded-xl border-l-4 border-transparent px-4 py-3 font-semibold text-gray-700 transition-all hover:border-emerald-500 hover:bg-emerald-50/70 hover:text-emerald-700">
                <i class="fas fa-envelope mr-3"></i>Contact
            </a>
            <a href="{{ route('login') }}"
                class="public-btn public-btn-primary w-full">
                <i class="fas fa-sign-in-alt mr-2"></i>Login
            </a>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const navbar = document.getElementById('navbar');
        const navContainer = document.getElementById('nav-container');
        const logoIcon = document.getElementById('logo-icon');
        const logoHeading = document.getElementById('logo-heading');
        const logoSubtitle = document.getElementById('logo-subtitle');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = document.getElementById('menu-icon');
        const closeIcon = document.getElementById('close-icon');

        let isHovering = false;
        let ticking = false;
        let lastShrinkState = null;

        // Shrink navbar on scroll
        function updateNavbar() {
            const shouldShrink = window.scrollY > 50 && !isHovering;

            if (lastShrinkState === shouldShrink) {
                return;
            }

            lastShrinkState = shouldShrink;

            if (shouldShrink) {
                // Shrink state
                navbar.style.setProperty('--navbar-height', '3.5rem');
                navContainer.style.height = '3.5rem';
                logoIcon.style.width = '2rem';
                logoIcon.style.height = '2rem';
                logoHeading.style.fontSize = '1rem';
                logoHeading.style.lineHeight = '1.5rem';
                logoSubtitle.style.opacity = '0';
                logoSubtitle.style.maxHeight = '0';
            } else {
                // Normal state
                navbar.style.setProperty('--navbar-height', '5rem');
                navContainer.style.height = '5rem';
                logoIcon.style.width = '3rem';
                logoIcon.style.height = '3rem';
                logoHeading.style.fontSize = '1.25rem';
                logoHeading.style.lineHeight = '1.75rem';
                logoSubtitle.style.opacity = '1';
                logoSubtitle.style.maxHeight = '1rem';
            }
        }

        // Handle scroll event with requestAnimationFrame to reduce layout thrash.
        window.addEventListener('scroll', function() {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(function() {
                updateNavbar();
                ticking = false;
            });
        }, {
            passive: true
        });

        // Handle hover events
        navbar.addEventListener('mouseenter', function() {
            isHovering = true;
            updateNavbar();
        });

        navbar.addEventListener('mouseleave', function() {
            isHovering = false;
            updateNavbar();
        });

        // Mobile menu functionality
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            menuIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const isClickInside = mobileMenuButton.contains(event.target) || mobileMenu.contains(event
                .target);
            if (!isClickInside && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        });

        // Close mobile menu when clicking a link
        const mobileLinks = mobileMenu.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            });
        });

        // Initial state
        updateNavbar();
    });
</script>
