<div class="space-y-6">
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
            <button type="button" wire:click="openPublicContentManager"
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
            Base roles stay available but can still be edited, and custom roles can be added for new officer
            responsibilities. Officers inherit their role's permissions; per-officer overrides can still be managed
            from the Officers section.
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
</div>
