<div class="flex">
    {{-- Top Navigation --}}
    <nav
        class="fixed top-0 left-20 lg:left-64 right-0 z-30 h-16 bg-white border-b border-gray-200 shadow-sm transition-all duration-300">
        <div class="flex items-center justify-between h-full px-6">
            {{-- Search bar --}}
            <div class="flex-1 max-w-md">
                <div class="relative">
                    <input type="text" placeholder="Search..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            {{-- Right side: Notification & User --}}
            <div class="flex items-center gap-4">
                {{-- Notification Button --}}
                <button
                    class="relative p-2 text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors">
                    <i class="fas fa-bell text-xl"></i>
                    {{-- Notification badge --}}
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>

                {{-- User Info --}}
                <div class="flex items-center gap-3 pl-4 border-l border-gray-200">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900">
                            {{ auth()->user()->personalData ? auth()->user()->personalData->first_name . ' ' . auth()->user()->personalData->last_name : 'User' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ auth()->user()->personalData->title ?? 'Member' }}
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-full bg-linear-to-r from-emerald-500 to-cyan-500 flex items-center justify-center text-white font-semibold">
                        {{ auth()->user()->personalData ? strtoupper(substr(auth()->user()->personalData->first_name, 0, 1)) : 'U' }}
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Side Navigation - Collapsed on md, full on lg+ --}}
    <nav
        class="fixed top-0 left-0 z-40 bg-white border-r border-gray-200 w-20 lg:w-64 hover:w-64 h-full flex flex-col shadow-lg transition-all duration-300 group">
        {{-- Logo and Coop Name --}}
        <div class="flex items-center justify-center lg:justify-start gap-3 p-2 mb-4 border-b border-gray-200">
            <div class="rounded-full overflow-hidden flex items-center justify-center w-12 h-12 shrink-0">
                <img src="{{ asset('images/Logo.png') }}" alt="Settlers Logo" class="w-full h-full object-cover">
            </div>
            <div class="hidden lg:block group-hover:block">
                <h1
                    class="font-bold text-lg bg-linear-to-r from-emerald-600 to-cyan-600 bg-clip-text text-transparent whitespace-nowrap">
                    Settlers
                </h1>
                <p class="text-xs text-gray-600 whitespace-nowrap">Credit Cooperative</p>
            </div>
        </div>

        {{-- Nav Links --}}
        {{-- logic is inside --}}
        <x-role-navigation :sections="$userSections" :currentSection="$currentSection" />

        {{-- Logout --}}
        <div class="border-t border-gray-200 p-4">
            <button wire:click="logout"
                class="w-full flex items-center justify-center lg:justify-start gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                <i class="fas fa-sign-out-alt text-lg"></i>
                <span
                    class="hidden lg:block group-hover:block flex-1 text-left font-medium whitespace-nowrap">Logout</span>
            </button>
        </div>
    </nav>

    {{-- Main Content Area --}}
    <main class="ml-20 lg:ml-64 mt-16 p-6 w-full min-h-screen bg-gray-50 transition-all duration-300">
        @if (View::exists('livewire.content.' . $currentSectionPath))
            @livewire('content.' . $currentSectionPath, key($currentSection))
        @else
            <div class="flex flex-col items-center justify-center py-12">
                <i class="fas fa-hammer text-6xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Section Not Yet Implemented</h3>
                <p class="text-gray-600">{{ ucfirst($currentSection) }} is coming soon</p>
            </div>
        @endif
    </main>

    {{-- Universal Toast Notifications --}}
    <x-toast-notifications />
</div>
