<div class="space-y-6">

    {{-- ── Page Header ── --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div
                class="w-10 h-10 rounded-full bg-linear-to-br from-emerald-500 to-cyan-500 flex items-center justify-center text-white font-bold text-lg shrink-0">
                {{ $user->personalData ? strtoupper(substr($user->personalData->first_name, 0, 1)) : 'U' }}
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ $profileMode === 'member' ? 'My Profile' : 'Officer Profile' }}
                </h1>
                <p class="text-sm text-gray-500">View and manage your personal information</p>
            </div>
        </div>

        <button type="button" wire:click="openEditModal"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
            <i class="fas fa-pen text-xs"></i>
            {{ $user->personalData ? 'Edit Personal Info' : 'Complete Profile' }}
        </button>
    </div>

    {{-- ── Profile Card (top summary) ── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="h-20 bg-linear-to-r from-emerald-500 to-cyan-600"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 -mt-10">
                {{-- Avatar --}}
                <div
                    class="w-20 h-20 rounded-full bg-linear-to-br from-emerald-500 to-cyan-500 border-4 border-white shadow flex items-center justify-center text-white font-bold text-3xl">
                    {{ $user->personalData ? strtoupper(substr($user->personalData->first_name, 0, 1)) : 'U' }}
                </div>

                {{-- Quick stats --}}
                <div class="flex flex-wrap gap-3 sm:mb-1">
                    <div class="text-center px-4 py-2 bg-emerald-50 rounded-xl border border-emerald-100">
                        <p class="text-xs text-emerald-600 font-medium">Accounts</p>
                        <p class="text-lg font-bold text-emerald-700">{{ $accounts->count() }}</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-cyan-50 rounded-xl border border-cyan-100">
                        <p class="text-xs text-cyan-600 font-medium">Total Balance</p>
                        <p class="text-lg font-bold text-cyan-700">₱{{ number_format($totalBalance, 2) }}</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-amber-50 rounded-xl border border-amber-100">
                        <p class="text-xs text-amber-600 font-medium">Active Loans</p>
                        <p class="text-lg font-bold text-amber-700">{{ $activeLoans }}</p>
                    </div>
                </div>
            </div>

            {{-- Name and meta --}}
            <div class="mt-3">
                <h2 class="text-xl font-bold text-gray-900">
                    @if ($user->personalData)
                        {{ trim(($user->personalData->title ? $user->personalData->title . ' ' : '') . $user->personalData->first_name . ' ' . ($user->personalData->mid_name ? strtoupper(substr($user->personalData->mid_name, 0, 1)) . '. ' : '') . $user->personalData->last_name . ($user->personalData->suffix ? ' ' . $user->personalData->suffix : '')) }}
                    @else
                        {{ $user->email }}
                    @endif
                </h2>
                <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-1">
                        <i class="fas fa-id-badge text-xs"></i>
                        {{ $user->user_number }}
                    </span>
                    <span class="text-gray-300">•</span>
                    <span class="inline-flex items-center gap-1 capitalize">
                        <i class="fas fa-user-tag text-xs"></i>
                        {{ $user->getRoleNames()->first() ?? 'Member' }}
                    </span>
                    <span class="text-gray-300">•</span>
                    @php
                        $statusColors = [
                            'active' => 'text-emerald-600 bg-emerald-50 border-emerald-200',
                            'inactive' => 'text-gray-500 bg-gray-50 border-gray-200',
                            'suspended' => 'text-amber-600 bg-amber-50 border-amber-200',
                            'terminated' => 'text-red-600 bg-red-50 border-red-200',
                        ];
                        $statusColor =
                            $statusColors[$user->account_status] ?? 'text-gray-500 bg-gray-50 border-gray-200';
                    @endphp
                    <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusColor }}">
                        {{ ucfirst($user->account_status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tab Navigation ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex overflow-x-auto border-b border-gray-200 scrollbar-none">
            @foreach ($tabs as $tabKey => $tabMeta)
                <button wire:click="setTab('{{ $tabKey }}')"
                    class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium whitespace-nowrap transition-colors shrink-0
                        {{ $activeTab === $tabKey
                            ? 'text-emerald-600 border-b-2 border-emerald-600 bg-emerald-50/40'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                    <i class="{{ $tabMeta['icon'] }} text-xs"></i>
                    {{ $tabMeta['label'] }}
                </button>
            @endforeach
        </div>

        <div class="p-6">

            {{-- ═══════════════════════ OVERVIEW TAB ═══════════════════════ --}}
            @if ($activeTab === 'overview')
                @if ($user->personalData)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @php
                            $fields = [
                                [
                                    'label' => 'First Name',
                                    'value' => $user->personalData->first_name,
                                    'icon' => 'fas fa-user',
                                ],
                                [
                                    'label' => 'Middle Name',
                                    'value' => $user->personalData->mid_name,
                                    'icon' => 'fas fa-user',
                                ],
                                [
                                    'label' => 'Last Name',
                                    'value' => $user->personalData->last_name,
                                    'icon' => 'fas fa-user',
                                ],
                                ['label' => 'Suffix', 'value' => $user->personalData->suffix, 'icon' => 'fas fa-user'],
                                ['label' => 'Title', 'value' => $user->personalData->title, 'icon' => 'fas fa-id-card'],
                                [
                                    'label' => 'Gender',
                                    'value' => $user->personalData->gender,
                                    'icon' => 'fas fa-venus-mars',
                                ],
                                [
                                    'label' => 'Date of Birth',
                                    'value' => $user->personalData->birth_date?->format('F d, Y'),
                                    'icon' => 'fas fa-calendar-alt',
                                ],
                                [
                                    'label' => 'Birthplace',
                                    'value' => $user->personalData->birthplace,
                                    'icon' => 'fas fa-map-pin',
                                ],
                                [
                                    'label' => 'Civil Status',
                                    'value' => $user->personalData->civil_status,
                                    'icon' => 'fas fa-heart',
                                ],
                                [
                                    'label' => 'Religion',
                                    'value' => $user->personalData->religion,
                                    'icon' => 'fas fa-church',
                                ],
                                [
                                    'label' => 'Education',
                                    'value' => $user->personalData->education,
                                    'icon' => 'fas fa-graduation-cap',
                                ],
                            ];
                        @endphp

                        @foreach ($fields as $field)
                            @if ($field['value'])
                                <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                        <i class="{{ $field['icon'] }} text-emerald-500 text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
                                            {{ $field['label'] }}</p>
                                        <p class="text-sm font-semibold text-gray-800 mt-0.5 capitalize">
                                            {{ $field['value'] }}</p>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        {{-- Member ID --}}
                        @if ($user->personalData->memberId)
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-cyan-50 border border-cyan-100">
                                <div
                                    class="w-8 h-8 rounded-lg bg-white border border-cyan-200 flex items-center justify-center shrink-0">
                                    <i class="fas fa-id-badge text-cyan-500 text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs text-cyan-500 font-medium uppercase tracking-wide">Member ID</p>
                                    <p class="text-sm font-semibold text-cyan-700 mt-0.5">
                                        {{ $user->personalData->memberId->id_number ?? '—' }}</p>
                                </div>
                            </div>
                        @endif

                        {{-- Account info --}}
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                            <div
                                class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                <i class="fas fa-envelope text-emerald-500 text-xs"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Login Email</p>
                                <p class="text-sm font-semibold text-gray-800 mt-0.5 break-all">{{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                            <div
                                class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                <i class="fas fa-clock text-emerald-500 text-xs"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Member Since</p>
                                <p class="text-sm font-semibold text-gray-800 mt-0.5">
                                    {{ $user->created_at->format('F Y') }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <i class="fas fa-user-slash text-5xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 font-medium">No personal data on file.</p>
                        <p class="text-gray-400 text-sm mt-1">Use the edit action to add your personal details.</p>
                        <button type="button" wire:click="openEditModal"
                            class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                            <i class="fas fa-user-edit text-xs"></i>
                            Complete Profile
                        </button>
                    </div>
                @endif

                {{-- ═══════════════════════ CONTACT & ADDRESS TAB ═══════════════════════ --}}
            @elseif ($activeTab === 'contact')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- Contact Details --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-phone text-emerald-500"></i> Contact Details
                        </h3>
                        @php $contact = $user->personalData?->contact; @endphp
                        @if ($contact)
                            <div class="space-y-3">
                                @foreach ([['label' => 'Phone Number', 'value' => $contact->phone_no, 'icon' => 'fas fa-phone', 'verified' => $contact->phone_verified_at], ['label' => 'Email', 'value' => $contact->email, 'icon' => 'fas fa-envelope', 'verified' => $contact->email_verified_at], ['label' => 'Facebook', 'value' => $contact->facebook, 'icon' => 'fab fa-facebook', 'verified' => null]] as $c)
                                    @if ($c['value'])
                                        <div
                                            class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                            <div
                                                class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                                <i class="{{ $c['icon'] }} text-emerald-500 text-xs"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">
                                                    {{ $c['label'] }}</p>
                                                <p class="text-sm text-gray-800 font-semibold break-all mt-0.5">
                                                    {{ $c['value'] }}</p>
                                            </div>
                                            @if ($c['verified'])
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full shrink-0">
                                                    <i class="fas fa-check-circle text-[10px]"></i> Verified
                                                </span>
                                            @else
                                                <span
                                                    class="text-xs text-gray-400 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded-full shrink-0">Unverified</span>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No contact information on file.</p>
                        @endif
                    </div>

                    {{-- Address --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-emerald-500"></i> Address
                        </h3>
                        @php $address = $user->personalData?->address; @endphp
                        @if ($address)
                            <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 space-y-2">
                                @foreach ([
        'Street' => $address->street,
        'Barangay' => $address->barangay,
        'City / Municipality' => $address->city_municipality,
        'Province' => $address->province,
        'Region' => $address->region,
        'Country' => $address->country,
        'ZIP Code' => $address->zip_code,
    ] as $label => $value)
                                    @if ($value)
                                        <div class="flex justify-between text-sm">
                                            <span
                                                class="text-gray-400 font-medium w-40 shrink-0">{{ $label }}</span>
                                            <span
                                                class="text-gray-800 font-semibold text-right">{{ $value }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No address on file.</p>
                        @endif
                    </div>
                </div>

                {{-- ═══════════════════════ EMPLOYMENT TAB ═══════════════════════ --}}
            @elseif ($activeTab === 'employment')
                @php $employment = $user->personalData?->employment; @endphp
                @if ($employment)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ([
        ['label' => 'Employment Type', 'value' => $employment->employment_type, 'icon' => 'fas fa-briefcase'],
        ['label' => 'Employment Status', 'value' => $employment->employment_status, 'icon' => 'fas fa-toggle-on'],
        ['label' => 'Employer Name', 'value' => $employment->employer_name, 'icon' => 'fas fa-building'],
        ['label' => 'Position', 'value' => $employment->position, 'icon' => 'fas fa-user-tie'],
        ['label' => 'Industry', 'value' => $employment->industry, 'icon' => 'fas fa-industry'],
        ['label' => 'Employer Contact', 'value' => $employment->employer_contact, 'icon' => 'fas fa-phone'],
        ['label' => 'Employer Address', 'value' => $employment->employer_address, 'icon' => 'fas fa-map-pin'],
        ['label' => 'Date Hired', 'value' => $employment->date_hired?->format('F d, Y'), 'icon' => 'fas fa-calendar'],
        ['label' => 'Years Employed', 'value' => $employment->years_employed ? $employment->years_employed . ' yr(s)' : null, 'icon' => 'fas fa-clock'],
        ['label' => 'Supervisor', 'value' => $employment->supervisor_name, 'icon' => 'fas fa-user-check'],
        ['label' => 'Previous Employer', 'value' => $employment->previous_employer, 'icon' => 'fas fa-history'],
        ['label' => 'Other Income Source', 'value' => $employment->other_income_source, 'icon' => 'fas fa-coins'],
    ] as $f)
                            @if ($f['value'])
                                <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                        <i class="{{ $f['icon'] }} text-emerald-500 text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
                                            {{ $f['label'] }}</p>
                                        <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $f['value'] }}</p>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        {{-- Income fields --}}
                        @if ($employment->monthly_income)
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-emerald-50 border border-emerald-100">
                                <div
                                    class="w-8 h-8 rounded-lg bg-white border border-emerald-200 flex items-center justify-center shrink-0">
                                    <i class="fas fa-peso-sign text-emerald-500 text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-emerald-500 font-medium uppercase tracking-wide">Monthly
                                        Income</p>
                                    <p class="text-sm font-bold text-emerald-700 mt-0.5">
                                        ₱{{ number_format($employment->monthly_income, 2) }}</p>
                                </div>
                            </div>
                        @endif
                        @if ($employment->other_income_amount)
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-emerald-50 border border-emerald-100">
                                <div
                                    class="w-8 h-8 rounded-lg bg-white border border-emerald-200 flex items-center justify-center shrink-0">
                                    <i class="fas fa-coins text-emerald-500 text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-emerald-500 font-medium uppercase tracking-wide">Other
                                        Income</p>
                                    <p class="text-sm font-bold text-emerald-700 mt-0.5">
                                        ₱{{ number_format($employment->other_income_amount, 2) }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <i class="fas fa-briefcase text-5xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 font-medium">No employment data on file.</p>
                    </div>
                @endif

                {{-- ═══════════════════════ FINANCIAL TAB ═══════════════════════ --}}
            @elseif ($activeTab === 'financial')
                <div class="space-y-6">

                    {{-- Account Cards --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-piggy-bank text-emerald-500"></i> My Accounts
                        </h3>
                        @if ($accounts->isNotEmpty())
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach ($accounts as $account)
                                    <div
                                        class="p-4 rounded-xl bg-linear-to-br from-emerald-50 to-cyan-50 border border-emerald-100">
                                        <div class="flex items-center justify-between mb-2">
                                            <span
                                                class="text-xs font-bold text-emerald-600 uppercase tracking-wide">{{ $account->account_type }}</span>
                                            <span class="text-xs text-gray-400">{{ $account->account_number }}</span>
                                        </div>
                                        <p class="text-2xl font-bold text-gray-800">
                                            ₱{{ number_format($account->balance, 2) }}</p>
                                        <p class="text-xs text-gray-500 mt-1">Opened
                                            {{ $account->created_at->format('M Y') }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No accounts found.</p>
                        @endif
                    </div>

                    {{-- Loans --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-hand-holding-usd text-amber-500"></i> Recent Loans
                        </h3>
                        @if ($loans->isNotEmpty())
                            <div class="overflow-x-auto rounded-xl border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                Loan #</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                Type</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                Amount</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                Status</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                Applied</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        @foreach ($loans as $loan)
                                            @php
                                                $loanStatusColors = [
                                                    'pending' => 'bg-gray-100 text-gray-600',
                                                    'approved' => 'bg-emerald-100 text-emerald-700',
                                                    'disbursed' => 'bg-cyan-100 text-cyan-700',
                                                    'active' => 'bg-blue-100 text-blue-700',
                                                    'paid' => 'bg-emerald-100 text-emerald-700',
                                                    'rejected' => 'bg-red-100 text-red-600',
                                                    'defaulted' => 'bg-red-100 text-red-700',
                                                ];
                                                $lsc =
                                                    $loanStatusColors[$loan->loan_status] ??
                                                    'bg-gray-100 text-gray-600';
                                            @endphp
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-4 py-3 font-mono text-xs text-gray-700">
                                                    {{ $loan->loan_number }}</td>
                                                <td class="px-4 py-3 text-gray-700 capitalize">
                                                    {{ str_replace('_', ' ', $loan->loan_type) }}</td>
                                                <td class="px-4 py-3 font-semibold text-gray-800">
                                                    ₱{{ number_format($loan->loan_amount, 2) }}</td>
                                                <td class="px-4 py-3">
                                                    <span
                                                        class="px-2 py-0.5 rounded-full text-xs font-medium {{ $lsc }} capitalize">{{ $loan->loan_status }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-gray-500 text-xs">
                                                    {{ $loan->applied_at?->format('M d, Y') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No loan records found.</p>
                        @endif
                    </div>
                </div>

                {{-- ═══════════════════════ BENEFICIARIES TAB ═══════════════════════ --}}
            @elseif ($activeTab === 'beneficiaries')
                @if ($user->beneficiaries->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($user->beneficiaries as $b)
                            <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 flex items-start gap-4">
                                <div
                                    class="w-10 h-10 rounded-full bg-linear-to-br from-purple-400 to-pink-400 flex items-center justify-center text-white font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($b->first_name, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800">
                                        {{ trim($b->first_name . ' ' . ($b->mid_name ? strtoupper(substr($b->mid_name, 0, 1)) . '. ' : '') . $b->last_name) }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5 capitalize">{{ $b->relationship }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        @if ($b->birth_date)
                                            <span class="flex items-center gap-1"><i
                                                    class="fas fa-birthday-cake text-pink-400"></i>{{ $b->birth_date->format('M d, Y') }}</span>
                                        @endif
                                        @if ($b->phone_no)
                                            <span class="flex items-center gap-1"><i
                                                    class="fas fa-phone text-emerald-400"></i>{{ $b->phone_no }}</span>
                                        @endif
                                    </div>
                                    @if ($b->barangay || $b->city || $b->province)
                                        <p class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            {{ collect([$b->street, $b->barangay, $b->city, $b->province])->filter()->implode(', ') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <i class="fas fa-users text-5xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 font-medium">No beneficiaries on file.</p>
                        <p class="text-gray-400 text-sm mt-1">Beneficiary records will appear here once added.</p>
                    </div>
                @endif

                {{-- ═══════════════════════ SECURITY TAB ═══════════════════════ --}}
            @elseif ($activeTab === 'security')
                <div class="space-y-6">

                    {{-- Account Security Info --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-key text-emerald-500"></i> Account Security
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                <div
                                    class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                    <i class="fas fa-envelope text-emerald-500 text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Login Email
                                    </p>
                                    <p class="text-sm font-semibold text-gray-800 mt-0.5 break-all">
                                        {{ $user->email }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                <div
                                    class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                    <i class="fas fa-calendar-check text-emerald-500 text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Account
                                        Created</p>
                                    <p class="text-sm font-semibold text-gray-800 mt-0.5">
                                        {{ $user->created_at->format('F d, Y') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Trusted Devices --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-laptop text-emerald-500"></i> Trusted Devices
                        </h3>
                        @if ($trustedDevices->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($trustedDevices as $device)
                                    <div
                                        class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                        <div
                                            class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0">
                                            <i class="fas fa-desktop text-gray-400 text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-800 truncate">
                                                {{ $device->device_label ?? ($device->user_agent ?? 'Unknown Device') }}
                                            </p>
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                Added {{ $device->created_at->diffForHumans() }}
                                                @if ($device->last_used_at)
                                                    · Last used {{ $device->last_used_at->diffForHumans() }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No trusted devices registered.</p>
                        @endif
                    </div>

                    {{-- Recent Activity Logs --}}
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-history text-emerald-500"></i> Recent Activity
                        </h3>
                        @if ($recentLogs->isNotEmpty())
                            <div class="space-y-2">
                                @foreach ($recentLogs as $log)
                                    <div
                                        class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                        <div class="w-2 h-2 rounded-full bg-emerald-400 mt-1.5 shrink-0"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-700">
                                                {{ $log->activity ?? ($log->description ?? 'Activity recorded') }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                {{ $log->created_at->format('M d, Y h:i A') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No recent activity.</p>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>

    <x-modals.profile-edit wire:key="profile-edit-modal" />
</div>
