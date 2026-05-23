{{-- Toast Notifications Component --}}
<div x-data="{
    toasts: [],
    add(message, type = 'success') {
        const id = Date.now();
        this.toasts.push({ id, message, type });
        setTimeout(() => this.remove(id), 3000);
    },
    remove(id) {
        this.toasts = this.toasts.filter(toast => toast.id !== id);
    }
}" x-on:toast.window="add($event.detail.message, $event.detail.type)"
    class="fixed top-4 right-4 z-50 space-y-2">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="true" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-full" @click="remove(toast.id)"
            class="flex items-center gap-3 min-w-[320px] max-w-md p-4 rounded-lg shadow-lg cursor-pointer backdrop-blur-sm"
            :class="{
                'bg-gradient-to-r from-emerald-500/90 to-cyan-500/90 text-white': toast.type === 'success',
                'bg-gradient-to-r from-red-500/90 to-pink-500/90 text-white': toast.type === 'error',
                'bg-gradient-to-r from-amber-500/90 to-orange-500/90 text-white': toast.type === 'warning',
                'bg-gradient-to-r from-blue-500/90 to-indigo-500/90 text-white': toast.type === 'info'
            }">
            {{-- Icon --}}
            <div class="flex-shrink-0">
                <template x-if="toast.type === 'success'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </template>
            </div>

            {{-- Message --}}
            <div class="flex-1 text-sm font-medium" x-text="toast.message"></div>

            {{-- Close Button --}}
            <button type="button" class="flex-shrink-0 hover:opacity-75">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    </template>
</div>

{{-- Helper script to trigger toasts from Livewire --}}
@script
    <script>
        window.toast = {
            success(message) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        message,
                        type: 'success'
                    }
                }));
            },
            error(message) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        message,
                        type: 'error'
                    }
                }));
            },
            warning(message) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        message,
                        type: 'warning'
                    }
                }));
            },
            info(message) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        message,
                        type: 'info'
                    }
                }));
            }
        };

        // Listen for Livewire flash messages
        Livewire.on('toast', (event) => {
            window.toast[event.type || 'success'](event.message);
        });

        // Detect session expiry / auth failures on any Livewire request
        Livewire.hook('request', ({
            fail
        }) => {
            fail(({
                status,
                preventDefault
            }) => {
                if (status === 401 || status === 419) {
                    preventDefault();
                    window.toast.warning('Your session has expired. Redirecting to login…');
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 1500);
                }
            });
        });
    </script>
@endscript
