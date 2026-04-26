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
            <div class="flex items-center gap-4" x-data="{
                dropdownOpen: false,
                unreadCount: 0,
                notifications: [],

                async init() {
                    await this.fetchNotifications();
                    setInterval(() => this.fetchNotifications(), 30000);
                },

                async fetchNotifications() {
                    try {
                        const response = await fetch('/api/notifications');
                        const data = await response.json();
                        this.notifications = data.notifications ?? [];
                        this.unreadCount = data.unread_count ?? 0;
                    } catch (error) {
                        console.error('Failed to fetch notifications:', error);
                    }
                },

                toggleDropdown() {
                    this.dropdownOpen = !this.dropdownOpen;
                },

                closeDropdown() {
                    this.dropdownOpen = false;
                },

                async markRead(notificationId) {
                    try {
                        await fetch(`/api/notifications/${notificationId}/read`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                            },
                        });

                        await this.fetchNotifications();
                    } catch (error) {
                        console.error('Failed to mark notification as read:', error);
                    }
                },

                async openNotification(notification) {
                    await this.markRead(notification.id);

                    if (notification.action_url) {
                        window.location.href = notification.action_url;
                        return;
                    }

                    this.openInbox();
                },

                openInbox() {
                    this.closeDropdown();
                    window.location.href = '/notifications';
                },

                async markAllAsRead() {
                    try {
                        await fetch('/api/notifications/mark-all-read', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                            },
                        });

                        await this.fetchNotifications();
                    } catch (error) {
                        console.error('Failed to mark all notifications as read:', error);
                    }
                }
            }" @click.away="closeDropdown()">
                {{-- Notification Button --}}
                <div class="relative">
                    <button @click="toggleDropdown()"
                        class="relative p-2 text-cyan-700 bg-cyan-100 border border-cyan-200 hover:bg-cyan-200 rounded-lg transition-colors"
                        type="button">
                        <i class="fas fa-bell text-xl"></i>

                        <span x-show="unreadCount > 0"
                            class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] leading-[18px] font-bold text-center"
                            x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
                    </button>

                    <div x-show="dropdownOpen" x-transition.opacity.duration.120ms
                        class="absolute right-0 mt-2 w-[360px] max-w-[90vw] bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden z-40"
                        style="display:none;">
                        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">Latest notifications</p>
                            <button x-show="unreadCount > 0" @click="markAllAsRead()" type="button"
                                class="text-xs font-medium text-emerald-700 hover:text-emerald-800">
                                Mark all read
                            </button>
                        </div>

                        <div class="max-h-[340px] overflow-y-auto divide-y divide-gray-100">
                            <template x-if="notifications.length === 0">
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm font-medium text-gray-500">No new notifications</p>
                                </div>
                            </template>

                            <template x-for="notification in notifications.slice(0, 6)" :key="notification.id">
                                <button @click="openNotification(notification)" type="button"
                                    class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors"
                                    :class="!notification.is_read ? 'bg-blue-50/60' : ''">
                                    <div class="flex items-start gap-3">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                                            :class="notification.color_class">
                                            <i class="fas" :class="notification.icon"></i>
                                        </div>

                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 line-clamp-1" x-text="notification.title"></p>
                                            <p class="text-xs text-gray-600 mt-1 line-clamp-2" x-text="notification.message"></p>
                                            <p class="text-[11px] text-gray-500 mt-1" x-text="notification.time_ago"></p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                            <button @click="openInbox()" type="button"
                                class="w-full text-center text-sm font-semibold text-cyan-700 hover:text-cyan-800">
                                Open notifications inbox
                            </button>
                        </div>
                    </div>
                </div>

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
    <main class="ml-20 lg:ml-64 mt-16 p-6 w-full min-h-screen bg-gray-50 transition-all duration-300 relative">
        <div wire:loading.flex wire:target="updateSection"
            class="absolute inset-0 z-20 items-center justify-center bg-gray-50/60">
            <div class="flex items-center gap-3 px-4 py-3 bg-white rounded-lg border border-gray-200 shadow-sm">
                <svg class="animate-spin h-5 w-5 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span class="text-sm font-medium text-gray-700">Loading…</span>
            </div>
        </div>

        @if (empty($userSections))
            <div class="flex flex-col items-center justify-center py-16">
                <i class="fas fa-user-lock text-6xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Assigned Permissions</h3>
                <p class="text-gray-600 text-center max-w-xl">
                    Your account currently has no enabled feature permissions. Please contact an administrator to
                    request access.
                </p>
            </div>
        @elseif (View::exists('livewire.content.' . $currentSectionPath))
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
