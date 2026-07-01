{{-- Single root element required by Livewire --}}
<div>
    @php
        $canViewMemberId = Auth::user()->can('members.view-full-details');
    @endphp
    {{-- Auto-refresh occasionally; paused while modals are open --}}
    <div x-data="{ showModal: @entangle('showModal'), showEditModal: @entangle('showEditModal'), showExportModal: @entangle('showExportModal') }" x-show="!showModal && !showEditModal && !showExportModal" wire:poll.visible.60s aria-hidden="true"
        class="w-px h-px opacity-0 pointer-events-none"></div>

    <div>
        <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
            <div class="w-full mb-1">
                <div class="mb-4">
                    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Members</h1>
                    <p class="text-sm text-gray-500">Manage member profiles and information</p>
                </div>

                <div class="sm:flex">
                    <div class="items-center hidden mb-3 sm:flex sm:divide-x sm:divide-gray-100 sm:mb-0">
                        <form class="lg:pr-3">
                            <label for="members-search" class="sr-only">Search</label>
                            <div class="relative mt-1 lg:w-64 xl:w-96">
                                <input type="text" wire:model.live.debounce.300ms="search" id="members-search"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="Search members...">
                            </div>
                        </form>
                    </div>
                    <div class="flex items-center ml-auto space-x-2 sm:space-x-3">
                        <button type="button" wire:click="$refresh"
                            class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300"
                            title="Refresh data">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                </path>
                            </svg>
                        </button>
                        {{-- Only officers with members.create permission can add members --}}
                        @if ($canCreate)
                            <button type="button" wire:click="addMember" wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg wire:loading.remove wire:target="addMember" class="w-5 h-5 mr-2 -ml-1"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z">
                                    </path>
                                </svg>
                                <svg wire:loading wire:target="addMember"
                                    class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span wire:loading.remove wire:target="addMember">Add Member</span>
                                <span wire:loading wire:target="addMember">Loading...</span>
                            </button>
                        @endif
                        @if ($canExport)
                            <button type="button" wire:click="openExportModal" wire:loading.attr="disabled"
                                wire:target="openExportModal,exportMembers"
                                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M8.707 7.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l2-2a1 1 0 00-1.414-1.414L11 7.586V3a1 1 0 10-2 0v4.586l-.293-.293z">
                                    </path>
                                    <path
                                        d="M3 5a2 2 0 012-2h1a1 1 0 010 2H5v7h2l1 2h4l1-2h2V5h-1a1 1 0 110-2h1a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5z">
                                    </path>
                                </svg>
                                Export
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden shadow">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th scope="col" class="p-4">
                                        <div class="flex items-center">
                                            <input wire:model="selectAll" type="checkbox"
                                                class="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300">
                                            <label class="sr-only">checkbox</label>
                                        </div>
                                    </th>
                                    @if ($canViewMemberId)
                                        <th scope="col" wire:click.debounce.250ms="sortBy('user_number')"
                                            wire:loading.class="opacity-60 cursor-not-allowed" wire:target="sortBy"
                                            class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                            Member ID
                                            @if ($sortField === 'user_number')
                                                <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                            @endif
                                        </th>
                                    @endif
                                    <th scope="col" wire:click.debounce.250ms="sortBy('first_name')"
                                        wire:loading.class="opacity-60 cursor-not-allowed" wire:target="sortBy"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                        Name
                                        @if ($sortField === 'first_name')
                                            <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                        @endif
                                    </th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                        Status
                                    </th>
                                    <th scope="col" wire:click.debounce.250ms="sortBy('created_at')"
                                        wire:loading.class="opacity-60 cursor-not-allowed" wire:target="sortBy"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                        Join Date
                                        @if ($sortField === 'created_at')
                                            <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                        @endif
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($members as $member)
                                    <tr wire:key="member-{{ $member->id }}" class="hover:bg-gray-100 cursor-pointer"
                                        wire:click="viewProfile('{{ $member->id }}')">
                                        <td class="w-4 p-4" onclick="event.stopPropagation()">
                                            <div class="flex items-center">
                                                <input wire:model="selectedRows" value="{{ $member->id }}"
                                                    type="checkbox"
                                                    class="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300">
                                                <label class="sr-only">checkbox</label>
                                            </div>
                                        </td>
                                        @if ($canViewMemberId)
                                            <td class="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
                                                <div class="text-base font-semibold text-gray-900">
                                                    {{ $member->user_number }}
                                                </div>
                                            </td>
                                        @endif
                                        <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                            {{ $member->personalData->first_name ?? '' }}
                                            {{ $member->personalData->last_name ?? '' }}
                                        </td>
                                        <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                            @if (($member->account_status ?? 'active') === 'active')
                                                <span
                                                    class="bg-green-100 text-green-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Active</span>
                                            @else
                                                <span
                                                    class="bg-red-100 text-red-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                            {{ $member->created_at->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canViewMemberId ? '5' : '4' }}" class="p-4 text-center">
                                            <div class="flex flex-col items-center justify-center py-12">
                                                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                                    </path>
                                                </svg>
                                                <p class="text-sm text-gray-500">No members found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pagination --}}
        <div
            class="sticky bottom-0 right-0 items-center w-full p-4 bg-white border-t border-gray-200 sm:flex sm:justify-between">
            <div class="flex items-center mb-4 sm:mb-0">
                <span class="text-sm font-normal text-gray-500">Showing <span
                        class="font-semibold text-gray-900">{{ $members->firstItem() ?? 0 }}-{{ $members->lastItem() ?? 0 }}</span>
                    of <span class="font-semibold text-gray-900">{{ $members->total() }}</span></span>
            </div>
            <div class="flex items-center space-x-3">
                <button wire:click="previousPage" @if (!$members->onFirstPage()) @else disabled @endif
                    class="inline-flex items-center justify-center flex-1 px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-5 h-5 mr-1 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                            clip-rule="evenodd"></path>
                    </svg>
                    Previous
                </button>
                <span class="text-sm font-normal text-gray-500">Page <span
                        class="font-semibold text-gray-900">{{ $members->currentPage() }}</span> of <span
                        class="font-semibold text-gray-900">{{ $members->lastPage() }}</span></span>
                <button wire:click="nextPage" @if (!$members->hasMorePages()) @else @endif
                    @if (!$members->hasMorePages()) disabled @endif
                    class="inline-flex items-center justify-center flex-1 px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                    Next
                    <svg class="w-5 h-5 ml-1 -mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Profile Modal --}}
        @if ($showModal && $selectedMember)
            <x-modals.member-profile wire:key="profile-modal-{{ $profileModalKey }}" :member="$selectedMember"
                :canEdit="$canEdit" :activeTab="$activeTab" />
        @endif

        {{-- Edit/Add Member Modal --}}
        @if ($showEditModal)
            <x-modals.member-form wire:key="member-form-{{ $modalKey }}" :isEdit="$isEdit" :tabErrors="$tabErrors"
                :form="$form" />
        @endif

        {{-- Export Modal --}}
        @if ($showExportModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="members-export-modal-title" role="dialog"
                aria-modal="true">
                <button type="button" class="fixed inset-0 bg-gray-500/75 transition-opacity"
                    wire:click="closeExportModal" aria-label="Close export modal"></button>

                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl">
                        <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900" id="members-export-modal-title">Export Members</h2>
                                <p class="mt-1 text-sm text-gray-500">Choose the file type and joined date range to export.</p>
                            </div>
                            <button type="button" class="text-gray-400 hover:text-gray-600"
                                wire:click="closeExportModal">
                                <span class="sr-only">Close</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="exportMembers" class="space-y-5 px-6 py-5">
                            <div>
                                <label for="members-export-type" class="mb-2 block text-sm font-medium text-gray-700">File type</label>
                                <select id="members-export-type" wire:model="exportType"
                                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                </select>
                                @error('exportType')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="members-export-start" class="mb-2 block text-sm font-medium text-gray-700">Start date</label>
                                    <input id="members-export-start" type="date" wire:model="exportStartDate"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500">
                                    @error('exportStartDate')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="members-export-end" class="mb-2 block text-sm font-medium text-gray-700">End date</label>
                                    <input id="members-export-end" type="date" wire:model="exportEndDate"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500">
                                    @error('exportEndDate')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                                Only members whose join date falls within the selected range will be included. Current search filters also apply.
                            </div>

                            <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
                                <button type="button"
                                    class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    wire:click="closeExportModal">
                                    Cancel
                                </button>
                                <button type="submit" wire:loading.attr="disabled" wire:target="exportMembers"
                                    class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="exportMembers" class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.707 7.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l2-2a1 1 0 00-1.414-1.414L11 7.586V3a1 1 0 10-2 0v4.586l-.293-.293z"></path>
                                        <path d="M3 5a2 2 0 012-2h1a1 1 0 010 2H5v7h2l1 2h4l1-2h2V5h-1a1 1 0 110-2h1a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5z"></path>
                                    </svg>
                                    <svg wire:loading wire:target="exportMembers" class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="exportMembers">Download Export</span>
                                    <span wire:loading wire:target="exportMembers">Preparing...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
