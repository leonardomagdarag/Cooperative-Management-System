<div x-data="{ showPublicContentModal: false }" class="space-y-6">
    <div
        class="bg-linear-to-r from-emerald-600 to-cyan-600 rounded-xl border border-emerald-500/20 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.28em] text-white/80 font-semibold">My account</p>
                <h2 class="mt-2 text-2xl font-bold">View your own account settings</h2>
                <p class="mt-2 text-sm text-white/90 max-w-2xl">
                    Open the same self-service profile, security, and account controls you use for your personal
                    cooperative account.
                </p>
            </div>

            <button type="button" wire:click="openMyAccountSettings"
                class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-50">
                View My Account Settings
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
        <p class="text-sm text-gray-600 mt-1">Configure system modules and open specialized management windows.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Public Page Content</h3>
        <p class="text-sm text-gray-600 mb-4">
            Review and prepare the information shown on the cooperative public pages, including the home hero,
            about section, officers, news highlights, and contact details.
        </p>

        @if ($canManage)
            <button type="button" @click.prevent="showPublicContentModal = true"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700">
                Manage Public Page Info
            </button>
        @else
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4">
                Only admins can manage public page content.
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Loan Product Management</h3>
        <p class="text-sm text-gray-600 mb-4">
            Loan products are now managed in a dedicated content window so admins can focus on loan configuration
            without crowding Settings.
        </p>

        @if ($canManage)
            <button type="button" wire:click="openLoanProducts"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-cyan-600 rounded-lg hover:bg-cyan-700">
                Add or Manage Loans
            </button>
        @else
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4">
                Only admins can open Loan Product Management.
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Role Permission Management</h3>
        <p class="text-sm text-gray-600 mb-4">
            Configure the default permissions assigned to each officer role (Manager, Secretary, Loan Officer,
            Treasurer).
            Officers inherit their role's permissions; per-officer overrides can be set from the Officers section.
        </p>

        @if ($canManage)
            <button type="button" wire:click="openRolePermissions"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                Manage Role Permissions
            </button>
        @else
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4">
                Only admins can manage role permissions.
            </div>
        @endif
    </div>
<div x-cloak x-show="showPublicContentModal" class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="public-content-modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4 py-6 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showPublicContentModal = false"></div>

        <div
            class="relative z-10 inline-block w-full max-w-5xl transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:align-middle">
            <div class="border-b border-gray-200 px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-600">Public Page
                            Content</p>
                        <h3 class="mt-1 text-2xl font-bold text-gray-900" id="public-content-modal-title">
                            Manage public information
                        </h3>
                        <p class="mt-2 text-sm text-gray-600">
                            This editor is wired for layout review only. The cooperative does not yet have a table
                            for saving these values.
                        </p>
                    </div>

                    <button type="button" @click="showPublicContentModal = false"
                        class="rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="max-h-[75vh] overflow-y-auto px-6 py-6">
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Changes here are temporary for now. Once a site content table exists, this modal can be
                    connected to real save actions.
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Home Section</h4>
                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Hero subtitle</span>
                                <textarea wire:model.defer="publicPageContentForm.home.hero_subtitle" rows="3"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            </label>

                            @foreach (['active_members' => 'Active members', 'total_assets' => 'Total assets', 'years_service' => 'Years of service', 'service_done' => 'Services done'] as $field => $label)
                                <label class="block">
                                    <span
                                        class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</span>
                                    <input type="text"
                                        wire:model.defer="publicPageContentForm.home.{{ $field }}"
                                        class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-700">About Section</h4>
                        <div class="mt-4 grid grid-cols-1 gap-4">
                            @foreach (['about1' => 'About paragraph 1', 'about2' => 'About paragraph 2', 'history' => 'History', 'mission' => 'Mission', 'vision' => 'Vision'] as $field => $label)
                                <label class="block">
                                    <span
                                        class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</span>
                                    <textarea wire:model.defer="publicPageContentForm.about.{{ $field }}" rows="3"
                                        class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Board and Officers
                        </h4>
                        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div class="space-y-4">
                                <h5 class="text-sm font-semibold text-gray-600">Board Members</h5>
                                @foreach (['chairperson' => 'Chairperson', 'vice_chairperson' => 'Vice-Chairperson', 'treasurer' => 'Treasurer', 'secretary' => 'Secretary'] as $field => $label)
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <label class="block sm:col-span-1">
                                            <span
                                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}
                                                Name</span>
                                            <input type="text"
                                                wire:model.defer="publicPageContentForm.board.{{ $field }}.name"
                                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        </label>
                                        <label class="block sm:col-span-1">
                                            <span
                                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}
                                                Title</span>
                                            <input type="text"
                                                wire:model.defer="publicPageContentForm.board.{{ $field }}.title"
                                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="space-y-4">
                                <h5 class="text-sm font-semibold text-gray-600">Officers</h5>
                                @foreach (['officer1' => 'Officer 1', 'officer2' => 'Officer 2', 'officer3' => 'Officer 3', 'officer4' => 'Officer 4'] as $field => $label)
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <label class="block sm:col-span-1">
                                            <span
                                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}
                                                Name</span>
                                            <input type="text"
                                                wire:model.defer="publicPageContentForm.officers.{{ $field }}.name"
                                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        </label>
                                        <label class="block sm:col-span-1">
                                            <span
                                                class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}
                                                Title</span>
                                            <input type="text"
                                                wire:model.defer="publicPageContentForm.officers.{{ $field }}.title"
                                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-700">News, Events, and
                            Contact</h4>
                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">News hero
                                    description</span>
                                <textarea wire:model.defer="publicPageContentForm.news.hero_description" rows="3"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Newsletter title</span>
                                <input type="text" wire:model.defer="publicPageContentForm.news.newsletter_title"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Privacy note</span>
                                <input type="text" wire:model.defer="publicPageContentForm.news.privacy_note"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Newsletter
                                    description</span>
                                <textarea wire:model.defer="publicPageContentForm.news.newsletter_description" rows="3"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            </label>

                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Events
                                    description</span>
                                <textarea wire:model.defer="publicPageContentForm.events.events" rows="3"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            </label>

                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Contact address</span>
                                <input type="text" wire:model.defer="publicPageContentForm.contact.address"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Contact phone</span>
                                <input type="text" wire:model.defer="publicPageContentForm.contact.phone"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Contact email</span>
                                <input type="email" wire:model.defer="publicPageContentForm.contact.email"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Office hours</span>
                                <textarea wire:model.defer="publicPageContentForm.contact.office_hours" rows="2"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            </label>

                            <label class="block lg:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Map embed URL</span>
                                <textarea wire:model.defer="publicPageContentForm.contact.map_embed" rows="3"
                                    class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">
                        Save is not wired yet. This modal only prepares the content layout until a database table is
                        added.
                    </p>

                    <div class="flex items-center gap-3">
                        <button type="button" @click="showPublicContentModal = false"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Close
                        </button>
                        <button type="button" wire:click="savePublicContentDraft"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                            Save Draft Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
