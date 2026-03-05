@props(['isEdit' => false, 'tabErrors' => []])

<div x-data="{ show: false, formTab: 'personal' }" x-init="$nextTick(() => show = true)" x-show="show" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black opacity-25" @click="show = false; $wire.closeEditModal()"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-5xl max-h-full mx-auto my-6 z-10"
        x-transition:enter="transition ease-out duration-200 delay-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-white rounded-lg shadow-xl max-h-[90vh] flex flex-col">
            {{-- Modal header with gradient --}}
            <div
                class="flex items-start justify-between p-6 border-b border-gray-200 rounded-t bg-gradient-to-r from-cyan-500 to-emerald-500">
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

            {{-- Tab Navigation --}}
            <div class="flex border-b border-gray-200 bg-gray-50 px-6 overflow-x-auto">
                <button @click="formTab = 'personal'"
                    :class="formTab === 'personal' ? 'border-cyan-500 text-cyan-600' :
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                    Personal Info
                    @if ($tabErrors['personal'] || $this->tabRequiredErrors['personal'])
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                    @endif
                </button>
                <button @click="formTab = 'address'"
                    :class="formTab === 'address' ? 'border-cyan-500 text-cyan-600' :
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                    Address
                    @if ($tabErrors['address'] || $this->tabRequiredErrors['address'])
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                    @endif
                </button>
                <button @click="formTab = 'contact'"
                    :class="formTab === 'contact' ? 'border-cyan-500 text-cyan-600' :
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                    Contact Info
                    @if ($tabErrors['contact'] || $this->tabRequiredErrors['contact'])
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                    @endif
                </button>
                <button @click="formTab = 'ids'"
                    :class="formTab === 'ids' ? 'border-cyan-500 text-cyan-600' :
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                    Government IDs
                    @if ($tabErrors['ids'] || $this->tabRequiredErrors['ids'])
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                    @endif
                </button>
                <button @click="formTab = 'employment'"
                    :class="formTab === 'employment' ? 'border-cyan-500 text-cyan-600' :
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                    Employment
                    @if ($tabErrors['employment'] || $this->tabRequiredErrors['employment'])
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                    @endif
                </button>
                @if (!$isEdit || in_array(Auth::user()->role, ['admin', 'manager']))
                    <button @click="formTab = 'login'"
                        :class="formTab === 'login' ? 'border-cyan-500 text-cyan-600' :
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-6 border-b-2 font-medium text-sm whitespace-nowrap transition-colors relative">
                        Login Info
                        @if ($tabErrors['login'] || $this->tabRequiredErrors['login'])
                            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                        @endif
                    </button>
                @endif
            </div>

            {{-- Modal body with tabs --}}
            <div class="p-6 overflow-y-auto flex-1">
                <div>

                    {{-- Tab 1: Personal Information --}}
                    <div x-show="formTab === 'personal'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-forms.text-input name="first_name" label="First Name" model="form.first_name"
                                :required="true" placeholder="Enter first name"
                                helperText="Legal first name as it appears on ID" />
                            <x-forms.text-input name="mid_name" label="Middle Name" model="form.mid_name"
                                placeholder="Enter middle name" />
                            <x-forms.text-input name="last_name" label="Last Name" model="form.last_name"
                                :required="true" placeholder="Enter last name" />
                            <x-forms.text-input name="suffix" label="Suffix" model="form.suffix"
                                placeholder="Jr., Sr., III, etc." maxlength="50" />
                            <x-forms.text-input name="birth_date" label="Date of Birth" model="form.birth_date"
                                :required="true" type="date" helperText="Must be 18 years or older" />
                            <x-forms.text-input name="birthplace" label="Place of Birth" model="form.birthplace"
                                placeholder="City, Province" />
                            <x-forms.select-input name="gender" label="Gender" model="form.gender" :required="true"
                                :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />
                            <x-forms.select-input name="civil_status" label="Civil Status" model="form.civil_status"
                                :required="true" :options="[
                                    'single' => 'Single',
                                    'married' => 'Married',
                                    'widowed' => 'Widowed',
                                    'separated' => 'Separated',
                                ]" />
                            <x-forms.text-input name="religion" label="Religion" model="form.religion"
                                placeholder="Enter religion" />
                        </div>
                    </div>

                    {{-- Tab 2: Address --}}
                    <div x-show="formTab === 'address'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-forms.text-input name="street" label="Street Address" model="form.street"
                                placeholder="House No., Street Name" />
                            <x-forms.text-input name="barangay" label="Barangay" model="form.barangay"
                                placeholder="Enter barangay" />
                            <x-forms.text-input name="city" label="City/Municipality" model="form.city"
                                placeholder="Enter city or municipality" />
                            <x-forms.text-input name="province" label="Province" model="form.province"
                                placeholder="Enter province" />
                            <x-forms.text-input name="region" label="Region" model="form.region"
                                placeholder="e.g., Region IV-A" />
                            <x-forms.text-input name="postal_code" label="Postal/ZIP Code" model="form.postal_code"
                                placeholder="Enter postal code" maxlength="20" />
                            <x-forms.text-input name="country" label="Country" model="form.country"
                                placeholder="Philippines" />
                        </div>
                    </div>

                    {{-- Tab 3: Contact Information --}}
                    <div x-show="formTab === 'contact'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-forms.text-input name="email" label="Email Address" model="form.email"
                                type="email" :required="true" placeholder="email@example.com"
                                helperText="Primary contact email" />
                            <x-forms.text-input name="phone_number" label="Phone Number" model="form.phone_number"
                                :required="true" type="tel" placeholder="09XX XXX XXXX" maxlength="11"
                                helperText="11-digit mobile number" />
                            <x-forms.text-input name="mobile_number" label="Alternate Mobile"
                                model="form.mobile_number" type="tel" placeholder="09XX XXX XXXX"
                                maxlength="11" />
                            <x-forms.text-input name="emergency_contact" label="Emergency Contact"
                                model="form.emergency_contact" placeholder="Name and number" />
                            <div class="md:col-span-2">
                                <x-forms.text-input name="facebook_link" label="Facebook Profile"
                                    model="form.facebook_link" type="url"
                                    placeholder="https://facebook.com/username"
                                    helperText="Optional: Your Facebook profile URL" />
                            </div>
                        </div>
                    </div>

                    {{-- Tab 4: Government IDs --}}
                    <div x-show="formTab === 'ids'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-forms.text-input name="national_id" label="National ID (PhilSys)"
                                model="form.national_id" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="64" />
                            <x-forms.text-input name="tin" label="TIN (Tax Identification Number)"
                                model="form.tin" placeholder="XXX-XXX-XXX-XXX" maxlength="64" />
                            <x-forms.text-input name="sss" label="SSS Number" model="form.sss"
                                placeholder="XX-XXXXXXX-X" maxlength="64" />
                            <x-forms.text-input name="philhealth" label="PhilHealth Number" model="form.philhealth"
                                placeholder="XX-XXXXXXXXX-X" maxlength="64" />
                            <x-forms.text-input name="barangay_id" label="Barangay ID" model="form.barangay_id"
                                placeholder="Enter barangay ID" maxlength="64" />
                        </div>
                    </div>

                    {{-- Tab 5: Employment --}}
                    <div x-show="formTab === 'employment'" x-transition class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                ]" />
                            <x-forms.text-input name="employer_name" label="Company/Employer Name"
                                model="form.employer_name" placeholder="Enter company name" />
                            <x-forms.text-input name="position" label="Position/Job Title" model="form.position"
                                placeholder="Enter position" />
                            <x-forms.text-input name="monthly_income" label="Monthly Income"
                                model="form.monthly_income" type="number" placeholder="0.00"
                                helperText="Enter amount in PHP" />
                            <x-forms.text-input name="other_income_source" label="Other Income Source"
                                model="form.other_income_source" placeholder="e.g., Business, Investments" />
                            <x-forms.text-input name="other_income_amount" label="Other Income Amount"
                                model="form.other_income_amount" type="number" placeholder="0.00" />
                        </div>
                    </div>

                    {{-- Tab 6: Login Information (Only for new members or admin/manager editing) --}}
                    @if (!$isEdit || in_array(Auth::user()->role, ['admin', 'manager']))
                        <div x-show="formTab === 'login'" x-transition class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if ($isEdit && !in_array(Auth::user()->role, ['admin', 'manager']))
                                    <div class="md:col-span-2">
                                        <x-forms.text-input name="current_password" label="Current Password"
                                            model="form.current_password" type="password" :required="!empty($form['password'])"
                                            placeholder="Enter current password"
                                            helperText="Required to change password" />
                                    </div>
                                @endif
                                <x-forms.text-input name="password" label="Password" model="form.password"
                                    type="password" :required="!$isEdit" placeholder="Enter password"
                                    helperText="Minimum 6 characters" />
                                <x-forms.text-input name="password_confirmation" label="Confirm Password"
                                    model="form.password_confirmation" type="password" :required="!$isEdit"
                                    placeholder="Re-enter password" />
                            </div>

                            @if (!$isEdit)
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

                </div>
            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50 rounded-b">
                <div class="flex gap-2">
                    <button type="button"
                        @click="if(formTab !== 'personal') formTab = ['personal', 'address', 'contact', 'ids', 'employment', 'login'][['address', 'contact', 'ids', 'employment', 'login'].indexOf(formTab)]"
                        x-show="formTab !== 'personal'"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Previous
                    </button>
                    <button type="button"
                        @click="if(formTab === 'personal') formTab = 'address'; else if(formTab === 'address') formTab = 'contact'; else if(formTab === 'contact') formTab = 'ids'; else if(formTab === 'ids') formTab = 'employment'; else if(formTab === 'employment') formTab = 'login';"
                        x-show="formTab !== 'login' && formTab !== 'employment' || (formTab === 'employment' && (!{{ $isEdit ? 'true' : 'false' }} || {{ in_array(Auth::user()->role, ['admin', 'manager']) ? 'true' : 'false' }}))"
                        class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-lg hover:from-cyan-600 hover:to-emerald-600 transition-all">
                        Next
                    </button>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"
                        wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="closeEditModal, saveMember">Cancel</span>
                        <span wire:loading wire:target="closeEditModal">Closing...</span>
                        <span wire:loading wire:target="saveMember">Please wait...</span>
                    </button>
                    <button type="button" wire:click="saveMember" {{ !$this->isFormValid ? 'disabled' : '' }}
                        wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed"
                        class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-lg hover:from-cyan-600 hover:to-emerald-600 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg wire:loading wire:target="saveMember" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span wire:loading.remove
                            wire:target="saveMember">{{ $isEdit ? 'Update Member' : 'Create Member' }}</span>
                        <span wire:loading
                            wire:target="saveMember">{{ $isEdit ? 'Updating...' : 'Creating...' }}</span>
