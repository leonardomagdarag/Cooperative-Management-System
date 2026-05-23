@props(['officer' => null, 'isEdit' => false, 'tabErrors' => []])

@php
    $canManageOfficers = Auth::user()?->can('officers.update') ?? false;
    $isAddMode = !$isEdit;

    $wizardSteps = ['personal', 'contact', 'role', 'login'];
    $stepLabels = [
        'personal' => 'Personal Info',
        'contact' => 'Contact Info',
        'role' => 'Officer Role',
        'login' => 'Login Info',
    ];

    // Role selection data
    $roleKeys = ['manager', 'secretary', 'loan', 'treasurer'];
    $adminRole = 'admin';
    $roleDisplayNames = \App\Support\PermissionCatalog::roleDisplayNames();
    $roleDescriptions = \App\Support\PermissionCatalog::roleDescriptions();
    $rolePresets = \App\Support\PermissionCatalog::rolePresets();
    $allGroups = \App\Support\PermissionCatalog::groups();
@endphp

<div {{ $attributes }} x-data="{
    show: @entangle('showEditModal'),
    formTab: 'personal',
    isAdd: {{ $isAddMode ? 'true' : 'false' }},
    stepOrder: ['personal', 'contact', 'role', 'login'],

    async goToTab(nextTab) {
        if (this.isAdd) {
            return;
        }

        try {
            await $wire.validateTab(this.formTab);
            this.formTab = nextTab;
        } catch (e) {}
    },

    async nextBtn() {
        if (!this.isAdd) {
            return;
        }

        const nextTab = await $wire.nextStep(this.formTab);
        if (nextTab) {
            this.formTab = nextTab;
        }
    },

    prevBtn() {
        const idx = this.stepOrder.indexOf(this.formTab);
        if (idx > 0) {
            this.formTab = this.stepOrder[idx - 1];
        }
    },
}" x-init="$watch('show', val => { if (val) formTab = 'personal'; });
$wire.on('wizard-advance', ({ tab }) => { formTab = tab; });" x-show="show" x-cloak
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden px-4 sm:px-6 outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black opacity-25"
        @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"></div>

    <div class="relative w-full max-w-5xl h-[90vh] mx-auto z-10"
        x-transition:enter="transition ease-out duration-200 delay-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-white rounded-lg shadow-xl h-full flex flex-col overflow-hidden">
            <div
                class="flex items-start justify-between p-6 border-b border-gray-200 rounded-t bg-linear-to-r from-cyan-500 to-emerald-500">
                <div>
                    <h3 class="text-2xl font-bold text-white">
                        {{ $isEdit ? 'Edit Officer' : 'Add New Officer' }}
                    </h3>
                    <p class="text-sm text-cyan-50 mt-1">
                        {{ $isEdit ? 'Update officer information' : 'Complete the setup steps to create an officer account' }}
                    </p>
                </div>
                <button type="button" @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"
                    class="text-white hover:text-cyan-100 hover:bg-white/10 rounded-lg text-sm p-2 transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-3 bg-blue-50 border-b border-blue-100">
                <p class="text-sm text-blue-700">
                    <svg class="inline-block w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd" />
                    </svg>
                    Fields marked with <span class="text-red-500 font-semibold">*</span> are required
                </p>
            </div>

            @if ($isAddMode)
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 overflow-x-auto">
                    <ol class="flex min-w-full items-center gap-0">
                        @foreach ($wizardSteps as $i => $step)
                            @php
                                $stepNumber = $i + 1;
                                $isLast = $i === count($wizardSteps) - 1;
                                $laterSteps = array_slice($wizardSteps, $i + 1);
                            @endphp
                            <li class="flex items-center {{ $isLast ? '' : 'flex-1' }}">
                                <div class="flex flex-col items-center">
                                    <div x-bind:class="formTab === '{{ $step }}'
                                        ?
                                        'bg-cyan-500 text-white ring-2 ring-cyan-300' :
                                        ({{ json_encode($laterSteps) }}.includes(formTab) ?
                                            'bg-emerald-500 text-white' :
                                            'bg-gray-200 text-gray-500')"
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors">
                                        <span
                                            x-show="!{{ json_encode($laterSteps) }}.includes(formTab)">{{ $stepNumber }}</span>
                                        <span x-show="{{ json_encode($laterSteps) }}.includes(formTab)">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </div>
                                    <span
                                        x-bind:class="formTab === '{{ $step }}' ? 'text-cyan-600 font-semibold' :
                                            'text-gray-500'"
                                        class="mt-1 text-xs sm:text-sm whitespace-nowrap transition-colors">
                                        {{ $stepLabels[$step] }}
                                    </span>
                                </div>
                                @if (!$isLast)
                                    <div class="flex-1 h-0.5 mx-1 -mt-3.5"
                                        x-bind:class="{{ json_encode($laterSteps) }}.includes(formTab) ? 'bg-emerald-400' :
                                            'bg-gray-200'">
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="flex border-b border-gray-200 bg-gray-50 px-6 overflow-x-auto">
                    @foreach ($wizardSteps as $step)
                        <button type="button" @click="goToTab('{{ $step }}')"
                            :class="formTab === '{{ $step }}'
                                ?
                                'border-cyan-500 text-cyan-600' :
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-5 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                            {{ $stepLabels[$step] }}
                            @if (($tabErrors[$step] ?? false) || ($this->tabRequiredErrors[$step] ?? false))
                                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="p-6 overflow-y-auto flex-1">
                <form wire:submit.prevent="saveOfficer">
                    <div x-show="formTab === 'personal'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-forms.text-input name="first_name" label="First Name" model="form.first_name"
                                :required="true" placeholder="Enter first name" :validateOnBlur="true" :defer="true" />
                            <x-forms.text-input name="mid_name" label="Middle Name" model="form.mid_name"
                                placeholder="Enter middle name" :validateOnBlur="true" :defer="true" />
                            <x-forms.text-input name="last_name" label="Last Name" model="form.last_name"
                                :required="true" placeholder="Enter last name" :validateOnBlur="true" :defer="true" />
                            <x-forms.text-input name="birth_date" label="Date of Birth" model="form.birth_date"
                                :required="true" type="date"
                                helperText="Must be at least {{ config('settings.requirement.age', 18) }} years old"
                                :validateOnBlur="true" :defer="true" />
                            <x-forms.select-input name="gender" label="Gender" model="form.gender" :required="true"
                                :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :validateOnBlur="true" :defer="true" />
                            <x-forms.select-input name="civil_status" label="Civil Status" model="form.civil_status"
                                :required="true" :options="[
                                    'single' => 'Single',
                                    'married' => 'Married',
                                    'widowed' => 'Widowed',
                                    'separated' => 'Separated',
                                ]" :validateOnBlur="true" :defer="true" />
                            <x-forms.select-input name="title" label="Officer Title" model="form.title"
                                :required="true" :options="[
                                    'Chairman' => 'Chairman',
                                    'Vice Chairman' => 'Vice Chairman',
                                    'Secretary' => 'Secretary',
                                    'Treasurer' => 'Treasurer',
                                    'Loan Officer' => 'Loan Officer',
                                ]" :validateOnBlur="true" :defer="true" />
                        </div>
                    </div>

                    <div x-show="formTab === 'contact'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-forms.text-input name="email" label="Email Address" model="form.email"
                                type="email" :required="true" placeholder="email@example.com"
                                helperText="Primary contact email" :validateOnBlur="true" :defer="true" />
                            <x-forms.text-input name="phone_number" label="Phone Number" model="form.phone_number"
                                :required="true" type="tel" placeholder="09XX XXX XXXX" maxlength="11"
                                helperText="11-digit mobile number" :validateOnBlur="true" :defer="true" />
                        </div>
                    </div>

                    {{-- ─── Role Selection Tab ──────────────────────────────────── --}}
                    <div x-show="formTab === 'role'" x-transition x-data="{ roleVal: @entangle('form.role').live }" class="space-y-5">

                        {{-- Admin role card (separated) --}}
                        <p class="text-xs font-semibold uppercase tracking-wider text-rose-500">Privileged Role</p>
                        <label @click="roleVal = 'admin'"
                            :class="roleVal === 'admin'
                                ?
                                'ring-2 ring-rose-500 border-rose-500 bg-rose-50' :
                                'border-rose-200 bg-white hover:border-rose-300 hover:bg-rose-50/40'"
                            class="cursor-pointer rounded-xl border-2 p-4 transition-all flex items-start gap-3">
                            <input type="radio" wire:model.live="form.role" value="admin" class="sr-only">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900 text-sm">Administrator</span>
                                        <span
                                            class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Full
                                            Access</span>
                                    </div>
                                    <span
                                        :class="roleVal === 'admin' ? 'opacity-100 scale-100' : 'opacity-0 scale-75'"
                                        class="inline-flex shrink-0 items-center justify-center w-5 h-5 rounded-full bg-rose-500 text-white transition-all duration-150">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 leading-relaxed">{{ $roleDescriptions['admin'] }}
                                </p>
                            </div>
                        </label>

                        {{-- Officer role cards --}}
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Officer Roles</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($roleKeys as $roleKey)
                                <label @click="roleVal = '{{ $roleKey }}'"
                                    :class="roleVal === '{{ $roleKey }}'
                                        ?
                                        'ring-2 ring-cyan-500 border-cyan-500 bg-cyan-50' :
                                        'border-gray-200 bg-white hover:border-cyan-300 hover:bg-gray-50'"
                                    class="cursor-pointer rounded-xl border-2 p-4 transition-all flex items-start gap-3">
                                    <input type="radio" wire:model.live="form.role" value="{{ $roleKey }}"
                                        class="sr-only">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-semibold text-gray-900 text-sm">
                                                {{ $roleDisplayNames[$roleKey] }}
                                            </span>
                                            <span
                                                :class="roleVal === '{{ $roleKey }}' ? 'opacity-100 scale-100' :
                                                    'opacity-0 scale-75'"
                                                class="inline-flex shrink-0 items-center justify-center w-5 h-5 rounded-full bg-cyan-500 text-white transition-all duration-150">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                    stroke-width="3" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                                            {{ $roleDescriptions[$roleKey] }}
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        {{-- Permission detail panel (pre-rendered per role, shown by Alpine) --}}
                        <div class="rounded-xl border border-gray-200 overflow-hidden">
                            <div
                                class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-700">Default Permissions</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="w-2 h-2 rounded-full bg-teal-500 inline-block"></span>
                                            Granted
                                        </span>
                                        &nbsp;·&nbsp;
                                        <span class="inline-flex items-center gap-1">
                                            <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
                                            Not Granted
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {{-- Admin: all permissions --}}
                            <div x-show="roleVal === 'admin'" class="p-4">
                                <div
                                    class="flex items-center gap-2 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3">
                                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-rose-700 font-medium">Administrators have unrestricted
                                        access to all system features and permissions.</p>
                                </div>
                            </div>

                            @foreach ($roleKeys as $roleKey)
                                <div x-show="roleVal === '{{ $roleKey }}'" class="p-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        @foreach ($allGroups as $groupName => $groupPerms)
                                            @php
                                                $granted = array_values(
                                                    array_intersect($groupPerms, $rolePresets[$roleKey]),
                                                );
                                                $notGranted = array_values(
                                                    array_diff($groupPerms, $rolePresets[$roleKey]),
                                                );
                                            @endphp
                                            <div>
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">
                                                    {{ $groupName }}
                                                </p>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($granted as $perm)
                                                        <span
                                                            class="inline-flex items-center gap-0.5 rounded-full bg-teal-50 border border-teal-200 px-2 py-0.5 text-xs font-medium text-teal-700">
                                                            <svg class="w-2.5 h-2.5 shrink-0" fill="none"
                                                                stroke="currentColor" stroke-width="2.5"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            {{ \App\Support\PermissionCatalog::formatLabel($perm) }}
                                                        </span>
                                                    @endforeach
                                                    @foreach ($notGranted as $perm)
                                                        <span
                                                            class="inline-flex items-center gap-0.5 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs font-medium text-gray-400">
                                                            <svg class="w-2.5 h-2.5 shrink-0" fill="none"
                                                                stroke="currentColor" stroke-width="2.5"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                            {{ \App\Support\PermissionCatalog::formatLabel($perm) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Settings note --}}
                        <div
                            class="flex items-start gap-2.5 rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-sm text-blue-700">
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>
                                These are the <strong>default permissions</strong> for this role.
                                You can customize which permissions each role has under
                                <strong>Settings → Role Permissions</strong>.
                            </span>
                        </div>
                    </div>

                    <div x-show="formTab === 'login'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if ($isEdit && !$canManageOfficers)
                                <div class="md:col-span-2">
                                    <x-forms.text-input name="current_password" label="Current Password"
                                        model="form.current_password" type="password" autocomplete="current-password"
                                        placeholder="Enter current password"
                                        helperText="Required when changing the password" :validateOnBlur="true"
                                        :defer="true" />
                                </div>
                            @endif

                            <div
                                class="md:col-span-2 rounded-xl border border-cyan-100 bg-linear-to-r from-cyan-50 to-emerald-50 p-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-700">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="text-sm text-gray-700">
                                        <p class="font-semibold text-gray-900">Password guidance</p>
                                        <p class="mt-1 leading-6">
                                            Use at least 6 characters and combine letters with numbers.
                                            Add uppercase letters and symbols for better strength.
                                            Avoid names, birthdays, and simple sequences like 12345.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <x-forms.text-input name="password" label="Password" model="form.password"
                                    type="password" :required="$isAddMode" placeholder="Enter password"
                                    autocomplete="new-password"
                                    helperText="Use letters and numbers; add symbols if possible." :validateOnBlur="true"
                                    :defer="true"
                                    x-on:input="window.__officerPasswordStrengthUpdate && window.__officerPasswordStrengthUpdate()"
                                    x-on:change="window.__officerPasswordStrengthUpdate && window.__officerPasswordStrengthUpdate()"
                                    x-on:keyup="window.__officerPasswordStrengthUpdate && window.__officerPasswordStrengthUpdate()"
                                    x-on:focusout="window.__officerPasswordStrengthUpdate && window.__officerPasswordStrengthUpdate()" />

                                <div
                                    class="mt-3 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                                    <span
                                        class="text-xs font-semibold uppercase tracking-wide text-gray-500">Strength:</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div id="officer-password-strength-bar"
                                            class="h-full w-0 rounded-full bg-gray-300 transition-all duration-200">
                                        </div>
                                    </div>
                                    <span id="officer-password-strength-label"
                                        class="min-w-16 text-sm font-semibold text-gray-700">Weak</span>
                                    <button type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-cyan-600 transition-colors hover:bg-cyan-50 hover:text-cyan-700"
                                        title="Use at least 6 characters, combine letters and numbers, add a capital letter and a special character if possible, and avoid names, birthdays, or simple sequences like 12345."
                                        aria-label="Password guidance">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <x-forms.text-input name="password_confirmation" label="Confirm Password"
                                    model="form.password_confirmation" type="password" :required="$isAddMode"
                                    autocomplete="new-password" placeholder="Re-enter password"
                                    helperText="Re-enter the same password to continue." :validateOnBlur="true"
                                    :defer="true" />
                            </div>
                        </div>

                        @if ($isAddMode)
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-amber-600 mt-0.5" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <div class="text-sm text-amber-800">
                                        <p class="font-medium">Initial Login Credentials</p>
                                        <p class="mt-1">The officer will use the email and password set here to log
                                            into the system.</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-6 border-t border-gray-200 mt-8">
                        <div>
                            @if ($isAddMode && $wizardSteps[0] !== 'login')
                                <button type="button" x-show="formTab !== 'personal'" @click="prevBtn()"
                                    class="text-gray-600 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                                    Previous
                                </button>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"
                                class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10">
                                Cancel
                            </button>

                            @if ($isAddMode)
                                <button type="button" x-show="formTab !== 'login'" @click="nextBtn()"
                                    wire:loading.attr="disabled" wire:target="nextStep"
                                    class="text-white bg-linear-to-r from-cyan-500 to-blue-500 hover:bg-linear-to-bl focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    Next
                                </button>

                                <button type="submit" x-show="formTab === 'login'"
                                    @if (!$this->isFormValid) disabled @endif wire:loading.attr="disabled"
                                    class="text-white bg-linear-to-r from-cyan-500 to-blue-500 hover:bg-linear-to-bl focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="saveOfficer">Create Officer</span>
                                    <span wire:loading wire:target="saveOfficer">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            @else
                                <button type="submit" @if (!$this->isFormValid) disabled @endif
                                    wire:loading.attr="disabled"
                                    class="text-white bg-linear-to-r from-cyan-500 to-blue-500 hover:bg-linear-to-bl focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="saveOfficer">
                                        Update Officer
                                    </span>
                                    <span wire:loading wire:target="saveOfficer">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] {
        display: none !important;
    }
</style>

<script>
    (() => {
        if (window.__officerFormStrengthBound) {
            return;
        }

        window.__officerFormStrengthBound = true;
        window.__officerPasswordStrengthUpdate = () => {
            const strengthLabel = document.getElementById('officer-password-strength-label');
            const strengthBar = document.getElementById('officer-password-strength-bar');

            if (!strengthLabel || !strengthBar) {
                return;
            }

            const getNameTokens = () => ['first_name', 'mid_name', 'last_name']
                .map((id) => document.getElementById(id)?.value || '')
                .map((value) => value.toLowerCase().replace(/[^a-z]+/g, ''))
                .filter((value) => value.length >= 3);

            const passwordInput = document.getElementById('password');
            const value = passwordInput?.value || '';
            const normalized = value.toLowerCase();

            const setState = (label, labelClass, barClass, width) => {
                strengthLabel.textContent = label;
                strengthLabel.className = `min-w-16 text-sm font-semibold ${labelClass}`;
                strengthBar.className = `h-full rounded-full transition-all duration-200 ${barClass}`;
                strengthBar.style.width = width;
            };

            if (!value) {
                setState('Weak', 'text-gray-700', 'bg-gray-300', '0%');
                return;
            }

            const hasLetters = /[a-z]/i.test(value);
            const hasNumbers = /\d/.test(value);
            const hasUppercase = /[A-Z]/.test(value);
            const hasSpecial = /[^A-Za-z0-9]/.test(value);
            const looksPredictable = getNameTokens().some((token) => normalized.includes(token) && /\d{3,}/
                .test(normalized));

            if (looksPredictable) {
                setState('Too weak', 'text-red-700', 'bg-red-500', '20%');
                return;
            }

            if (hasLetters && hasNumbers && hasUppercase && hasSpecial) {
                setState('Strong', 'text-emerald-700', 'bg-emerald-500', '100%');
                return;
            }

            if (hasLetters && hasNumbers && hasUppercase) {
                setState('Medium', 'text-cyan-700', 'bg-cyan-500', '70%');
                return;
            }

            if (hasLetters && hasNumbers) {
                setState('Weak', 'text-amber-700', 'bg-amber-500', '45%');
                return;
            }

            setState('Weak', 'text-gray-700', 'bg-gray-300', '25%');
        };

        document.addEventListener('input', (event) => {
            if (event.target && ['password', 'first_name', 'mid_name', 'last_name'].includes(event.target
                    .id)) {
                window.__officerPasswordStrengthUpdate();
            }
        });

        document.addEventListener('keyup', (event) => {
            if (event.target && ['password', 'first_name', 'mid_name', 'last_name'].includes(event.target
                    .id)) {
                window.__officerPasswordStrengthUpdate();
            }
        });

        document.addEventListener('blur', (event) => {
            if (event.target && event.target.id === 'password') {
                window.__officerPasswordStrengthUpdate();
            }
        }, true);

        document.addEventListener('DOMContentLoaded', () => {
            window.__officerPasswordStrengthUpdate();
        });

        window.__officerPasswordStrengthUpdate();
    })();
</script>
