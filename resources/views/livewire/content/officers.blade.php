{{-- Single root element required by Livewire --}}
<div>
    {{-- Auto-refresh every 20 seconds, but only when modals are closed --}}
    <div @if (!$showModal && !$showEditModal) wire:poll.20s @endif>
        <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
            <div class="w-full mb-1">
                <div class="mb-4">
                    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Officers</h1>
                    <p class="text-sm text-gray-500">Manage organization officers and their roles</p>
                </div>

                <div class="sm:flex">
                    <div class="items-center hidden mb-3 sm:flex sm:divide-x sm:divide-gray-100 sm:mb-0">
                        <form class="lg:pr-3">
                            <label for="officers-search" class="sr-only">Search</label>
                            <div class="relative mt-1 lg:w-64 xl:w-96">
                                <input type="text" wire:model.live.debounce.300ms="search" id="officers-search"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="Search officers...">
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
                        {{-- Only admins and managers can add/edit officers --}}
                        @if ($canEdit)
                            <button type="button" wire:click="addOfficer"
                                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z">
                                    </path>
                                </svg>
                                Add Officer
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
                                        </div>
                                    </th>
                                    @if (in_array(Auth::user()->role, ['admin', 'manager']))
                                        <th scope="col"
                                            class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                            Officer ID
                                        </th>
                                    @endif
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                        Name
                                    </th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                        Title
                                    </th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                        Role
                                    </th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($officers as $officer)
                                    <tr wire:key="officer-{{ $officer->id }}" class="hover:bg-gray-100 cursor-pointer"
                                        wire:click="viewProfile('{{ $officer->id }}')">
                                        <td class="w-4 p-4" onclick="event.stopPropagation()">
                                            <div class="flex items-center">
                                                <input wire:model="selectedRows" value="{{ $officer->id }}"
                                                    type="checkbox"
                                                    class="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300">
                                            </div>
                                        </td>
                                        @if (in_array(Auth::user()->role, ['admin', 'manager']))
                                            <td class="p-4 text-sm font-semibold text-gray-900 whitespace-nowrap">
                                                {{ $officer->user_number }}
                                            </td>
                                        @endif
                                        <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                            {{ $officer->personalData->first_name ?? '' }}
                                            {{ $officer->personalData->last_name ?? '' }}
                                        </td>
                                        <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                            @if ($officer->personalData?->title)
                                                <span
                                                    class="bg-cyan-100 text-cyan-800 text-xs font-medium px-2.5 py-0.5 rounded">{{ ucwords(str_replace('_', ' ', $officer->personalData->title)) }}</span>
                                            @else
                                                <span class="text-gray-400 text-xs">N/A</span>
                                            @endif
                                        </td>
                                        <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                            <span
                                                class="bg-purple-100 text-purple-800 text-xs font-medium px-2.5 py-0.5 rounded">{{ ucfirst($officer->role) }}</span>
                                        </td>
                                        <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                            @if ($officer->role !== 'inactive')
                                                <span
                                                    class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                                            @else
                                                <span
                                                    class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ in_array(Auth::user()->role, ['admin', 'manager']) ? '6' : '5' }}"
                                            class="p-4 text-center">
                                            <div class="flex flex-col items-center justify-center py-12">
                                                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                                    </path>
                                                </svg>
                                                <p class="text-sm text-gray-500">No officers found</p>
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
                        class="font-semibold text-gray-900">{{ $officers->firstItem() ?? 0 }}-{{ $officers->lastItem() ?? 0 }}</span>
                    of <span class="font-semibold text-gray-900">{{ $officers->total() }}</span></span>
            </div>
            <div class="flex items-center space-x-3">
                {{ $officers->links() }}
            </div>
        </div>

        {{-- Profile Modal --}}
        @if ($showModal && $selectedOfficer)
            <x-modals.officer-profile wire:key="officer-profile-{{ $profileModalKey }}" :officer="$selectedOfficer"
                :canEdit="$canEdit" :activeTab="$activeTab" />
        @endif

        {{-- Edit/Add Officer Modal --}}
        @if ($showEditModal)
            <x-modals.officer-form wire:key="officer-form-{{ $modalKey }}" :officer="$isEdit && $selectedOfficer ? $selectedOfficer : null" :isEdit="$isEdit"
                :tabErrors="$tabErrors" />
        @endif

        {{-- Delete Confirmation Modal --}}
        @if ($confirmingDelete)
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
                aria-labelledby="modal-title" role="dialog" aria-modal="true">
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-gray-900 opacity-50 transition-opacity" wire:click="cancelDelete">
                </div>

                {{-- Modal content --}}
                <div class="relative w-full max-w-md mx-auto my-6 z-10">
                    <div class="relative bg-white rounded-lg shadow">
                        {{-- Modal header --}}
                        <div class="flex items-start justify-between p-5 border-b border-gray-200 rounded-t">
                            <h3 class="text-xl font-semibold text-gray-900">
                                Confirm Deletion
                            </h3>
                            <button type="button" wire:click="cancelDelete"
                                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        {{-- Modal body --}}
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                    </path>
                                </svg>
                            </div>
                            <p class="text-center text-gray-600">
                                Are you sure you want to delete this officer? This action will soft delete the record,
                                which can be recovered if needed.
                            </p>
                        </div>
                        {{-- Modal footer --}}
                        <div class="flex items-center justify-end p-4 space-x-2 border-t border-gray-200 rounded-b">
                            <button type="button" wire:click="cancelDelete"
                                class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10">
                                Cancel
                            </button>
                            <button type="button" wire:click="deleteOfficer"
                                class="text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                                Delete Officer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
