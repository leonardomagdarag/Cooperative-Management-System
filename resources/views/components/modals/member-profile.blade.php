@props(['member', 'canEdit', 'activeTab'])

<div {{ $attributes }} x-data="{ show: false }" x-init="setTimeout(() => show = true, 10)" x-show="show" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900 opacity-50"
        @click="show = false; setTimeout(() => $wire.closeModal(), 150)"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-6xl max-h-[90vh] mx-auto my-6 z-10"
        x-transition:enter="transition ease-out duration-200 delay-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-white rounded-lg shadow overflow-hidden">
            {{-- Modal header --}}
            <div
                class="flex items-center justify-between p-5 border-b border-gray-200 bg-gradient-to-r from-cyan-600 to-emerald-600">
                <h3 class="text-xl font-semibold text-white">
                    Member Profile
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
                    <button wire:click="setActiveTab('financial')"
                        class="px-6 py-3 text-sm font-medium transition-colors {{ $activeTab === 'financial' ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800' }}">
                        Financial
                    </button>
                    <button wire:click="setActiveTab('beneficiaries')"
                        class="px-6 py-3 text-sm font-medium transition-colors {{ $activeTab === 'beneficiaries' ? 'text-cyan-600 border-b-2 border-cyan-600 bg-white' : 'text-gray-600 hover:text-gray-800' }}">
                        Beneficiaries
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
                            @if ($member->personalData?->profile_picture)
                                <img src="{{ Storage::url($member->personalData->profile_picture) }}" alt="Profile"
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
                                    {{ $member->personalData?->first_name ?? 'N/A' }}
                                    {{ $member->personalData?->middle_name ? $member->personalData->middle_name[0] . '.' : ($member->personalData?->mid_name ? $member->personalData->mid_name[0] . '.' : '') }}
                                    {{ $member->personalData?->last_name ?? 'N/A' }}
                                    @if ($member->personalData?->suffix)
                                        <span class="text-sm text-gray-600">{{ $member->personalData->suffix }}</span>
                                    @endif
                                </h4>
                                <p class="text-cyan-600 font-semibold mt-1">Member</p>
                                <p class="text-sm text-gray-600 mt-1">ID: {{ $member->user_number }}</p>
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
                                        {{ $member->personalData?->birthdate ? \Carbon\Carbon::parse($member->personalData->birthdate)->format('F d, Y') : ($member->personalData?->birth_date ? \Carbon\Carbon::parse($member->personalData->birth_date)->format('F d, Y') : 'N/A') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Place of Birth:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->birthplace ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Gender:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ ucfirst($member->personalData?->gender ?? 'N/A') }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Civil Status:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ ucfirst($member->personalData?->civil_status ?? 'N/A') }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Religion:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->religion ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Nationality:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->nationality ?? 'N/A' }}</p>
                                </div>
                            </div>

                            {{-- Address Section (Below Personal Info) --}}
                            <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100 mt-6">
                                Address</h5>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                                <div class="col-span-2">
                                    <span class="text-sm font-medium text-gray-600">Street Address:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->address?->street ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Barangay:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->address?->barangay ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">City/Municipality:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->address?->city ?? ($member->personalData?->address?->city_municipality ?? 'N/A') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Province:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->address?->province ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Postal Code:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->address?->postal_code ?? ($member->personalData?->address?->zip_code ?? 'N/A') }}
                                    </p>
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
                                    <p class="text-gray-800 font-medium">{{ $member->email ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Phone Number:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->contact?->phone_number ?? ($member->personalData?->contact?->phone_no ?? 'N/A') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Mobile Number:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->contact?->mobile_number ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-600">Emergency Contact:</span>
                                    <p class="text-gray-800 font-medium">
                                        {{ $member->personalData?->contact?->emergency_contact ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Government IDs --}}
                        <div>
                            <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">
                                Government ID Numbers</h5>
                            @php
                                $canViewFullId = Auth::user()->can('members.view-full-details');
                                $memberId = $member->personalData?->memberId;
                            @endphp

                            <div class="grid grid-cols-1 gap-3">
                                @foreach ([
                                    ['label' => 'National ID (PhilSys)', 'field' => 'national_id', 'photo' => 'national_id_photo'],
                                    ['label' => 'TIN', 'field' => 'tin', 'photo' => 'tin_photo'],
                                    ['label' => 'SSS Number', 'field' => 'sss', 'photo' => 'sss_photo'],
                                    ['label' => 'PhilHealth Number', 'field' => 'philhealth', 'photo' => 'philhealth_photo'],
                                    ['label' => 'Barangay ID', 'field' => 'barangay_id', 'photo' => 'barangay_id_photo'],
                                ] as $idRow)
                                    @php
                                        $fieldVal = $memberId?->{$idRow['field']};
                                        $photoVal = $memberId?->{$idRow['photo']};
                                    @endphp
                                    <div class="grid grid-cols-2 gap-4 items-center p-4 bg-gray-50 rounded-lg" x-data="{ lightbox: false }">
                                        {{-- Left: ID number --}}
                                        <div>
                                            <span class="text-sm font-medium text-gray-600">{{ $idRow['label'] }}:</span>
                                            <p class="text-gray-800 font-mono font-medium mt-1">
                                                @if ($fieldVal)
                                                    @if ($canViewFullId)
                                                        {{ $fieldVal }}
                                                    @else
                                                        ********{{ substr($fieldVal, -3) }}
                                                    @endif
                                                @else
                                                    <span class="text-gray-400 font-normal">N/A</span>
                                                @endif
                                            </p>
                                        </div>

                                        {{-- Right: ID photo thumbnail + lightbox --}}
                                        <div class="flex items-center justify-end">
                                            @if ($canViewFullId && $photoVal)
                                                <div>
                                                    <img src="{{ Storage::url($photoVal) }}"
                                                        alt="{{ $idRow['label'] }} photo"
                                                        @click="lightbox = true"
                                                        class="h-14 w-24 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity shadow-sm">

                                                    {{-- Lightbox --}}
                                                    <div x-show="lightbox" x-cloak @click="lightbox = false"
                                                        class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4"
                                                        x-transition>
                                                        <img src="{{ Storage::url($photoVal) }}"
                                                            alt="{{ $idRow['label'] }} photo full"
                                                            class="max-w-full max-h-full rounded-lg shadow-2xl"
                                                            @click.stop>
                                                        <button @click="lightbox = false"
                                                            class="absolute top-4 right-4 text-white/80 hover:text-white text-2xl">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @elseif ($canViewFullId)
                                                <div class="h-14 w-24 rounded-lg bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center">
                                                    <i class="fas fa-id-card text-gray-300 text-xl"></i>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Employment Information --}}
                        @if ($member->personalData?->employment)
                            <div>
                                <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">
                                    Employment Information</h5>
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <span class="text-sm font-medium text-gray-600">Employer Name:</span>
                                        <p class="text-gray-800 font-medium">
                                            {{ $member->personalData->employment->employer_name ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-600">Position:</span>
                                        <p class="text-gray-800 font-medium">
                                            {{ $member->personalData->employment->position ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-600">Employment Type:</span>
                                        <p class="text-gray-800 font-medium">
                                            {{ ucfirst($member->personalData->employment->employment_type ?? 'N/A') }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-600">Monthly Income:</span>
                                        <p class="text-gray-800 font-medium">
                                            ₱{{ number_format($member->personalData->employment->monthly_income ?? 0, 2) }}
                                        </p>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-sm font-medium text-gray-600">Employer Address:</span>
                                        <p class="text-gray-800 font-medium">
                                            {{ $member->personalData->employment->employer_address ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Financial Tab --}}
                @if ($activeTab === 'financial')
                    <div class="space-y-6">
                        {{-- Account Summary Cards --}}
                        <div class="grid grid-cols-2 gap-6">
                            @php
                                $savingsAccount = $member->accounts?->where('account_type', 'savings')->first();
                                $shareCapitalAccount = $member->accounts
                                    ?->where('account_type', 'share-capital')
                                    ->first();
                            @endphp

                            {{-- Savings Account Card --}}
                            <div
                                class="bg-gradient-to-br from-cyan-50 to-cyan-100 p-6 rounded-xl shadow-md border-2 border-cyan-200">
                                <div class="flex items-center justify-between mb-4">
                                    <h5 class="text-lg font-semibold text-gray-800">Savings Account</h5>
                                    <svg class="w-8 h-8 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                        <path fill-rule="evenodd"
                                            d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                @if ($savingsAccount)
                                    <p class="text-sm text-gray-600 mb-2">Account Number: <span
                                            class="font-mono font-medium">{{ $savingsAccount->account_number }}</span>
                                    </p>
                                    <p class="text-3xl font-bold text-cyan-700">
                                        ₱{{ number_format($savingsAccount->balance, 2) }}</p>
                                    <p class="text-xs text-gray-600 mt-2">Available Balance</p>
                                @else
                                    <p class="text-gray-600 italic">No savings account</p>
                                @endif
                            </div>

                            {{-- Share Capital Account Card --}}
                            <div
                                class="bg-gradient-to-br from-emerald-50 to-emerald-100 p-6 rounded-xl shadow-md border-2 border-emerald-200">
                                <div class="flex items-center justify-between mb-4">
                                    <h5 class="text-lg font-semibold text-gray-800">Share Capital</h5>
                                    <svg class="w-8 h-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                @if ($shareCapitalAccount)
                                    <p class="text-sm text-gray-600 mb-2">Account Number: <span
                                            class="font-mono font-medium">{{ $shareCapitalAccount->account_number }}</span>
                                    </p>
                                    <p class="text-3xl font-bold text-emerald-700">
                                        ₱{{ number_format($shareCapitalAccount->balance, 2) }}</p>
                                    <p class="text-xs text-gray-600 mt-2">Share Capital Balance</p>
                                @else
                                    <p class="text-gray-600 italic">No share capital account</p>
                                @endif
                            </div>
                        </div>

                        {{-- Active Loans --}}
                        <div>
                            <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">Active
                                Loans</h5>
                            @php
                                $activeLoans = $member->loans?->whereIn('loan_status', [
                                    'pending',
                                    'approved',
                                    'active',
                                ]);
                            @endphp

                            @if ($activeLoans && $activeLoans->count() > 0)
                                <div class="space-y-4">
                                    @foreach ($activeLoans as $loan)
                                        <div
                                            class="bg-white border-2 border-gray-200 rounded-lg p-5 hover:shadow-md transition-shadow">
                                            <div class="flex items-start justify-between mb-3">
                                                <div>
                                                    <h6 class="font-semibold text-gray-800">
                                                        {{ ucwords(str_replace('_', ' ', $loan->loan_type)) }} Loan
                                                    </h6>
                                                    <p class="text-sm text-gray-600">Loan No: <span
                                                            class="font-mono">{{ $loan->loan_number }}</span></p>
                                                </div>
                                                <span
                                                    class="px-3 py-1 text-xs font-semibold rounded-full 
                                            {{ $loan->loan_status === 'approved' || $loan->loan_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                    {{ ucfirst($loan->loan_status) }}
                                                </span>
                                            </div>

                                            <div class="grid grid-cols-3 gap-4 mt-4">
                                                <div>
                                                    <span class="text-xs text-gray-600">Principal Amount</span>
                                                    <p class="text-lg font-bold text-gray-800">
                                                        ₱{{ number_format($loan->loan_amount, 2) }}</p>
                                                </div>
                                                <div>
                                                    <span class="text-xs text-gray-600">Interest Rate</span>
                                                    <p class="text-lg font-bold text-gray-800">
                                                        {{ $loan->interest_rate }}%</p>
                                                </div>
                                                <div>
                                                    <span class="text-xs text-gray-600">Term</span>
                                                    <p class="text-lg font-bold text-gray-800">
                                                        {{ $loan->term_months }} months</p>
                                                </div>
                                            </div>

                                            @if ($loan->loan_status === 'approved' || $loan->loan_status === 'active')
                                                <div class="mt-4 pt-4 border-t border-gray-200">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <span class="text-xs text-gray-600">Remaining
                                                                Balance</span>
                                                            <p class="text-xl font-bold text-red-600">
                                                                ₱{{ number_format($loan->remainingBalance(), 2) }}</p>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="text-xs text-gray-600">Due Date</span>
                                                            <p class="text-sm font-semibold text-gray-800">
                                                                {{ $loan->due_date ? \Carbon\Carbon::parse($loan->due_date)->format('M d, Y') : 'N/A' }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-12 bg-gray-50 rounded-lg">
                                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-gray-600">No active loans</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Beneficiaries Tab --}}
                @if ($activeTab === 'beneficiaries')
                    <div>
                        <h5 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-100">
                            Beneficiaries</h5>
                        @if ($member->beneficiaries && $member->beneficiaries->count() > 0)
                            <div class="grid grid-cols-2 gap-4">
                                @foreach ($member->beneficiaries as $beneficiary)
                                    <div
                                        class="bg-gradient-to-r from-cyan-50 to-emerald-50 p-5 rounded-lg border-2 border-cyan-200 hover:shadow-md transition-shadow">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex-1">
                                                <h6 class="text-lg font-bold text-gray-800">
                                                    {{ $beneficiary->first_name }}
                                                    {{ $beneficiary->mid_name ? $beneficiary->mid_name[0] . '.' : '' }}
                                                    {{ $beneficiary->last_name }}
                                                </h6>
                                                <span
                                                    class="inline-block mt-1 px-2 py-1 text-xs font-semibold bg-cyan-100 text-cyan-800 rounded">
                                                    {{ ucfirst($beneficiary->relationship) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <span class="text-xs font-medium text-gray-600">Date of Birth:</span>
                                                <p class="text-sm font-semibold text-gray-800">
                                                    {{ $beneficiary->birth_date ? \Carbon\Carbon::parse($beneficiary->birth_date)->format('F d, Y') : 'N/A' }}
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-600">Age:</span>
                                                <p class="text-sm font-semibold text-gray-800">
                                                    {{ $beneficiary->birth_date ? \Carbon\Carbon::parse($beneficiary->birth_date)->age . ' years old' : 'N/A' }}
                                                </p>
                                            </div>
                                            <div>
                                                <span class="text-xs font-medium text-gray-600">Contact Number:</span>
                                                <p class="text-sm font-semibold text-gray-800">
                                                    {{ $beneficiary->phone_no ?? 'N/A' }}</p>
                                            </div>
                                            <div class="pt-3 border-t border-cyan-200">
                                                <span class="text-xs font-medium text-gray-600">Address:</span>
                                                <p class="text-sm font-semibold text-gray-800">
                                                    @if ($beneficiary->street || $beneficiary->barangay || $beneficiary->city)
                                                        {{ $beneficiary->street ? $beneficiary->street . ', ' : '' }}
                                                        {{ $beneficiary->barangay ? $beneficiary->barangay . ', ' : '' }}
                                                        {{ $beneficiary->city ? $beneficiary->city . ', ' : '' }}
                                                        {{ $beneficiary->province ?? '' }}
                                                        {{ $beneficiary->postal_code ? ' ' . $beneficiary->postal_code : '' }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-16 bg-gray-50 rounded-lg">
                                <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <p class="text-gray-600 text-lg font-medium">No beneficiaries listed</p>
                                <p class="text-gray-500 text-sm mt-2">This member has not added any beneficiaries yet
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Documents Tab --}}
                @if ($activeTab === 'documents')
                    @php $docMemberId = $member->personalData?->memberId; @endphp
                    <div class="space-y-6">
                        {{-- Profile Photo --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Profile Photo</h4>
                            @if ($member->personalData?->profile_picture)
                                <div class="flex items-center gap-4">
                                    <img src="{{ Storage::url($member->personalData->profile_picture) }}"
                                        alt="Profile photo"
                                        class="w-32 h-32 rounded-full object-cover border-2 border-gray-200 shadow-sm">
                                    <div>
                                        <p class="text-sm text-gray-600">Profile photo on file</p>
                                        <a href="{{ Storage::url($member->personalData->profile_picture) }}" target="_blank"
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

                        {{-- Government ID Photos --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Government ID Photos</h4>
                            @php
                                $idPhotoRows = [
                                    ['label' => 'National ID (PhilSys)', 'photo' => $docMemberId?->national_id_photo],
                                    ['label' => 'TIN', 'photo' => $docMemberId?->tin_photo],
                                    ['label' => 'SSS', 'photo' => $docMemberId?->sss_photo],
                                    ['label' => 'PhilHealth', 'photo' => $docMemberId?->philhealth_photo],
                                    ['label' => 'Barangay ID', 'photo' => $docMemberId?->barangay_id_photo],
                                ];
                                $hasAnyIdPhoto = collect($idPhotoRows)->contains(fn($r) => !empty($r['photo']));
                            @endphp
                            @if ($hasAnyIdPhoto)
                                <div class="grid grid-cols-1 gap-3">
                                    @foreach ($idPhotoRows as $row)
                                        @if ($row['photo'])
                                            <div class="grid grid-cols-2 gap-4 items-center p-3 bg-gray-50 rounded-lg" x-data="{ lightbox: false }">
                                                <p class="text-sm font-medium text-gray-700">{{ $row['label'] }}</p>
                                                <div class="flex items-center gap-3">
                                                    <img src="{{ Storage::url($row['photo']) }}"
                                                        alt="{{ $row['label'] }} photo"
                                                        @click="lightbox = true"
                                                        class="h-14 w-24 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity shadow-sm">
                                                    <a href="{{ Storage::url($row['photo']) }}" target="_blank"
                                                        class="text-xs text-blue-600 hover:text-blue-800">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>

                                                    {{-- Lightbox --}}
                                                    <div x-show="lightbox" x-cloak @click="lightbox = false"
                                                        class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4"
                                                        x-transition>
                                                        <img src="{{ Storage::url($row['photo']) }}"
                                                            alt="{{ $row['label'] }} photo full"
                                                            class="max-w-full max-h-full rounded-lg shadow-2xl"
                                                            @click.stop>
                                                        <button @click="lightbox = false"
                                                            class="absolute top-4 right-4 text-white/80 hover:text-white text-2xl">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-gray-400 py-4">
                                    <div class="w-20 h-14 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center">
                                        <i class="fas fa-id-card text-2xl"></i>
                                    </div>
                                    <p class="text-sm">No ID photos uploaded</p>
                                </div>
                            @endif
                        </div>

                        {{-- Other Member Documents --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Other Member Documents</h4>
                            @php
                                $otherDocuments = [
                                    ['label' => 'Proof of Registration', 'path' => $docMemberId?->proof_of_registration_path],
                                    ['label' => 'Other Supporting Document', 'path' => $docMemberId?->other_document_path],
                                ];
                                $hasOtherDocuments = collect($otherDocuments)->contains(fn($document) => !empty($document['path']));
                            @endphp
                            @if ($hasOtherDocuments)
                                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    @foreach ($otherDocuments as $document)
                                        @if ($document['path'])
                                            @php
                                                $isImage = \Illuminate\Support\Str::endsWith(strtolower($document['path']), ['.jpg', '.jpeg', '.png', '.gif', '.webp']);
                                            @endphp
                                            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" x-data="{ lightbox: false }">
                                                <div class="flex items-start gap-4">
                                                    @if ($isImage)
                                                        <img src="{{ Storage::url($document['path']) }}" alt="{{ $document['label'] }}"
                                                            @click="lightbox = true"
                                                            class="h-20 w-20 rounded-lg object-cover border border-gray-200 cursor-pointer hover:opacity-90 transition-opacity">
                                                    @else
                                                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-500">
                                                            <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z" />
                                                            </svg>
                                                        </div>
                                                    @endif

                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium text-gray-800">{{ $document['label'] }}</p>
                                                        <p class="mt-1 truncate text-xs text-gray-500">{{ basename($document['path']) }}</p>
                                                        <a href="{{ Storage::url($document['path']) }}" target="_blank"
                                                            class="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                                            <i class="fas fa-external-link-alt text-xs"></i>
                                                            Open document
                                                        </a>
                                                    </div>
                                                </div>

                                                @if ($isImage)
                                                    <div x-show="lightbox" x-cloak @click="lightbox = false"
                                                        class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4"
                                                        x-transition>
                                                        <img src="{{ Storage::url($document['path']) }}" alt="{{ $document['label'] }} full"
                                                            class="max-w-full max-h-full rounded-lg shadow-2xl" @click.stop>
                                                        <button @click="lightbox = false"
                                                            class="absolute top-4 right-4 text-white/80 hover:text-white text-2xl">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-gray-400 py-4">
                                    <div class="w-20 h-14 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center">
                                        <i class="fas fa-file-alt text-2xl"></i>
                                    </div>
                                    <p class="text-sm">No additional documents uploaded</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-between p-4 space-x-2 border-t border-gray-200 bg-gray-50">
                {{-- Left side: Special Loan Toggle (Manager/Admin only) --}}
                <div class="flex items-center">
                    @if (Auth::user()->can('members.update'))
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" @checked($member->special_loan_eligible)
                                wire:change="toggleSpecialLoanEligibility('{{ $member->id }}')"
                                class="sr-only peer">
                            <div
                                class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600">
                            </div>
                            <span class="ms-3 text-sm font-medium text-gray-700">
                                Special Loan Eligible
                            </span>
                        </label>
                    @endif
                </div>

                {{-- Right side: Action Buttons --}}
                <div class="flex items-center space-x-2">
                    @if ($canEdit)
                        <button type="button" wire:click="editMember('{{ $member->id }}')"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center text-white bg-cyan-600 hover:bg-cyan-700 focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg wire:loading.remove wire:target="editMember" class="w-4 h-4 mr-2"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                </path>
                                <path fill-rule="evenodd"
                                    d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <svg wire:loading wire:target="editMember"
                                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span wire:loading.remove wire:target="editMember">Edit Member</span>
                            <span wire:loading wire:target="editMember">Loading...</span>

                        </button>
                    @endif
                    <button type="button" @click="show = false; setTimeout(() => $wire.closeModal(), 150)"
                        wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed"
                        class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="closeModal, editMember">Close</span>
                        <span wire:loading wire:target="closeModal">Closing...</span>
                        <span wire:loading wire:target="editMember">Please wait...</span>

                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
