@props(['officer' => null, 'isEdit' => false, 'tabErrors' => []])

<div x-data="{ show: false }" x-init="$nextTick(() => show = true)" x-show="show" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black opacity-25"
        @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-4xl max-h-full mx-auto my-6 z-10"
        x-transition:enter="transition ease-out duration-200 delay-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-white rounded-lg shadow-xl max-h-[90vh] flex flex-col">
            {{-- Modal header --}}
            <div
                class="flex items-start justify-between p-6 border-b border-gray-200 rounded-t bg-gradient-to-r from-cyan-500 to-emerald-500">
                <div>
                    <h3 class="text-2xl font-bold text-white">
                        {{ $isEdit ? 'Edit Officer' : 'Add New Officer' }}
                    </h3>
                    <p class="text-sm text-cyan-50 mt-1">
                        {{ $isEdit ? 'Update officer information' : 'Create a new officer account' }}
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

            {{-- Modal body --}}
            <div class="p-6 overflow-y-auto flex-1">
                <form wire:submit.prevent="saveOfficer">
                    {{-- Personal Information --}}
                    <div class="mb-8">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Personal Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-forms.text-input name="first_name" label="First Name" model="form.first_name"
                                :required="true" />
                            <x-forms.text-input name="mid_name" label="Middle Name" model="form.mid_name" />
                            <x-forms.text-input name="last_name" label="Last Name" model="form.last_name"
                                :required="true" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <x-forms.text-input name="birth_date" label="Date of Birth" model="form.birth_date"
                                type="date" :required="true" />
                            <x-forms.select-input name="gender" label="Gender" model="form.gender" :required="true"
                                :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />
                            <x-forms.select-input name="civil_status" label="Civil Status" model="form.civil_status"
                                :required="true" :options="[
                                    'single' => 'Single',
                                    'married' => 'Married',
                                    'widowed' => 'Widowed',
                                    'separated' => 'Separated',
                                ]" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <x-forms.select-input name="title" label="Officer Title" model="form.title"
                                :required="true" :options="[
                                    'Chairman' => 'Chairman',
                                    'Vice Chairman' => 'Vice Chairman',
                                    'Secretary' => 'Secretary',
                                    'Treasurer' => 'Treasurer',
                                    'Loan Officer' => 'Loan Officer',
                                    'Member' => 'Member',
                                ]" />
                        </div>
                    </div>

                    {{-- Contact Information --}}
                    <div class="mb-8">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Contact Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-forms.text-input name="email" label="Email Address" model="form.email" type="email"
                                :required="true" />
                            <x-forms.text-input name="phone_number" label="Phone Number" model="form.phone_number" />
                        </div>
                    </div>

                    {{-- Account & Role --}}
                    <div class="mb-8">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Account Settings</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-forms.select-input name="role" label="Role" model="form.role" :required="true"
                                :options="[
                                    'admin' => 'Administrator',
                                    'manager' => 'Manager',
                                    'secretary' => 'Secretary',
                                    'treasurer' => 'Treasurer',
                                    'loan' => 'Loan Officer',
                                ]" />
                            <x-forms.text-input name="password" label="Password" model="form.password" type="password"
                                :required="!$isEdit"
                                helperText="{{ $isEdit ? 'Leave blank to keep current' : 'Min. 6 characters' }}" />
                            <x-forms.text-input name="password_confirmation" label="Confirm Password"
                                model="form.password_confirmation" type="password" :required="!$isEdit" />
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end space-x-2 pt-6 border-t border-gray-200">
                        <button type="button" @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"
                            class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10">
                            Cancel
                        </button>
                        <button type="submit" @if (!$this->isFormValid) disabled @endif
                            wire:loading.attr="disabled"
                            class="text-white bg-gradient-to-r from-cyan-500 to-blue-500 hover:bg-gradient-to-bl focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveOfficer">
                                {{ $isEdit ? 'Update Officer' : 'Create Officer' }}
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
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
