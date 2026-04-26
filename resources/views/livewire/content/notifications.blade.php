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

                <button wire:click="deleteRead"
                    class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                    type="button">
                    Clear read
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
                    'rounded-xl border p-4 transition-colors',
                    'border-blue-200 bg-blue-50/50' => !$notification->is_read,
                    'border-gray-200 bg-white' => $notification->is_read,
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $notification->color_class }}">
                                <i class="fas {{ $notification->icon }}"></i>
                            </div>

                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $notification->title }}</h3>
                                <p class="text-sm text-gray-600 mt-1">{{ $notification->message }}</p>
                                <p class="text-xs text-gray-500 mt-2">{{ $notification->time_ago }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if (!$notification->is_read)
                                <button wire:click="markAsRead({{ $notification->id }})"
                                    class="text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                                    type="button">
                                    Mark read
                                </button>
                            @endif

                            @if ($notification->action_url)
                                <a href="{{ $notification->action_url }}"
                                    class="text-xs font-semibold text-cyan-700 hover:text-cyan-800">
                                    Open
                                </a>
                            @endif
                        </div>
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
</div>
