@props(['officer', 'canEdit', 'activeTab'])

<div {{ $attributes }} x-data="{ show: false }" x-init="setTimeout(() => show = true, 10)" x-show="show"
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900 opacity-50 transition-opacity"
        @click="show = false; setTimeout(() => $wire.closeModal(), 150)"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-6xl max-h-[90vh] mx-auto my-6 z-10">
        <div class="relative bg-white rounded-lg shadow overflow-hidden">
            {{-- Modal header --}}
            <div
                class="flex items-center justify-between p-5 border-b border-gray-200 bg-gradient-to-r from-cyan-600 to-emerald-600">
                <h3 class="text-xl font-semibold text-white">
                    Officer Profile
                </h3>
                <button type="button" @click="show = false; setTimeout(() => $wire.closeModal(), 150)"
                    class="text-white bg-transparent hover:bg-cyan-800 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>

            {{-- Tab Navigation --}}
            <div class="bg-gray-50 border-b border-gray-200">
                <div class="flex space-x-1 px-6">
                    <button wire:click="setActiveTab('overview')"
                        class="px-6 py-3 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800' }}">
                        Overview
                    </button>
                    <button wire:click="setActiveTab('contact')"
                        class="px-6 py-3 text-sm font-medium transition-colors {{ $activeTab === 'contact' ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800' }}">
                        Contact & IDs
                    </button>
                    <button wire:click="setActiveTab('permissions')"
                        class="px-6 py-3 text-sm font-medium transition-colors {{ $activeTab === 'permissions' ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800' }}">
                        Permissions
                    </button>
                    <button wire:click="setActiveTab('documents')"
                        class="px-6 py-3 text-sm font-medium transition-colors {{ $activeTab === 'documents' ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800' }}">
                        Documents
                    </button>
                </div>
            </div>

            {{-- Modal body with scrollable content --}}
            <div class="overflow-y-auto max-h-[calc(90vh-250px)] p-6">
                {{-- Overview Tab --}}
                @if ($activeTab === 'overview')
                    <div class="flex gap-6">
                        {{-- Left Column: Profile Image --}}
                        <div class="w-64 flex-shrink-0">
                            @if ($officer->personalData?->profile_picture)
                                <img src="{{ Storage::url($officer->personalData->profile_picture) }}" alt="Profile"
                                    class="w-64 h-64 rounded-lg shadow-lg object-cover border-4 border-cyan-100">
                            @else
                                <div
                                    class="w-64 h-64 rounded-lg shadow-lg bg-gradient-to-br from-cyan-100 to-emerald-100 flex items-center justify-center border-4 border-cyan-100">
                                    <svg class="w-32 h-32 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                            @endif

                            <div class="mt-4 text-center">
                                <h4 class="text-xl font-bold text-gray-800">
                                    {{ $officer->personalData?->first_name ?? 'N/A' }}
                                    {{ $officer->personalData?->middle_name ? $officer->personalData->middle_name[0] . '.' : ($officer->personalData?->mid_name ? $officer->personalData->mid_name[0] . '.' : '') }}
                                    {{ $officer->personalData?->last_name ?? 'N/A' }}
                                    @if ($officer->personalData?->suffix)
                                        <span class="text-sm text-gray-600">{{ $officer->personalData->suffix }}</span>
                                    @endif
                                </h4>
                                <p class="text-cyan-600 font-semibold mt-1">
                                    {{ $officer->personalData?->title ? ucwords(str_replace('_', ' ', $officer->personalData->title)) : 'Officer' }}
                                </p>
                                <p class="text-sm text-gray-600 mt-1">ID: {{ $officer->user_number }}</p>

                                {{-- Role Badge --}}
                                @php
                                    $primaryRole = $officer->primary_role;
                                    $roleColors = [
                                        'admin' => 'bg-red-100 text-red-800 border-red-200',
                                        'manager' => 'bg-purple-100 text-purple-800 border-purple-200',
                                        'accountant' => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'loan' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                        'officer' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    ];
                                    $roleColor =
                                        $roleColors[$primaryRole] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                @endphp
                                <span
                                    class="inline-block mt-3 px-3 py-1 text-xs font-semibold rounded-full border-2 {{ $roleColor }}">
                                    {{ strtoupper($primaryRole) }}
                                </span>
                            </div>
                        </div>

                        {{-- Right Column: Personal Information --}}
                        <div class="flex-1">
                            <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">
                                Personal Information</h5>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Date of Birth:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->birthdate ? \Carbon\Carbon::parse($officer->personalData->birthdate)->format('F d, Y') : ($officer->personalData?->birth_date ? \Carbon\Carbon::parse($officer->personalData->birth_date)->format('F d, Y') : 'N/A') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Place of Birth:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->birthplace ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Gender:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ ucfirst($officer->personalData?->gender ?? 'N/A') }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Civil Status:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ ucfirst($officer->personalData?->civil_status ?? 'N/A') }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Religion:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->religion ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Nationality:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->nationality ?? 'N/A' }}</p>
                                </div>
                            </div>

                            {{-- Role & Permissions Section --}}
                            <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100 mt-6">
                                Role & Permissions</h5>
                            <div
                                class="bg-gradient-to-r from-cyan-50 to-emerald-50 p-4 rounded-lg border border-cyan-200">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-sm font-medium text-gray-600">System Role:</span>
                                        <p class="text-gray-800 font-semibold">{{ ucfirst($primaryRole) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-600">Position:</span>
                                        <p class="text-gray-800 font-semibold">
                                            {{ $officer->personalData?->title ? ucwords(str_replace('_', ' ', $officer->personalData->title)) : 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <span class="text-sm font-medium text-gray-600 block mb-2">Permissions:</span>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($primaryRole === 'admin')
                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Full System
                                                Access</span>
                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">User
                                                Management</span>
                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Financial
                                                Management</span>
                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Loan
                                                Approval</span>
                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Reports
                                                Access</span>
                                        @elseif($primaryRole === 'manager')
                                            <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Member
                                                Management</span>
                                            <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Loan
                                                Approval</span>
                                            <span
                                                class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Transaction
                                                Oversight</span>
                                            <span
                                                class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Reports
                                                Access</span>
                                        @elseif($primaryRole === 'accountant')
                                            <span
                                                class="px-2 py-1 text-xs bg-amber-100 text-amber-800 rounded">Transaction
                                                Management</span>
                                            <span class="px-2 py-1 text-xs bg-amber-100 text-amber-800 rounded">Member
                                                Onboarding</span>
                                            <span class="px-2 py-1 text-xs bg-amber-100 text-amber-800 rounded">Service
                                                Processing</span>
                                            <span
                                                class="px-2 py-1 text-xs bg-amber-100 text-amber-800 rounded">Financial
                                                Reports</span>
                                        @else
                                            <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-800 rounded">View
                                                Records</span>
                                            <span
                                                class="px-2 py-1 text-xs bg-emerald-100 text-emerald-800 rounded">Process
                                                Transactions</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Contact & IDs Tab --}}
                @if ($activeTab === 'contact')
                    <div class="space-y-6">
                        {{-- Contact Information --}}
                        <div>
                            <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">Contact
                                Information</h5>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Email Address:</span>
                                    <p class="text-gray-800 font-medium">{{ $officer->email ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Phone Number:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->contact?->phone_number ?? ($officer->personalData?->contact?->phone_no ?? 'N/A') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Mobile Number:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->contact?->mobile_number ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Emergency Contact:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $officer->personalData?->contact?->emergency_contact ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Permissions Tab --}}
                @if ($activeTab === 'permissions')
                    @php
                        $rolePerms = $officer->getPermissionsViaRoles()->pluck('name')->all();
                        $overrideRows = \App\Models\OfficerPermissionOverride::where('user_id', $officer->id)
                            ->get()
                            ->keyBy('permission');
                        $groups = \App\Support\PermissionCatalog::groups();
                        $allowCount = $overrideRows->filter(fn($r) => $r->effect === 'allow')->count();
                        $denyCount = $overrideRows->filter(fn($r) => $r->effect === 'disallow')->count();
                    @endphp
                    <div class="space-y-5">
                        {{-- Summary header --}}
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-100">
                            <h5 class="text-sm font-semibold text-gray-800 mb-1">Role-Based Permissions</h5>
                            <p class="text-xs text-gray-600">
                                This officer inherits <strong>{{ count($rolePerms) }}</strong> permissions from their
                                <em>{{ ucfirst($officer->getRoleNames()->first() ?? 'unknown') }}</em> role.
                                @if ($allowCount > 0 || $denyCount > 0)
                                    Overrides: <span class="text-emerald-700 font-medium">{{ $allowCount }}
                                        allow</span>
                                    · <span class="text-red-700 font-medium">{{ $denyCount }} deny</span>.
                                @else
                                    No overrides set.
                                @endif
                                {{ $canEdit ? 'Use "Manage Permissions" to set overrides.' : '' }}
                            </p>
                        </div>

                        {{-- Groups --}}
                        @foreach ($groups as $groupName => $perms)
                            @php
                                $visible = collect($perms)->filter(
                                    fn($p) => in_array($p, $rolePerms, true) || $overrideRows->has($p),
                                );
                            @endphp
                            @if ($visible->isNotEmpty())
                                <div>
                                    <h6 class="text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                                        {{ $groupName }}</h6>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($visible as $perm)
                                            @php
                                                $inRole = in_array($perm, $rolePerms, true);
                                                $override = $overrideRows->get($perm);
                                                $effect = $override?->effect;
                                                $label = \App\Support\PermissionCatalog::formatLabel($perm);
                                            @endphp
                                            @if ($effect === 'disallow')
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded line-through"
                                                    title="Disallowed by override">{{ $label }}</span>
                                            @elseif ($effect === 'allow' && !$inRole)
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-emerald-100 text-emerald-700 rounded"
                                                    title="Allowed by override (not in role)">+{{ $label }}</span>
                                            @elseif ($inRole)
                                                <span
                                                    class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded"
                                                    title="From role">{{ $label }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Documents Tab --}}
                @if ($activeTab === 'documents')
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Profile Photo</h4>
                            @if ($officer->personalData?->profile_picture)
                                <div class="flex items-center gap-4">
                                    <img src="{{ Storage::url($officer->personalData->profile_picture) }}"
                                        alt="Profile photo"
                                        class="w-32 h-32 rounded-full object-cover border-2 border-gray-200 shadow-sm">
                                    <div>
                                        <p class="text-sm text-gray-600">Profile photo on file</p>
                                        <a href="{{ Storage::url($officer->personalData->profile_picture) }}" target="_blank"
                                            class="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-external-link-alt text-xs"></i> View full size
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-gray-400 py-4">
                                    <div class="w-16 h-16 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center">
                                        <i class="fas fa-user text-2xl"></i>
                                    </div>
                                    <p class="text-sm">No profile photo uploaded</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-between p-4 space-x-2 border-t border-gray-200 bg-gray-50">
                <div class="flex items-center space-x-2">
                    @if ($canEdit)
                        <button type="button" wire:click="confirmDelete('{{ $officer->id }}')"
                            class="inline-flex items-center text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            Delete Officer
                        </button>
                    @endif
                </div>
                <div class="flex items-center space-x-2">
                    @if ($canEdit)
                        <button type="button" wire:click="managePermissions('{{ $officer->id }}')"
                            class="inline-flex items-center text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.343a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM15.657 14.657a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM11 17a1 1 0 102 0v-1a1 1 0 10-2 0v1zM5.343 15.657a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414l-.707.707zM4 10a1 1 0 01 1 1H4a1 1 0 110-2h1a1 1 0 011 1zM5.343 5.343a1 1 0 001.414-1.414L6.05 3.222a1 1 0 10-1.414 1.414l.707.707z">
                                </path>
                            </svg>
                            Manage Permissions
                        </button>
                        <button type="button" wire:click="editOfficer('{{ $officer->id }}')"
                            class="inline-flex items-center text-white bg-cyan-600 hover:bg-cyan-700 focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                </path>
                                <path fill-rule="evenodd"
                                    d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            Edit Officer
                        </button>
                    @endif
                    <button type="button" wire:click="closeModal"
                        class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
