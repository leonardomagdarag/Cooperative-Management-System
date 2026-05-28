<div x-data="{ show: @entangle('showEditModal') }" x-show="show" x-cloak
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="profile-edit-modal-title" role="dialog"
    aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4 py-6 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="show = false; $wire.closeEditModal()"></div>

        <div
            class="relative z-10 inline-block w-full max-w-5xl transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:align-middle">
            <div class="border-b border-gray-200 bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80">Profile</p>
                        <h3 class="mt-1 text-2xl font-bold" id="profile-edit-modal-title">
                            Edit your personal information
                        </h3>
                        <p class="mt-2 text-sm text-white/90">
                            Update the contact and identity details tied to your cooperative account.
                        </p>
                    </div>

                    <button type="button" @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"
                        class="rounded-full p-2 text-white/80 transition-colors hover:bg-white/10 hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <form wire:submit.prevent="saveProfile">
                <div class="max-h-[75vh] overflow-y-auto px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-5">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900">Profile details</h4>
                                <p class="mt-1 text-sm text-gray-500">Keep the records used by the cooperative up to date.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach (['title' => 'Title', 'first_name' => 'First Name', 'mid_name' => 'Middle Name', 'last_name' => 'Last Name', 'suffix' => 'Suffix', 'birth_date' => 'Birth Date'] as $field => $label)
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</span>
                                        <input type="{{ $field === 'birth_date' ? 'date' : 'text' }}"
                                            wire:model.blur="profileForm.{{ $field }}"
                                            class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        @error('profileForm.' . $field)
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </label>
                                @endforeach
                            </div>

                            {{-- Profile Photo Upload --}}
                            <div x-data="{ preview: null }">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-full bg-gray-100 border border-gray-300 overflow-hidden flex items-center justify-center shrink-0">
                                        @php $currentPhoto = Auth::user()?->personalData?->profile_picture; @endphp
                                        @if ($currentPhoto)
                                            <img src="{{ Storage::url($currentPhoto) }}" class="w-full h-full object-cover" alt="Current photo" x-show="!preview">
                                        @else
                                            <span x-show="!preview" class="text-gray-400"><i class="fas fa-user text-2xl"></i></span>
                                        @endif
                                        <img x-show="preview" :src="preview" class="w-full h-full object-cover" alt="Preview" style="display:none">
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" wire:model="profilePhotoUpload" accept="image/*"
                                            @change="const f=$event.target.files[0]; if(f){const r=new FileReader();r.onload=e=>preview=e.target.result;r.readAsDataURL(f)}"
                                            class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                                        <p class="mt-1 text-xs text-gray-400">JPG, PNG or GIF · max 2MB</p>
                                        @error('profilePhotoUpload') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach (['birthplace' => 'Birthplace', 'gender' => 'Gender', 'education' => 'Education', 'civil_status' => 'Civil Status', 'religion' => 'Religion', 'email' => 'Login Email', 'phone_no' => 'Phone Number', 'facebook' => 'Facebook'] as $field => $label)
                                    <label class="block {{ in_array($field, ['email', 'facebook'], true) ? 'sm:col-span-2' : '' }}">
                                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</span>
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
                                <h4 class="text-lg font-semibold text-gray-900">Address details</h4>
                                <p class="mt-1 text-sm text-gray-500">Use the address the cooperative should keep on file.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach (['country' => 'Country', 'region' => 'Region', 'province' => 'Province', 'city_municipality' => 'City / Municipality', 'barangay' => 'Barangay', 'street' => 'Street', 'zip_code' => 'ZIP Code'] as $field => $label)
                                    <label class="block {{ $field === 'street' ? 'sm:col-span-2' : '' }}">
                                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</span>
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
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-6 py-4">
                    <p class="text-xs text-gray-500">Keep this current so the cooperative can process notices and requests faster.</p>

                    <div class="flex items-center gap-3">
                        <button type="button" @click="show = false; setTimeout(() => $wire.closeEditModal(), 150)"
                            class="inline-flex items-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                            <span wire:loading.remove wire:target="saveProfile">Save Profile Changes</span>
                            <span wire:loading wire:target="saveProfile">Saving...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>