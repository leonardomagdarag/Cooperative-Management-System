<div class="max-w-7xl mx-auto p-6">
    {{-- Header Section --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Announcements</h1>
            <p class="text-sm text-gray-600 mt-1">Stay updated with the latest news and announcements</p>
        </div>

        @if ($canCreateAnnouncements)
            <button wire:click="openModal"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg shadow-md transition duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Announcement
            </button>
        @endif
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd" />
                </svg>
                <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd" />
                </svg>
                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    {{-- Announcements List --}}
    <div class="space-y-4">
        @forelse($announcements as $announcement)
            <div wire:key="announcement-{{ $announcement->id }}"
                wire:click="viewAnnouncement({{ $announcement->id }})"
                class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-xl font-bold text-gray-900">{{ $announcement->title }}</h3>
                                {{-- Type badge --}}
                                @if ($announcement->type === 'news')
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                        <i class="fas fa-newspaper text-[10px]"></i> News
                                    </span>
                                @elseif ($announcement->type === 'event')
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                        <i class="fas fa-calendar-alt text-[10px]"></i> Event
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                        <i class="fas fa-bullhorn text-[10px]"></i> Announcement
                                    </span>
                                @endif
                                {{-- Visibility badge --}}
                                @php
                                    $vis = (array) ($announcement->visibility ?? []);
                                    $isEveryone = in_array('everyone', $vis);
                                    $isPublic = in_array('public', $vis);
                                    $isMembers = in_array('members', $vis);
                                    $isOfficers = in_array('officers', $vis);
                                @endphp
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $isEveryone ? 'bg-emerald-100 text-emerald-700' : ($isPublic ? 'bg-blue-100 text-blue-700' : ($isOfficers && !$isMembers ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700')) }}">
                                    <i
                                        class="fas {{ $isEveryone ? 'fa-globe' : ($isPublic ? 'fa-earth-asia' : ($isOfficers && !$isMembers ? 'fa-user-tie' : 'fa-users')) }} text-[10px]"></i>
                                    {{ $announcement->visibility_label }}
                                </span>
                                {{-- Category badge --}}
                                @if ($announcement->category)
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                        {{ $announcement->category }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center text-sm text-gray-500 space-x-4">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>{{ $announcement->creator ? $announcement->creator->personalData->first_name . ' ' . $announcement->creator->personalData->last_name : 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ $announcement->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        @if ($canUpdateAnnouncements || $canDeleteAnnouncements)
                            <div class="flex gap-2 ml-4">
                                @if ($canUpdateAnnouncements)
                                    <button wire:click.stop="edit({{ $announcement->id }})"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition duration-200"
                                        title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                @endif
                                @if ($canDeleteAnnouncements)
                                    <button wire:click.stop="delete({{ $announcement->id }})"
                                        wire:confirm="Are you sure you want to delete this announcement?"
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition duration-200"
                                        title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No Announcements Yet</h3>
                <p class="text-gray-600">There are currently no announcements to display.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $announcements->links() }}
    </div>

    {{-- View Modal --}}
    @if ($showViewModal && $selectedAnnouncement)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="announcement-view-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity" wire:click="closeViewModal"></div>

                <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    @if ($selectedAnnouncement['image_url'])
                        <div class="h-64 w-full overflow-hidden bg-gray-100">
                            <img src="{{ $selectedAnnouncement['image_url'] }}"
                                alt="{{ $selectedAnnouncement['title'] }}"
                                class="h-full w-full object-cover">
                        </div>
                    @endif

                    <div class="px-6 py-5 sm:px-8 sm:py-6">
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                        {{ $selectedAnnouncement['type_label'] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                        {{ $selectedAnnouncement['visibility_label'] }}
                                    </span>
                                    @if ($selectedAnnouncement['category'])
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">
                                            {{ $selectedAnnouncement['category'] }}
                                        </span>
                                    @endif
                                </div>
                                <h2 id="announcement-view-title" class="text-2xl font-bold text-gray-900 sm:text-3xl">
                                    {{ $selectedAnnouncement['title'] }}
                                </h2>
                                <p class="mt-2 text-sm text-gray-500">
                                    Posted {{ $selectedAnnouncement['created_at_label'] }}
                                    @if ($selectedAnnouncement['creator_name'])
                                        by {{ $selectedAnnouncement['creator_name'] }}
                                    @endif
                                </p>
                            </div>

                            <button type="button" wire:click="closeViewModal" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        @if ($selectedAnnouncement['type'] === 'event' && ($selectedAnnouncement['event_date_label'] || $selectedAnnouncement['event_time'] || $selectedAnnouncement['event_location']))
                            <div class="mb-5 grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 sm:grid-cols-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Date</p>
                                    <p class="mt-1">{{ $selectedAnnouncement['event_date_label'] ?: 'TBA' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Time</p>
                                    <p class="mt-1">{{ $selectedAnnouncement['event_time'] ?: 'TBA' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Location</p>
                                    <p class="mt-1">{{ $selectedAnnouncement['event_location'] ?: 'TBA' }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="prose prose-sm max-w-none text-gray-700">
                            {!! nl2br(e($selectedAnnouncement['content'])) !!}
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 sm:px-8">
                        <button type="button" wire:click="closeViewModal"
                            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 font-semibold text-gray-700 transition duration-200 hover:bg-gray-50">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Create/Edit Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity" wire:click="closeModal"></div>

                {{-- Modal panel --}}
                <div
                    class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form wire:submit.prevent="save">
                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-2xl font-bold text-gray-900" id="modal-title">
                                    {{ $editingId ? 'Edit Announcement' : 'Create New Announcement' }}
                                </h3>
                                <button type="button" wire:click="closeModal"
                                    class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <x-forms.text-input name="title" label="Title" model="title" :required="true"
                                    maxlength="255" placeholder="Enter title" :validateOnBlur="false" />

                                {{-- Two-column layout: [Type + Category] | [Visibility] --}}
                                <div class="grid grid-cols-2 gap-4 items-start">
                                    {{-- Left column: Type then Category --}}
                                    <div class="space-y-4">
                                        <x-forms.select-input name="type" label="Type" model="type"
                                            :required="true" :live="true" :options="[
                                                'announcement' => 'Announcement',
                                                'news' => 'News',
                                                'event' => 'Event',
                                            ]" :validateOnBlur="false" />

                                        <x-forms.text-input name="category" label="Category" model="category"
                                            maxlength="100" placeholder="e.g. Achievement, Workshop, Policy…"
                                            :validateOnBlur="false" />
                                    </div>

                                    {{-- Right column: Visibility checkboxes --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Visible to <span class="text-red-500">*</span>
                                        </label>
                                        @error('visibility')
                                            <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <div class="flex flex-wrap gap-2">
                                            {{-- Everyone --}}
                                            <label
                                                class="flex items-center gap-2 cursor-pointer select-none px-3 py-2 rounded-lg border transition-colors
                                                {{ $visEveryone ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400' }}">
                                                <input type="checkbox" wire:model.live="visEveryone"
                                                    class="accent-emerald-600 w-4 h-4">
                                                <i class="fas fa-globe text-xs"></i>
                                                <span class="text-sm font-medium">Everyone</span>
                                            </label>
                                            {{-- Members --}}
                                            <label
                                                class="flex items-center gap-2 px-3 py-2 rounded-lg border transition-colors
                                                {{ $visEveryone ? 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' : ($visMembers ? 'border-emerald-500 bg-emerald-50 text-emerald-700 cursor-pointer' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 cursor-pointer') }}">
                                                <input type="checkbox" wire:model="visMembers"
                                                    {{ $visEveryone ? 'disabled' : '' }}
                                                    class="accent-emerald-600 w-4 h-4 {{ $visEveryone ? 'opacity-40' : '' }}">
                                                <i class="fas fa-users text-xs"></i>
                                                <span class="text-sm font-medium">Members</span>
                                            </label>
                                            {{-- Officers --}}
                                            <label
                                                class="flex items-center gap-2 px-3 py-2 rounded-lg border transition-colors
                                                {{ $visEveryone ? 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' : ($visOfficers ? 'border-emerald-500 bg-emerald-50 text-emerald-700 cursor-pointer' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 cursor-pointer') }}">
                                                <input type="checkbox" wire:model="visOfficers"
                                                    {{ $visEveryone ? 'disabled' : '' }}
                                                    class="accent-emerald-600 w-4 h-4 {{ $visEveryone ? 'opacity-40' : '' }}">
                                                <i class="fas fa-user-tie text-xs"></i>
                                                <span class="text-sm font-medium">Officers</span>
                                            </label>
                                            {{-- Public --}}
                                            <label
                                                class="flex items-center gap-2 px-3 py-2 rounded-lg border transition-colors
                                                {{ $visEveryone ? 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' : ($visPublic ? 'border-emerald-500 bg-emerald-50 text-emerald-700 cursor-pointer' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 cursor-pointer') }}">
                                                <input type="checkbox" wire:model="visPublic"
                                                    {{ $visEveryone ? 'disabled' : '' }}
                                                    class="accent-emerald-600 w-4 h-4 {{ $visEveryone ? 'opacity-40' : '' }}">
                                                <i class="fas fa-earth-asia text-xs"></i>
                                                <span class="text-sm font-medium">Public</span>
                                            </label>
                                        </div>
                                        <p class="mt-1.5 text-xs text-gray-500">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            "Everyone" includes members, officers, and the public website. News &amp;
                                            events should be visible to at least <em>Public</em> or <em>Everyone</em> to
                                            appear on public pages.
                                        </p>
                                    </div>
                                </div>

                                @if ($type === 'event')
                                    <div
                                        class="grid grid-cols-2 gap-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                                        <div
                                            class="col-span-2 flex items-center gap-2 text-xs font-semibold text-emerald-700 uppercase tracking-wide mb-1">
                                            <i class="fas fa-calendar-alt"></i> Event Details
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Date <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" wire:model="event_date"
                                                class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500" />
                                            @error('event_date')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                                            <input type="text" wire:model="event_time" placeholder="e.g. 9:00 AM"
                                                class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label
                                                class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                            <input type="text" wire:model="event_location"
                                                placeholder="e.g. Main Office, Conference Hall"
                                                class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500" />
                                        </div>
                                    </div>
                                @endif

                                <x-forms.textarea-input name="content" label="Content" model="content"
                                    :rows="6" :required="true" maxlength="5000" placeholder="Enter content"
                                    :validateOnBlur="false" />

                                {{-- Image upload --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Cover Image <span class="text-gray-400 font-normal">(optional, max 2 MB)</span>
                                    </label>

                                    @if ($existingImage && !$image)
                                        <div class="mb-2 flex items-center gap-3">
                                            <img src="{{ asset('storage/' . $existingImage) }}" alt="Current image"
                                                class="h-20 w-32 object-cover rounded-lg border border-gray-200">
                                            <span class="text-xs text-gray-500">Current image — upload a new one to replace it</span>
                                        </div>
                                    @endif

                                    @if ($image)
                                        <div class="mb-2">
                                            <img src="{{ $image->temporaryUrl() }}" alt="Preview"
                                                class="h-20 w-32 object-cover rounded-lg border border-gray-200">
                                        </div>
                                    @endif

                                    <input type="file" wire:model="image" accept="image/*"
                                        class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition">
                                    @error('image')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-4 flex gap-3 justify-end">
                            <button type="button" wire:click="closeModal"
                                class="px-6 py-2.5 bg-white border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition duration-200">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition duration-200">
                                {{ $editingId ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
