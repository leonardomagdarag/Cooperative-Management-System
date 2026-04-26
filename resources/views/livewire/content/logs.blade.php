<div>
    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
        <div class="w-full mb-1">
            <div class="mb-4">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Audit Logs</h1>
                <p class="text-sm text-gray-500">Track authentication, data edits, approvals, and other system actions.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-2">
                    <label for="logs-search" class="sr-only">Search logs</label>
                    <input id="logs-search" type="text" wire:model.live.debounce.300ms="search"
                        class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg block w-full p-2.5"
                        placeholder="Search event, tag, actor, email, description...">
                </div>

                <select wire:model.live="tagFilter"
                    class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg p-2.5">
                    <option value="all">All Tags</option>
                    @foreach ($tagOptions as $tag)
                        <option value="{{ $tag }}">{{ ucfirst($tag) }}</option>
                    @endforeach
                </select>

                <select wire:model.live="eventFilter"
                    class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg p-2.5">
                    <option value="all">All Events</option>
                    @foreach ($eventOptions as $event)
                        <option value="{{ $event }}">{{ $event }}</option>
                    @endforeach
                </select>

                <select wire:model.live="dateRange"
                    class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg p-2.5">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>

                <button type="button" wire:click="$refresh"
                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 table-fixed">
            <thead class="bg-gray-100">
                <tr>
                    <th wire:click.debounce.250ms="sortBy('created_at')"
                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                        Date
                        @if ($sortField === 'created_at')
                            <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                        @endif
                    </th>
                    <th wire:click.debounce.250ms="sortBy('log_name')"
                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                        Tag
                        @if ($sortField === 'log_name')
                            <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                        @endif
                    </th>
                    <th wire:click.debounce.250ms="sortBy('event')"
                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                        Event
                        @if ($sortField === 'event')
                            <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                        @endif
                    </th>
                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actor</th>
                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Subject</th>
                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Description</th>
                </tr>
            </thead>

            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-100">
                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                            {{ $log->created_at?->format('M d, Y h:i:s A') }}</td>
                        <td class="p-4 text-sm text-gray-700 whitespace-nowrap">
                            <span
                                class="px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-semibold">{{ $log->log_name ?? 'system' }}</span>
                        </td>
                        <td class="p-4 text-sm text-gray-700 whitespace-nowrap">{{ $log->event ?? 'n/a' }}</td>
                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                            @php
                                $actor = trim(
                                    ($log->causer?->personalData?->first_name ?? '') .
                                        ' ' .
                                        ($log->causer?->personalData?->last_name ?? ''),
                                );
                            @endphp
                            {{ $actor !== '' ? $actor : $log->causer?->email ?? 'System' }}
                        </td>
                        <td class="p-4 text-sm text-gray-600 whitespace-nowrap">
                            {{ class_basename((string) $log->subject_type) }}#{{ $log->subject_id ?? 'n/a' }}
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            <p class="line-clamp-2">{{ $log->description }}</p>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-gray-500">No audit logs found for the current
                            filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div
        class="sticky bottom-0 right-0 items-center w-full p-4 bg-white border-t border-gray-200 sm:flex sm:justify-between">
        <div class="flex items-center mb-4 sm:mb-0">
            <span class="text-sm font-normal text-gray-500">
                Showing
                <span
                    class="font-semibold text-gray-900">{{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }}</span>
                of
                <span class="font-semibold text-gray-900">{{ $logs->total() }}</span>
            </span>
        </div>
        <div class="flex items-center space-x-3">
            <button wire:click="previousPage" @if (!$logs->onFirstPage()) @else disabled @endif
                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white rounded-lg bg-blue-700 hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Previous
            </button>
            <span class="text-sm font-normal text-gray-500">Page <span
                    class="font-semibold text-gray-900">{{ $logs->currentPage() }}</span> of <span
                    class="font-semibold text-gray-900">{{ $logs->lastPage() }}</span></span>
            <button wire:click="nextPage" @if (!$logs->hasMorePages()) disabled @endif
                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white rounded-lg bg-blue-700 hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
            </button>
        </div>
    </div>
</div>
