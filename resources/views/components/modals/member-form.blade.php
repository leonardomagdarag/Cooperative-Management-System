@props(['isEdit' => false, 'tabErrors' => [], 'form' => []])

@php
    $canManageMembers = Auth::user()->can('members.update');
    $isAddMode = !$isEdit;

    // Ordered steps for add-mode wizard progress bar
    $wizardSteps = ['personal', 'address', 'contact', 'ids', 'employment', 'login', 'review'];

    $stepLabels = [
        'personal' => 'Personal Info',
        'address' => 'Address',
        'contact' => 'Contact Info',
        'ids' => 'Gov\'t IDs',
        'employment' => 'Employment',
        'login' => 'Login Info',
        'review' => 'Review',
    ];
@endphp

<div {{ $attributes }} x-data="{
    show: @entangle('showEditModal'),
    formTab: 'personal',
    isAdd: {{ $isAddMode ? 'true' : 'false' }},

    // Edit-mode: validate current tab then jump freely
    async goToTab(nextTab) {
        if (this.isAdd) { return; }
        try {
            await $wire.validateTab(this.formTab);
            this.formTab = nextTab;
        } catch (e) { /* stay */ }
    },

    // Next button handler
    async nextBtn() {
        if (!this.isAdd) {
            // edit-mode: advance without re-validation
            const order = ['personal', 'address', 'contact', 'ids', 'employment', 'login'];
            const idx = order.indexOf(this.formTab);
            if (idx !== -1 && idx + 1 < order.length) { this.formTab = order[idx + 1]; }
            return;
        }
        // add-mode: server validates, dispatches wizard-advance on success
        await $wire.nextStep(this.formTab);
    },

    // Previous button: go back with no validation
    prevBtn() {
        const order = this.isAdd ? ['personal', 'address', 'contact', 'ids', 'employment', 'login', 'review'] : ['personal', 'address', 'contact', 'ids', 'employment', 'login'];
        const idx = order.indexOf(this.formTab);
        if (idx > 0) { this.formTab = order[idx - 1]; }
    },
}" x-init="$watch('show', val => { if (val) formTab = 'personal'; });
$wire.on('wizard-advance', ({ tab }) => { formTab = tab; });" x-show="show" x-cloak
    @member-saved.window="show = false" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden px-4 sm:px-6 outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black opacity-25" @click="show = false; $wire.closeEditModal()"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-5xl h-[90vh] mx-auto z-10"
        x-transition:enter="transition ease-out duration-200 delay-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-white rounded-lg shadow-xl h-full flex flex-col overflow-hidden">
            {{-- Modal header with gradient --}}
            <div
                class="flex items-start justify-between p-6 border-b border-gray-200 rounded-t bg-linear-to-r from-cyan-500 to-emerald-500">
                <div>
                    <h3 class="text-2xl font-bold text-white">
                        {{ $isEdit ? 'Edit Member' : 'Add New Member' }}
                    </h3>
                    <p class="text-sm text-cyan-50 mt-1">
                        {{ $isEdit ? 'Update member information' : 'Complete all required fields' }}</p>
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

            {{-- Required Fields Notice --}}
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

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- ADD MODE: numbered stepper — non-clickable progress bar --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            @if ($isAddMode)
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 overflow-x-auto">
                    <ol class="flex min-w-full items-center gap-0">
                        @php
                            $steps = $wizardSteps;
                            $total = count($steps);
                        @endphp
                        @foreach ($steps as $i => $step)
                            @php
                                $label = $stepLabels[$step];
                                $stepNumber = $i + 1;
                                $isLast = $i === $total - 1;
                                // Steps that come after the current visible step
                                $laterSteps = array_slice($steps, $i + 1);
                            @endphp
                            <li class="flex items-center {{ $isLast ? '' : 'flex-1' }}">
                                {{-- Circle + label --}}
                                <div class="flex flex-col items-center">
                                    <div x-bind:class="formTab === '{{ $step }}'
                                        ?
                                        'bg-cyan-500 text-white ring-2 ring-cyan-300' :
                                        ({{ json_encode($laterSteps) }}.includes(formTab) || formTab === 'review' ?
                                            'bg-emerald-500 text-white' :
                                            'bg-gray-200 text-gray-500')"
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors">
                                        {{-- Checkmark when step is complete (a later step is active) --}}
                                        <span
                                            x-show="{{ json_encode($laterSteps) }}.includes(formTab) || (formTab === 'review' && '{{ $step }}' !== 'review')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span
                                            x-show="!({{ json_encode($laterSteps) }}.includes(formTab) || (formTab === 'review' && '{{ $step }}' !== 'review'))">{{ $stepNumber }}</span>
                                    </div>
                                    <span
                                        x-bind:class="formTab === '{{ $step }}' ? 'text-cyan-600 font-semibold' :
                                            'text-gray-500'"
                                        class="mt-1 text-xs sm:text-sm whitespace-nowrap transition-colors">
                                        {{ $label }}
                                    </span>
                                </div>
                                {{-- Connector line --}}
                                @if (!$isLast)
                                    <div class="flex-1 h-0.5 mx-1 -mt-3.5"
                                        x-bind:class="{{ json_encode($laterSteps) }}.includes(formTab) || (formTab === 'review' &&
                                            '{{ $step }}'
                                            !== 'review') ? 'bg-emerald-400' : 'bg-gray-200'">
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- ──────────────────────────────────────────────────────── --}}
                {{-- EDIT MODE: clickable tabs                                --}}
                {{-- ──────────────────────────────────────────────────────── --}}
            @else
                <div class="flex border-b border-gray-200 bg-gray-50 px-6 overflow-x-auto">
                    @foreach (['personal' => 'Personal Info', 'address' => 'Address', 'contact' => 'Contact Info', 'ids' => 'Government IDs', 'employment' => 'Employment'] as $tab => $label)
                        <button @click="goToTab('{{ $tab }}')"
                            :class="formTab === '{{ $tab }}'
                                ?
                                'border-cyan-500 text-cyan-600' :
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-5 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                            {{ $label }}
                            @if (($tabErrors[$tab] ?? false) || ($this->tabRequiredErrors[$tab] ?? false))
                                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                            @endif
                        </button>
                    @endforeach
                    @if ($canManageMembers)
                        <button @click="goToTab('login')"
                            :class="formTab === 'login'
                                ?
                                'border-cyan-500 text-cyan-600' :
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-5 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                            Login Info
                            @if (($tabErrors['login'] ?? false) || ($this->tabRequiredErrors['login'] ?? false))
                                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                            @endif
                        </button>
                    @endif
                </div>
            @endif

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- Modal body                                               --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="p-6 overflow-y-auto flex-1">

                {{-- Tab 1: Personal Information --}}
                <div x-show="formTab === 'personal'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-forms.text-input name="first_name" label="First Name" model="form.first_name"
                            :required="true" placeholder="Enter first name"
                            helperText="Legal first name as it appears on ID" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="mid_name" label="Middle Name" model="form.mid_name"
                            placeholder="Enter middle name" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="last_name" label="Last Name" model="form.last_name" :required="true"
                            placeholder="Enter last name" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="suffix" label="Suffix" model="form.suffix"
                            placeholder="Jr., Sr., III, etc." maxlength="50" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="birth_date" label="Date of Birth" model="form.birth_date"
                            :required="true" type="date"
                            helperText="Must be at least {{ config('settings.requirement.age', 18) }} years old"
                            :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="birthplace" label="Place of Birth" model="form.birthplace"
                            placeholder="City, Province" :validateOnBlur="true" :defer="true" />
                        <x-forms.select-input name="gender" label="Gender" model="form.gender" :required="true"
                            :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :validateOnBlur="true" :defer="true" />
                        <x-forms.select-input name="civil_status" label="Civil Status" model="form.civil_status"
                            :required="true" :options="[
                                'single' => 'Single',
                                'married' => 'Married',
                                'widowed' => 'Widowed',
                                'separated' => 'Separated',
                            ]" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="religion" label="Religion" model="form.religion"
                            placeholder="Enter religion" :validateOnBlur="true" :defer="true" />
                    </div>
                </div>

                {{-- Tab 2: Address --}}
                <div x-show="formTab === 'address'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-forms.text-input name="street" label="Street Address" model="form.street"
                            placeholder="House No., Street Name" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="barangay" label="Barangay" model="form.barangay"
                            placeholder="Enter barangay" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="city" label="City/Municipality" model="form.city"
                            placeholder="Enter city or municipality" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="province" label="Province" model="form.province"
                            placeholder="Enter province" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="region" label="Region" model="form.region"
                            placeholder="e.g., Region IV-A" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="postal_code" label="Postal/ZIP Code" model="form.postal_code"
                            placeholder="Enter postal code" maxlength="20" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="country" label="Country" model="form.country"
                            placeholder="Philippines" :validateOnBlur="true" :defer="true" />
                    </div>
                </div>

                {{-- Tab 3: Contact Information --}}
                <div x-show="formTab === 'contact'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-forms.text-input name="email" label="Email Address" model="form.email" type="email"
                            :required="true" placeholder="email@example.com" helperText="Primary contact email"
                            :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="phone_number" label="Phone Number" model="form.phone_number"
                            :required="true" type="tel" placeholder="09XX XXX XXXX" maxlength="11"
                            helperText="11-digit mobile number" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="mobile_number" label="Alternate Mobile" model="form.mobile_number"
                            type="tel" placeholder="09XX XXX XXXX" maxlength="11" :validateOnBlur="true"
                            :defer="true" />
                        <x-forms.text-input name="emergency_contact" label="Emergency Contact"
                            model="form.emergency_contact" placeholder="Name and number" :validateOnBlur="true"
                            :defer="true" />
                        <div class="md:col-span-3">
                            <x-forms.text-input name="facebook_link" label="Facebook Profile"
                                model="form.facebook_link" type="url" placeholder="https://facebook.com/username"
                                helperText="Optional: Your Facebook profile URL" :validateOnBlur="true"
                                :defer="true" />
                        </div>
                    </div>
                </div>

                {{-- Tab 4: Government IDs --}}
                <div x-show="formTab === 'ids'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-forms.text-input name="national_id" label="National ID (PhilSys)" model="form.national_id"
                            placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="64" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="tin" label="TIN (Tax Identification Number)" model="form.tin"
                            placeholder="XXX-XXX-XXX-XXX" maxlength="64" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="sss" label="SSS Number" model="form.sss"
                            placeholder="XX-XXXXXXX-X" maxlength="64" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="philhealth" label="PhilHealth Number" model="form.philhealth"
                            placeholder="XX-XXXXXXXXX-X" maxlength="64" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="barangay_id" label="Barangay ID" model="form.barangay_id"
                            placeholder="Enter barangay ID" maxlength="64" :validateOnBlur="true" :defer="true" />
                    </div>
                </div>

                {{-- Tab 5: Employment --}}
                <div x-show="formTab === 'employment'" x-transition class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-forms.select-input name="employment_type" label="Employment Type"
                            model="form.employment_type" :options="[
                                'full-time' => 'Full-time',
                                'part-time' => 'Part-time',
                                'self-employed' => 'Self-employed',
                                'freelancer' => 'Freelancer',
                                'government' => 'Government',
                                'private' => 'Private',
                                'ofw' => 'OFW',
                                'retired' => 'Retired',
                                'unemployed' => 'Unemployed',
                                'student' => 'Student',
                                'homemaker' => 'Homemaker',
                            ]" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="employer_name" label="Company/Employer Name"
                            model="form.employer_name" placeholder="Enter company name" :validateOnBlur="true"
                            :defer="true" />
                        <x-forms.text-input name="position" label="Position/Job Title" model="form.position"
                            placeholder="Enter position" :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="monthly_income" label="Monthly Income" model="form.monthly_income"
                            type="number" placeholder="0.00" prefix="₱" helperText="Enter amount in PHP"
                            :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="other_income_source" label="Other Income Source"
                            model="form.other_income_source" placeholder="e.g., Business, Investments"
                            :validateOnBlur="true" :defer="true" />
                        <x-forms.text-input name="other_income_amount" label="Other Income Amount"
                            model="form.other_income_amount" type="number" placeholder="0.00" prefix="₱"
                            :validateOnBlur="true" :defer="true" />
                    </div>
                </div>

                {{-- Tab 6: Login Information --}}
                @if ($isAddMode || $canManageMembers)
                    <div x-show="formTab === 'login'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if ($isEdit && !$canManageMembers)
                                <div class="md:col-span-2">
                                    <x-forms.text-input name="current_password" label="Current Password"
                                        model="form.current_password" type="password" autocomplete="current-password"
                                        placeholder="Enter current password" helperText="Required to change password"
                                        :validateOnBlur="true" :defer="true" />
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
                                            Add capital letters and special characters to make it stronger.
                                            Avoid names, birthdays, member IDs, and simple sequences like 12345.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <x-forms.text-input name="password" label="Password" model="form.password"
                                    type="password" :required="$isAddMode" placeholder="Enter password"
                                    autocomplete="new-password"
                                    helperText="Use letters and numbers to start; uppercase letters and symbols make it stronger."
                                    :validateOnBlur="true" :defer="true"
                                    x-on:input="window.__memberPasswordStrengthUpdate && window.__memberPasswordStrengthUpdate()"
                                    x-on:change="window.__memberPasswordStrengthUpdate && window.__memberPasswordStrengthUpdate()"
                                    x-on:keyup="window.__memberPasswordStrengthUpdate && window.__memberPasswordStrengthUpdate()"
                                    x-on:focusout="window.__memberPasswordStrengthUpdate && window.__memberPasswordStrengthUpdate()" />

                                <div
                                    class="mt-3 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                                    <span
                                        class="text-xs font-semibold uppercase tracking-wide text-gray-500">Strength:</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div id="member-password-strength-bar"
                                            class="h-full w-0 rounded-full bg-gray-300 transition-all duration-200">
                                        </div>
                                    </div>
                                    <span id="member-password-strength-label"
                                        class="min-w-16 text-sm font-semibold text-gray-700">Weak</span>
                                    <button type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-cyan-600 transition-colors hover:bg-cyan-50 hover:text-cyan-700"
                                        title="Use at least 6 characters, combine letters and numbers, add a capital letter and a special character if possible, and avoid names, birthdays, member IDs, or simple sequences like 12345."
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
                                        <p class="mt-1">The member will use the email and password set here to
                                            log into the system.</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Review Step (add-mode only) --}}
                @if ($isAddMode)
                    <div x-show="formTab === 'review'" x-transition class="space-y-6">

                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-start gap-3">
                            <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <div class="text-sm text-emerald-800">
                                <p class="font-semibold">Review before creating</p>
                                <p class="mt-0.5">Please verify the information below. Click <strong>Previous</strong>
                                    to go back and make changes, or <strong>Create Member</strong> when everything looks
                                    correct.</p>
                            </div>
                        </div>

                        {{-- Personal --}}
                        <div>
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Personal Information
                            </h4>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">First Name</p>
                                    <p class="font-medium text-gray-900">{{ $form['first_name'] ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Middle Name</p>
                                    <p class="font-medium text-gray-900">{{ $form['mid_name'] ?? '' ?: '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Last Name</p>
                                    <p class="font-medium text-gray-900">{{ $form['last_name'] ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Suffix</p>
                                    <p class="font-medium text-gray-900">{{ $form['suffix'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Date of Birth</p>
                                    <p class="font-medium text-gray-900">{{ $form['birth_date'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Birthplace</p>
                                    <p class="font-medium text-gray-900">{{ $form['birthplace'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Gender</p>
                                    <p class="font-medium text-gray-900 capitalize">{{ $form['gender'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Civil Status</p>
                                    <p class="font-medium text-gray-900 capitalize">{{ $form['civil_status'] ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Religion</p>
                                    <p class="font-medium text-gray-900">{{ $form['religion'] ?? '' ?: '—' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Address --}}
                        <div>
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Address
                            </h4>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Street</p>
                                    <p class="font-medium text-gray-900">{{ $form['street'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Barangay</p>
                                    <p class="font-medium text-gray-900">{{ $form['barangay'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">City/Municipality</p>
                                    <p class="font-medium text-gray-900">{{ $form['city'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Province</p>
                                    <p class="font-medium text-gray-900">{{ $form['province'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Region</p>
                                    <p class="font-medium text-gray-900">{{ $form['region'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Postal Code</p>
                                    <p class="font-medium text-gray-900">{{ $form['postal_code'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Country</p>
                                    <p class="font-medium text-gray-900">{{ $form['country'] ?? '' ?: '—' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Contact --}}
                        <div>
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Contact Information
                            </h4>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Email</p>
                                    <p class="font-medium text-gray-900">{{ $form['email'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Phone Number</p>
                                    <p class="font-medium text-gray-900">{{ $form['phone_number'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Alternate Mobile</p>
                                    <p class="font-medium text-gray-900">{{ $form['mobile_number'] ?? '' ?: '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Emergency Contact</p>
                                    <p class="font-medium text-gray-900">
                                        {{ $form['emergency_contact'] ?? '' ?: '—' }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="text-gray-500 text-xs">Facebook</p>
                                    <p class="font-medium text-gray-900 break-all">
                                        {{ $form['facebook_link'] ?? '' ?: '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Government IDs --}}
                        <div>
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                                </svg>
                                Government IDs
                            </h4>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">National ID</p>
                                    <p class="font-medium text-gray-900">{{ $form['national_id'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">TIN</p>
                                    <p class="font-medium text-gray-900">{{ $form['tin'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">SSS</p>
                                    <p class="font-medium text-gray-900">{{ $form['sss'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">PhilHealth</p>
                                    <p class="font-medium text-gray-900">{{ $form['philhealth'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Barangay ID</p>
                                    <p class="font-medium text-gray-900">{{ $form['barangay_id'] ?? '' ?: '—' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Employment --}}
                        <div>
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0H8m8 0a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2" />
                                </svg>
                                Employment
                            </h4>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Type</p>
                                    <p class="font-medium text-gray-900 capitalize">
                                        {{ $form['employment_type'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Employer</p>
                                    <p class="font-medium text-gray-900">{{ $form['employer_name'] ?? '' ?: '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Position</p>
                                    <p class="font-medium text-gray-900">{{ $form['position'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Monthly Income</p>
                                    <p class="font-medium text-gray-900">
                                        {{ $form['monthly_income'] ?? null ? '₱' . number_format((float) $form['monthly_income'], 2) : '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Other Income Source</p>
                                    <p class="font-medium text-gray-900">
                                        {{ $form['other_income_source'] ?? '' ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Other Income Amount</p>
                                    <p class="font-medium text-gray-900">
                                        {{ $form['other_income_amount'] ?? null ? '₱' . number_format((float) $form['other_income_amount'], 2) : '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Login --}}
                        <div>
                            <h4
                                class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                Login Credentials
                            </h4>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 bg-white border border-gray-200 rounded-lg p-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Email (login)</p>
                                    <p class="font-medium text-gray-900">{{ $form['email'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Password</p>
                                    <p class="font-medium text-gray-900 tracking-wider">
                                        {{ str_repeat('•', min(strlen($form['password'] ?? ''), 12)) ?: '—' }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                @endif

            </div>
            {{-- end modal body --}}

            {{-- ──────────────────────────────────────────────────────── --}}
            {{-- Footer                                                   --}}
            {{-- ──────────────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50 rounded-b">

                {{-- Left: Previous + Next --}}
                <div class="flex gap-2">
                    {{-- Previous: always goes back without validation --}}
                    <button type="button" @click="prevBtn()" x-show="formTab !== 'personal'"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Previous
                    </button>
                </div>

                {{-- Right: Cancel + Save --}}
                <div class="flex gap-2">
                    {{-- Next: add-mode triggers server validation; edit-mode advances freely --}}
                    @php
                        $lastInputTab = $isAddMode ? 'review' : ($canManageMembers ? 'login' : 'employment');
                    @endphp
                    <button type="button" @click="nextBtn()"
                        x-show="formTab !== '{{ $lastInputTab }}' && formTab !== 'review'"
                        wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed"
                        wire:target="nextStep"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-linear-to-r from-cyan-500 to-emerald-500 rounded-lg hover:from-cyan-600 hover:to-emerald-600 transition-all disabled:opacity-50">
                        <svg wire:loading wire:target="nextStep"
                            class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span wire:loading.remove wire:target="nextStep">Next</span>
                        <span wire:loading wire:target="nextStep">Validating…</span>
                        <svg wire:loading.remove wire:target="nextStep" class="w-4 h-4 ml-1" fill="none"
                            stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    {{-- Edit mode: Update Member always visible --}}
                    @if ($isEdit)
                        <button type="button" wire:click="saveMember" wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-linear-to-r from-cyan-500 to-emerald-500 rounded-lg hover:from-cyan-600 hover:to-emerald-600 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg wire:loading wire:target="saveMember"
                                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span wire:loading.remove wire:target="saveMember">Update Member</span>
                            <span wire:loading wire:target="saveMember">Updating…</span>
                        </button>

                        {{-- Add mode: Create Member only on review step --}}
                    @else
                        <button type="button" wire:click="saveMember" x-show="formTab === 'review'"
                            wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed"
                            class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-linear-to-r from-emerald-500 to-cyan-500 rounded-lg hover:from-emerald-600 hover:to-cyan-600 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg wire:loading wire:target="saveMember"
                                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <svg wire:loading.remove wire:target="saveMember" class="w-4 h-4 mr-2" fill="none"
                                stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            <span wire:loading.remove wire:target="saveMember">Create Member</span>
                            <span wire:loading wire:target="saveMember">Creating…</span>
                        </button>
                    @endif
                </div>

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
        if (window.__memberFormEmailSyncBound) {
            return;
        }

        window.__memberFormEmailSyncBound = true;
        window.__memberPasswordStrengthUpdate = () => {
            const strengthLabel = document.getElementById('member-password-strength-label');
            const strengthBar = document.getElementById('member-password-strength-bar');

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
                window.__memberPasswordStrengthUpdate();
            }
        });

        document.addEventListener('keyup', (event) => {
            if (event.target && ['password', 'first_name', 'mid_name', 'last_name'].includes(event.target
                    .id)) {
                window.__memberPasswordStrengthUpdate();
            }
        });

        document.addEventListener('blur', (event) => {
            if (event.target && event.target.id === 'password') {
                window.__memberPasswordStrengthUpdate();
            }
        }, true);

        document.addEventListener('DOMContentLoaded', () => {
            window.__memberPasswordStrengthUpdate();
        });

        window.__memberPasswordStrengthUpdate();
    })();
</script>
