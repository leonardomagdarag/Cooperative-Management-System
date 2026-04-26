@if ($activeService === 'loan')
    <div x-data="{ show: false }" x-init="$nextTick(() => show = true)" x-show="show"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black opacity-25"
            @click="show = false; setTimeout(() => $wire.closeService(), 150)"></div>

        <div class="z-10 bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto"
            x-transition:enter="transition ease-out duration-200 delay-100"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">
            <div class="bg-gradient-to-r from-purple-500 to-pink-600 p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold">Apply for Loan</h3>
                        <p class="text-purple-100 text-sm mt-1">Step {{ $loanStep }} of 4</p>
                    </div>
                    <button @click="show = false; setTimeout(() => $wire.closeService(), 150)"
                        class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Progress Bar --}}
                <div class="mt-4 flex gap-2">
                    @for ($i = 1; $i <= 4; $i++)
                        <div class="flex-1 h-2 rounded-full {{ $loanStep >= $i ? 'bg-white' : 'bg-purple-300' }}">
                        </div>
                    @endfor
                </div>
            </div>

            <div class="p-6">
                {{-- Step 1: Loan Type Selection --}}
                @if ($loanStep === 1)
                    <div class="space-y-4">
                        <h4 class="text-xl font-bold text-gray-900 mb-4">Choose Loan Type</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($loanTypes as $key => $type)
                                <button wire:click="selectLoanType('{{ $key }}')"
                                    class="text-left p-5 border-2 border-gray-200 rounded-xl hover:border-purple-500 hover:bg-purple-50 transition-all group">
                                    <div class="flex items-start gap-4">
                                        <div
                                            class="w-12 h-12 bg-purple-100 group-hover:bg-purple-500 rounded-lg flex items-center justify-center transition-colors">
                                            <i
                                                class="fas fa-{{ $type['icon'] }} text-purple-600 group-hover:text-white text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h5 class="font-bold text-gray-900 mb-1">{{ $type['name'] }}</h5>
                                            <p class="text-sm text-gray-600 mb-2">{{ $type['description'] }}</p>
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                                <span>Rate: {{ $type['interest_rate'] }}%</span>
                                                <span>Max: ₱{{ number_format($type['max_amount']) }}</span>
                                                <span>Up to {{ $type['max_term'] }} months</span>
                                            </div>

                                            @if (!empty($loanTypeWarnings[$key]))
                                                <div class="mt-2 text-xs text-red-600 bg-red-50 p-2 rounded">
                                                    ⚠️ {{ $loanTypeWarnings[$key] }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Step 2: Loan Amount & Group Members / Business Info --}}
                @if ($loanStep === 2)
                    <div class="space-y-6">
                        <h4 class="text-xl font-bold text-gray-900">Loan Details</h4>

                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4">
                            <p class="text-sm text-purple-700">
                                <strong>{{ $loanTypes[$loanType]['name'] }}</strong> - Interest Rate:
                                {{ $loanTypes[$loanType]['interest_rate'] }}%
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Loan Amount *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                                <input type="number" wire:model.live="loanAmount" step="100" min="100"
                                    max="{{ $loanTypes[$loanType]['max_amount'] }}"
                                    class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                    placeholder="0.00" required>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Maximum:
                                ₱{{ number_format($loanTypes[$loanType]['max_amount']) }}</p>
                            @error('loanAmount')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Loan Term (Months) *</label>
                                <input type="number" wire:model.live="loanTerm" min="1"
                                    max="{{ $loanTypes[$loanType]['max_term'] }}"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                    required>
                                <p class="text-xs text-gray-500 mt-1">Max: {{ $loanTypes[$loanType]['max_term'] }}
                                    months</p>
                                @error('loanTerm')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Frequency *</label>
                                <select wire:model="loanPaymentFrequency"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>

                        {{-- Product co-makers requirement --}}
                        @if (($loanTypes[$loanType]['required_co_makers'] ?? 0) > 0)
                            <div class="border-t pt-6">
                                <h5 class="font-bold text-gray-900 mb-2">Group Members (Co-makers)</h5>
                                <p class="text-sm text-gray-600 mb-4">Select
                                    {{ $loanTypes[$loanType]['required_co_makers'] }} members to form your loan group.
                                    Each
                                    member must approve the loan.</p>

                                {{-- Search members --}}
                                <div class="mb-4">
                                    <input type="text" wire:model.live.debounce.300ms="memberSearch"
                                        placeholder="Search members by name..."
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">

                                    @if ($memberSearch && strlen($memberSearch) >= 2)
                                        @php
                                            $searchResults = \App\Models\User::where('role', 'member')
                                                ->whereHas('personalData', function ($q) {
                                                    $q->where(
                                                        'first_name',
                                                        'like',
                                                        '%' . $this->memberSearch . '%',
                                                    )->orWhere('last_name', 'like', '%' . $this->memberSearch . '%');
                                                })
                                                ->whereNotIn(
                                                    'id',
                                                    array_merge(
                                                        [
                                                            $this->isTreasurerMode && $this->selectedMemberId
                                                                ? $this->selectedMemberId
                                                                : Auth::id(),
                                                        ],
                                                        $this->groupMembers,
                                                    ),
                                                )
                                                ->with('personalData')
                                                ->limit(5)
                                                ->get();
                                        @endphp

                                        @if ($searchResults->count() > 0)
                                            <div class="mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto">
                                                @foreach ($searchResults as $user)
                                                    <button type="button"
                                                        wire:click="addGroupMember({{ $user->id }})"
                                                        class="w-full px-4 py-2 hover:bg-gray-50 text-left">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $user->personalData->first_name }}
                                                            {{ $user->personalData->last_name }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">ID: {{ $user->user_number }}
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Selected Members --}}
                                <div class="space-y-2">
                                    @forelse ($groupMembers as $memberId)
                                        @php
                                            $member = \App\Models\User::with('personalData')->find($memberId);
                                        @endphp
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    {{ $member->personalData->first_name }}
                                                    {{ $member->personalData->last_name }}
                                                </div>
                                                <div class="text-sm text-gray-500">ID: {{ $member->user_number }}
                                                </div>
                                            </div>
                                            <button type="button" wire:click="removeGroupMember({{ $memberId }})"
                                                class="text-red-600 hover:text-red-800">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="text-center py-8 text-gray-500">
                                            <p>No members added yet</p>
                                            <p class="text-sm">Add {{ $loanTypes[$loanType]['required_co_makers'] }}
                                                members to continue</p>
                                        </div>
                                    @endforelse
                                </div>

                                <div
                                    class="mt-2 text-sm {{ count($groupMembers) === (int) $loanTypes[$loanType]['required_co_makers'] ? 'text-green-600' : 'text-gray-600' }}">
                                    {{ count($groupMembers) }} / {{ $loanTypes[$loanType]['required_co_makers'] }}
                                    members added
                                </div>
                            </div>
                        @endif

                        {{-- Product interview requirement --}}
                        @if (!empty($loanTypes[$loanType]['requires_interview']))
                            <div class="border-t pt-6">
                                <h5 class="font-bold text-gray-900 mb-2">Business Verification</h5>
                                <p class="text-sm text-gray-600 mb-4">Schedule an interview with a loan officer for
                                    business verification.</p>

                                @php
                                    $employment = Auth::user()->personalData?->employment;
                                @endphp

                                @if ($employment)
                                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                                        <h6 class="font-semibold text-gray-900 mb-2">Your Business/Employment Info:
                                        </h6>
                                        <div class="text-sm space-y-1">
                                            <p><strong>Type:</strong>
                                                {{ ucfirst($employment->employment_type ?? 'N/A') }}</p>
                                            <p><strong>Company:</strong> {{ $employment->employer_name ?? 'N/A' }}</p>
                                            <p><strong>Position:</strong> {{ $employment->position ?? 'N/A' }}</p>
                                            <p><strong>Monthly Income:</strong>
                                                ₱{{ number_format($employment->monthly_income ?? 0, 2) }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                        <p class="text-sm text-yellow-700">
                                            ⚠️ No employment information found. Please update your profile with
                                            business/employment details before applying.
                                        </p>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Interview Schedule
                                        *</label>
                                    <input type="datetime-local" wire:model="interviewSchedule"
                                        min="{{ now()->addDay()->format('Y-m-d\TH:i') }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                        required>
                                    <p class="text-xs text-gray-500 mt-1">Select a date and time for your interview
                                        (must be at least 24 hours from now)</p>
                                    @error('interviewSchedule')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousLoanStep"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" wire:click="nextLoanStep"
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Continue
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 3: Loan Purpose --}}
                @if ($loanStep === 3)
                    <div class="space-y-6">
                        <h4 class="text-xl font-bold text-gray-900">Loan Purpose</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Purpose of Loan *</label>
                            <textarea wire:model="loanPurpose" rows="4"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                                placeholder="Describe how you will use the loan..." required></textarea>
                            <p class="text-xs text-gray-500 mt-1">Minimum 10 characters</p>
                            @error('loanPurpose')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousLoanStep"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" wire:click="nextLoanStep"
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Review Application
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 4: Review & Submit --}}
                @if ($loanStep === 4)
                    <div class="space-y-6">
                        <h4 class="text-xl font-bold text-gray-900">Review Your Application</h4>

                        <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Loan Type</p>
                                <p class="font-semibold text-gray-900">{{ $loanTypes[$loanType]['name'] }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Amount</p>
                                    <p class="font-semibold text-gray-900">₱{{ number_format($loanAmount, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Term</p>
                                    <p class="font-semibold text-gray-900">{{ $loanTerm }} months</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Payment Frequency</p>
                                <p class="font-semibold text-gray-900">{{ ucfirst($loanPaymentFrequency) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Interest Rate</p>
                                <p class="font-semibold text-gray-900">{{ $loanTypes[$loanType]['interest_rate'] }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Purpose</p>
                                <p class="font-semibold text-gray-900">{{ $loanPurpose }}</p>
                            </div>

                            @if (($loanTypes[$loanType]['required_co_makers'] ?? 0) > 0)
                                <div>
                                    <p class="text-sm text-gray-600 mb-2">Group Members ({{ count($groupMembers) }})
                                    </p>
                                    @foreach ($groupMembers as $memberId)
                                        @php
                                            $member = \App\Models\User::with('personalData')->find($memberId);
                                        @endphp
                                        <p class="text-sm font-medium text-gray-900">
                                            • {{ $member->personalData->first_name }}
                                            {{ $member->personalData->last_name }} ({{ $member->user_number }})
                                        </p>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($loanTypes[$loanType]['requires_interview']))
                                <div>
                                    <p class="text-sm text-gray-600">Interview Schedule</p>
                                    <p class="font-semibold text-gray-900">
                                        {{ date('F d, Y h:i A', strtotime($interviewSchedule)) }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">
                                <strong>Important:</strong>
                                @if (($loanTypes[$loanType]['required_co_makers'] ?? 0) > 0)
                                    After submission, all group members must approve this loan. Once approved by all
                                    members, the application will be reviewed by loan officers.
                                @elseif (!empty($loanTypes[$loanType]['requires_interview']))
                                    After submission, you must attend the scheduled interview. Your application will be
                                    reviewed after the interview is completed.
                                @else
                                    After submission, your application will be reviewed by loan officers based on your
                                    member profile and account standing.
                                @endif
                            </p>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousLoanStep"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" wire:click="submitLoanApplication"
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Submit Application
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
