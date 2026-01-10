<div class="flex min-h-screen bg-gray-50">
    {{-- Top Navigation --}}
    <nav class="fixed top-0 left-64 right-0 z-30 h-16 bg-white border-b border-gray-200 shadow-sm">
        <div class="flex items-center justify-between h-full px-6">
            {{-- Title --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('dev.home') }}" class="text-gray-600 hover:text-emerald-600 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-900">Test Dashboard</h1>
                <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-full text-xs font-semibold">All Sections</span>
            </div>

            {{-- Current Section Display --}}
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900">Current Section:</p>
                    <p class="text-xs text-emerald-600 font-semibold uppercase">{{ $currentSection }}</p>
                </div>
            </div>
        </div>
    </nav>

    {{-- Side Navigation --}}
    <nav class="fixed top-0 left-0 z-40 bg-white border-r border-gray-200 w-64 h-full flex flex-col shadow-lg">
        {{-- Logo --}}
        <div class="flex items-center justify-center gap-3 p-6 mb-4 border-b border-gray-200">
            <div
                class="rounded-full overflow-hidden flex items-center justify-center w-12 h-12 shrink-0 bg-linear-to-r from-cyan-500 to-blue-500">
                <i class="fas fa-vial text-white text-xl"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg text-gray-900">Test Mode</h1>
                <p class="text-xs text-gray-600">All Sections</p>
            </div>
        </div>

        {{-- All Sections Navigation --}}
        <div class="flex-1 overflow-y-auto px-4 py-2">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-4">All Sections
                ({{ count($allSections) }})</p>
            <div class="space-y-2">
                <x-role-navigation :sections="$allSections" :currentSection="$currentSection" />
            </div>
        </div>

        {{-- Back Button --}}
        <div class="border-t border-gray-200 p-4">
            <a href="{{ route('dev.home') }}"
                class="w-full flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200 group">
                <i class="fas fa-arrow-left text-lg"></i>
                <span class="flex-1 text-left font-medium">Back to Dev Home</span>
            </a>
        </div>
    </nav>

    <main class="ml-64 mt-16 p-6 w-full min-h-screen bg-gray-50">
        @if (View::exists('livewire.content.' . $currentSection))
            @livewire('content.' . $currentSection, key($currentSection))
        @else
            <div class="flex flex-col items-center justify-center py-12">
                <i class="fas fa-hammer text-6xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Section Not Yet Implemented</h3>
                <p class="text-gray-600">{{ ucfirst($currentSection) }} is coming soon</p>
            </div>
        @endif
    </main>
</div>
