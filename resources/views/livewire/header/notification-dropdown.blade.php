<div class="relative" x-data="{ dropdownOpen: false }" @click.away="dropdownOpen = false"
    @open-notifications-inbox.window="dropdownOpen = false">

    {{-- Bell Button --}}
    <button @click="dropdownOpen = !dropdownOpen"
        class="relative p-2 text-cyan-700 bg-cyan-100 border border-cyan-200 hover:bg-cyan-200 rounded-lg transition-colors"
        type="button">
        <i class="fas fa-bell text-xl"></i>

        @if ($unreadCount > 0)
            <span
                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] leading-[18px] font-bold text-center">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div x-show="dropdownOpen" x-transition.opacity.duration.120ms
        class="absolute right-0 mt-2 w-[360px] max-w-[90vw] bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden z-40"
        style="display:none;">

        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-900">Latest notifications</p>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead" type="button"
                    class="text-xs font-medium text-emerald-700 hover:text-emerald-800">
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-[340px] overflow-y-auto divide-y divide-gray-100">
            @forelse ($notifications as $notification)
                <button wire:click="openNotification({{ $notification->id }})"
                    @click="dropdownOpen = false" type="button"
                    @class([
                        'w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors',
                        'bg-blue-50/60' => ! $notification->is_read,
                    ])>
                    <div class="flex items-start gap-3">
                        <div
                            class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 {{ $notification->color_class }}">
                            <i class="fas {{ $notification->icon }}"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 line-clamp-1">
                                {{ $notification->title }}</p>
                            <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $notification->message }}</p>
                            <p class="text-[11px] text-gray-500 mt-1">{{ $notification->time_ago }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-8 text-center">
                    <p class="text-sm font-medium text-gray-500">No new notifications</p>
                </div>
            @endforelse
        </div>

        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
            <button wire:click="openInbox" @click="dropdownOpen = false" type="button"
                class="w-full text-center text-sm font-semibold text-cyan-700 hover:text-cyan-800">
                Open notifications inbox
            </button>
        </div>
    </div>
</div>
