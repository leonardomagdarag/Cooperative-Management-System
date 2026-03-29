@php
    function sectionLink($section)
    {
        return request()->routeIs('home_page') ? "#$section" : route('home_page') . "#$section";
    }
@endphp

<nav id="navbar"
    class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md shadow-lg transition-all duration-300"
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
                    <h1 id="logo-heading" class="font-bold text-slate-800 transition-all duration-300"
                        style="font-size: 1.25rem; line-height: 1.75rem;">
                        Settlers</h1>
                    <p id="logo-subtitle" class="text-xs text-gray-600 transition-all duration-300" style="opacity: 1;">
                        Credit Cooperative</p>
                </div>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden lg:flex items-center gap-2 ml-auto">
                <a href="{{ route('home_page') }}"
                    class="px-4 py-2  rounded-lg font-medium transition-all {{ request()->routeIs('home_page') ? 'text-white bg-slate-700' : 'text-gray-700 hover:text-slate-700 hover:bg-gray-100 rounded-lg' }}">
                    <i class="fas fa-home mr-2"></i>Home
                </a>
                <a href="{{ route('about_page') }}"
                    class="px-4 py-2  rounded-lg font-medium transition-all {{ request()->routeIs('about_page') ? 'text-white bg-slate-700' : 'text-gray-700 hover:text-slate-700 hover:bg-gray-100 rounded-lg' }}">
                    <i class="fas fa-info-circle mr-2"></i>About Us
                </a>
                <a href="{{ route('news_page') }}"
                    class="px-4 py-2  rounded-lg font-medium transition-all {{ request()->routeIs('news_page') ? 'text-white bg-slate-700' : 'text-gray-700 hover:text-slate-700 hover:bg-gray-100 rounded-lg' }}">
                    <i class="fas fa-newspaper mr-2"></i>News
                </a>
                {{-- <a href="{{ route('stories_page') }}"
                    class="px-4 py-2  rounded-lg font-medium transition-all {{ request()->routeIs('stories_page') ? 'text-white bg-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg' }}">
                    <i class="fas fa-book-open mr-2"></i>Stories
                </a> --}}
                <a href="{{ sectionLink('Contact') }}"
                    class="px-4 py-2 hover:bg-gray-100 hover:text-slate-700 rounded-lg font-medium transition-all ">
                    <i class="fas fa-envelope mr-2"></i>Contact
                </a>
                <a href="{{ route('login') }}"
                    class="ml-4 px-6 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-102">
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
    <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200">
        <div class="px-4 py-4 space-y-2">
            <a href="{{ route('home_page') }}"
                class="block px-4 py-3 rounded-lg font-medium transition-all {{ request()->routeIs('home_page') ? 'text-white bg-slate-700' : 'text-gray-700 hover:text-slate-700 hover:bg-gray-100' }}">
                <i class="fas fa-home mr-3"></i>Home
            </a>
            <a href="{{ route('about_page') }}"
                class="block px-4 py-3 rounded-lg font-medium transition-all {{ request()->routeIs('about_page') ? 'text-white bg-slate-700' : 'text-gray-700 hover:text-slate-700 hover:bg-gray-100' }}">
                <i class="fas fa-info-circle mr-3"></i>About Us
            </a>
            <a href="{{ route('news_page') }}"
                class="block px-4 py-3 rounded-lg font-medium transition-all {{ request()->routeIs('news_page') ? 'text-white bg-slate-700' : 'text-gray-700 hover:text-slate-700 hover:bg-gray-100' }}">
                <i class="fas fa-newspaper mr-3"></i>News
            </a>
            {{-- <a href="{{ route('stories_page') }}"
                class="block px-4 py-3 rounded-lg font-medium transition-all {{ request()->routeIs('stories_page') ? 'text-white bg-emerald-600' : 'text-gray-700 hover:text-emerald-600 hover:bg-emerald-50' }}">
                <i class="fas fa-book-open mr-3"></i>Stories
            </a> --}}
            <a href="{{ sectionLink('Contact') }}"
                class="block px-4 py-3 text-gray-700 hover:text-slate-700 hover:bg-gray-100 rounded-lg font-medium transition-all">
                <i class="fas fa-envelope mr-3"></i>Contact
            </a>
            <a href="{{ route('login') }}"
                class="block px-4 py-3 bg-emerald-700 text-white font-semibold rounded-lg shadow-md text-center hover:bg-emerald-600 transition-all">
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
