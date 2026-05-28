<div class="space-y-6">
    <div class="rounded-2xl bg-white border border-gray-200 shadow-sm p-4 md:p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Notifications Inbox</h2>
                <p class="text-sm text-gray-500">Track security alerts, account actions, and payment reminders.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-red-100 text-red-700 text-xs font-semibold px-3 py-1">
                    {{ $unreadCount }} unread
                </span>

                <button wire:click="markAllAsRead"
                    class="px-3 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors"
                    type="button">
                    Mark all read
                </button>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white border border-gray-200 shadow-sm p-4 md:p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
            <label class="block">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Status</span>
                <select wire:model.live="status"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-500">
                    <option value="all">All notifications</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
            </label>

            <label class="block">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Category</span>
                <select wire:model.live="category"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-500">
                    <option value="all">All categories</option>
                    <option value="security">Security</option>
                    <option value="activity">Account activity</option>
                    <option value="billing">Billing and reminders</option>
                    <option value="announcements">Announcements</option>
                </select>
            </label>
        </div>

        <div class="space-y-3">
            @forelse ($notifications as $notification)
                <article @class([
                    'rounded-xl border transition-colors cursor-pointer hover:shadow-sm',
                    'border-blue-200 bg-blue-50/50 hover:bg-blue-50' => !$notification->is_read,
                    'border-gray-200 bg-white hover:bg-gray-50' => $notification->is_read,
                ])
                    wire:click="openNotification({{ $notification->id }})"
                    role="button"
                    tabindex="0">
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $notification->color_class }}">
                                <i class="fas {{ $notification->icon }}"></i>
                            </div>

                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $notification->title }}</h3>
                                <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $notification->message }}</p>
                                <p class="text-xs text-gray-500 mt-2">{{ $notification->time_ago }}</p>
                            </div>
                        </div>

                        @if (!$notification->is_read)
                            <span class="shrink-0 w-2 h-2 mt-2 rounded-full bg-blue-500"></span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center">
                    <p class="text-sm font-medium text-gray-600">No notifications found for the selected filters.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-5">
            {{ $notifications->links() }}
        </div>
    </div>

    {{-- Notification Detail Modal --}}
    @if ($showModal && $selectedNotification)
        <div
            x-data
            x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden')"
            @keydown.escape.window="$wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            wire:key="notification-modal-{{ $selectedNotification->id }}">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60" wire:click="closeModal"></div>

            {{-- Modal panel --}}
            <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl border-2 border-gray-300 shadow-2xl overflow-hidden">

                {{-- Header --}}
                <div class="flex items-start gap-4 p-6 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 {{ $selectedNotification->color_class }}">
                        <i class="fas {{ $selectedNotification->icon }} text-lg"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-bold text-gray-900 leading-snug">{{ $selectedNotification->title }}</h2>
                        <p class="text-xs text-gray-500 mt-1">{{ $selectedNotification->time_ago }}</p>
                    </div>

                    <button wire:click="closeModal" type="button"
                        class="shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $selectedNotification->message }}</p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-red-100 text-red-700' => $selectedNotification->priority === 'urgent',
                            'bg-orange-100 text-orange-700' => $selectedNotification->priority === 'high',
                            'bg-blue-100 text-blue-700' => $selectedNotification->priority === 'normal',
                            'bg-gray-100 text-gray-600' => $selectedNotification->priority === 'low',
                        ])>
                            {{ ucfirst($selectedNotification->priority) }} priority
                        </span>

                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-600 px-2.5 py-1 text-xs font-semibold">
                            {{ $selectedNotification->created_at->format('M d, Y \a\t g:i A') }}
                        </span>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-2 px-6 py-4 bg-gray-50 border-t border-gray-100">
                    <button wire:click="closeModal" type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

