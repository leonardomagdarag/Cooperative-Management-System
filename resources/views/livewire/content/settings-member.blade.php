<div class="space-y-6">
    @if ($showBackToSettings ?? false)
        <div>
            <button type="button" wire:click="backToSystemSettings"
                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                <i class="fas fa-arrow-left text-xs"></i>
                Back to Settings
            </button>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-500 via-cyan-500 to-blue-500 px-6 py-7 text-white">
            <p class="text-xs uppercase tracking-[0.28em] text-white/80 font-semibold">Account Settings</p>
            <h1 class="text-3xl font-bold mt-2">Manage your account, privacy, and security</h1>
            <p class="mt-2 text-sm text-white/90 max-w-3xl">
                Keep your profile current, review how your information is used, and control how your cooperative
                account stays protected.
            </p>
        </div>

    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex overflow-x-auto border-b border-gray-200">
            @foreach ([
        'profile' => ['icon' => 'fas fa-user-edit', 'label' => 'Profile'],
        'privacy' => ['icon' => 'fas fa-shield-heart', 'label' => 'Privacy'],
        'security' => ['icon' => 'fas fa-lock', 'label' => 'Security'],
        'account' => ['icon' => 'fas fa-user-slash', 'label' => 'Account'],
    ] as $tabKey => $tabMeta)
                <button type="button" wire:click="setMemberTab('{{ $tabKey }}')"
                    class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium whitespace-nowrap transition-colors shrink-0 {{ $activeMemberTab === $tabKey ? 'text-emerald-600 border-b-2 border-emerald-600 bg-emerald-50/40' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                    <i class="{{ $tabMeta['icon'] }} text-xs"></i>
                    {{ $tabMeta['label'] }}
                </button>
            @endforeach
        </div>

        <div class="p-6 space-y-8">
            @if ($activeMemberTab === 'profile')
                <form wire:submit.prevent="saveMemberProfile" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-5">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Profile details</h2>
                                <p class="text-sm text-gray-500 mt-1">Keep the records used by the cooperative up to
                                    date.</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach (['title' => 'Title', 'first_name' => 'First Name', 'mid_name' => 'Middle Name', 'last_name' => 'Last Name', 'suffix' => 'Suffix', 'birth_date' => 'Birth Date'] as $field => $label)
                                    <label class="block">
                                        <span
                                            class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</span>
                                        <input type="{{ $field === 'birth_date' ? 'date' : 'text' }}"
                                            wire:model.blur="profileForm.{{ $field }}"
                                            class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        @error('profileForm.' . $field)
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </label>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach (['birthplace' => 'Birthplace', 'gender' => 'Gender', 'education' => 'Education', 'civil_status' => 'Civil Status', 'religion' => 'Religion', 'email' => 'Login Email', 'phone_no' => 'Phone Number', 'facebook' => 'Facebook'] as $field => $label)
                                    <label
                                        class="block {{ in_array($field, ['email', 'facebook'], true) ? 'sm:col-span-2' : '' }}">
                                        <span
                                            class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</span>
                                        <input type="text" wire:model.blur="profileForm.{{ $field }}"
                                            class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        @error('profileForm.' . $field)
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Address details</h2>
                                <p class="text-sm text-gray-500 mt-1">Use the address the cooperative should keep on
                                    file.</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach (['country' => 'Country', 'region' => 'Region', 'province' => 'Province', 'city_municipality' => 'City / Municipality', 'barangay' => 'Barangay', 'street' => 'Street', 'zip_code' => 'ZIP Code'] as $field => $label)
                                    <label class="block {{ $field === 'street' ? 'sm:col-span-2' : '' }}">
                                        <span
                                            class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</span>
                                        <input type="text" wire:model.blur="profileForm.{{ $field }}"
                                            class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        @error('profileForm.' . $field)
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </label>
                                @endforeach
                            </div>

                            
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-medium hover:bg-emerald-700 transition-colors">
                            Save Profile Changes
                        </button>
                        <p class="text-xs text-gray-500">Keep this current so the cooperative can process notices and
                            requests faster.</p>
                    </div>
                </form>
            @elseif ($activeMemberTab === 'privacy')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Privacy and information use</h2>
                            <p class="text-sm text-gray-500 mt-1">A practical summary members can understand quickly.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-5 space-y-4 text-sm text-gray-700 leading-6">
                            <p>The cooperative stores only the information needed to verify membership, manage loans and
                                savings, and keep records accurate.</p>
                            <p>Access to your profile should be limited to staff and officers who need it to perform
                                their duties, such as account validation, loan processing, support, reporting, and
                                compliance checks.</p>
                            <p>When staff review your information, it should be for a business purpose only. Internal
                                access should be logged, and sensitive updates should be tied to accountability and
                                audit trails.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <h3
                                class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <i class="fas fa-users text-emerald-500"></i> Who can access what
                            </h3>
                            <div class="space-y-3">
                                @foreach ([['title' => 'Cooperative officers', 'body' => 'Can view records needed for membership services, loan processing, payments, and support requests.'], ['title' => 'System administrators', 'body' => 'Can manage technical access, policy controls, and account lifecycle actions when necessary.'], ['title' => 'You', 'body' => 'Can review your own profile, security activity, devices, and account status from this area.']] as $item)
                                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                                        <p class="font-semibold text-gray-900">{{ $item['title'] }}</p>
                                        <p class="mt-1 text-sm text-gray-600">{{ $item['body'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                </div>
            @elseif ($activeMemberTab === 'security')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <form wire:submit.prevent="changePassword"
                        class="space-y-4 rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Change password</h2>
                            <p class="text-sm text-gray-500 mt-1">Use a password you have not reused anywhere else.</p>
                        </div>

                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700 mb-1">Current password</span>
                            <input type="password" wire:model.blur="passwordForm.current_password"
                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            @error('passwordForm.current_password')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700 mb-1">New password</span>
                            <input type="password" wire:model.blur="passwordForm.password"
                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            @error('passwordForm.password')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</span>
                            <input type="password" wire:model.blur="passwordForm.password_confirmation"
                                class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </label>

                        <div class="flex items-center gap-3">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2.5 rounded-xl bg-cyan-600 text-white font-medium hover:bg-cyan-700 transition-colors">
                                Update Password
                            </button>
                            <button type="button" wire:click="revokeTrustedDevices"
                                class="inline-flex items-center px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                                Revoke Trusted Devices
                            </button>
                        </div>
                    </form>

                    <div class="space-y-4">
                        <div>
                            <h3
                                class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <i class="fas fa-laptop text-emerald-500"></i> Where you're logged in
                            </h3>
                            @if ($trustedDevices->isNotEmpty())
                                <div class="space-y-3">
                                    @foreach ($trustedDevices as $device)
                                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-900 truncate">
                                                        {{ $device->device_label ?? ($device->user_agent ?? 'Unknown device') }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-gray-500">
                                                        Last used
                                                        {{ $device->last_used_at?->diffForHumans() ?? $device->created_at->diffForHumans() }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-gray-500">
                                                        {{ $device->last_ip_address ?? 'IP unavailable' }}</p>
                                                </div>
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $device->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $device->is_active ? 'Active' : 'Revoked' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500 italic">No trusted devices registered yet.</p>
                            @endif
                        </div>

                    </div>
                </div>
            @elseif ($activeMemberTab === 'account')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5 space-y-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Deactivate your account</h2>
                            <p class="text-sm text-gray-600 mt-1">This keeps your record but stops normal access until
                                the cooperative reactivates it.</p>
                        </div>

                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700 mb-1">Confirm your password</span>
                            <input type="password" wire:model.blur="accountForm.current_password"
                                class="w-full rounded-xl border-gray-300 focus:border-amber-500 focus:ring-amber-500">
                            @error('accountForm.current_password')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <button type="button" wire:click="deactivateAccount"
                            class="inline-flex items-center px-4 py-2.5 rounded-xl bg-amber-600 text-white font-medium hover:bg-amber-700 transition-colors">
                            Deactivate Account
                        </button>
                    </div>

                    <div class="rounded-2xl border border-red-100 bg-red-50 p-5 space-y-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Delete your account</h2>
                            <p class="text-sm text-gray-600 mt-1">This soft-deletes your login and removes access
                                immediately.</p>
                        </div>

                        <p class="text-sm text-gray-700 leading-6">
                            The cooperative may still keep records required by law, audits, loans, and membership
                            history.
                            If you want a full privacy process, pair deletion with a records-access or data-correction
                            request.
                        </p>

                        <button type="button" wire:click="deleteAccount"
                            class="inline-flex items-center px-4 py-2.5 rounded-xl bg-red-600 text-white font-medium hover:bg-red-700 transition-colors">
                            Delete Account
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
