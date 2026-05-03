@if ($activeService === 'loan-review')
    <div x-data="{ show: false }" x-init="$nextTick(() => show = true)" x-show="show"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black opacity-25"
            @click="show = false; setTimeout(() => $wire.closeService(), 150)"></div>

        <div class="z-10 bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto"
            x-transition:enter="transition ease-out duration-200 delay-100"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-indigo-600 to-violet-700 p-6 text-white sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @if ($selectedLoanId)
                            <button wire:click="cancelLoanReview"
                                class="text-white hover:text-indigo-200 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                        @endif
                        <div>
                            <h3 class="text-2xl font-bold">
                                {{ $selectedLoanId ? 'Loan Application Detail' : 'Loan Application Review' }}
                            </h3>
                            <p class="text-indigo-200 text-sm mt-0.5">
                                {{ $selectedLoanId ? 'Review and take action on this application' : $reviewableLoans->count() . ' application(s) awaiting review' }}
                            </p>
                        </div>
                    </div>
                    <button @click="show = false; setTimeout(() => $wire.closeService(), 150)"
                        class="text-white hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-6">

                {{-- ── LOAN LIST VIEW ── --}}
                @if (!$selectedLoanId)
                    @if ($reviewableLoans->count() > 0)
                        <div class="space-y-3">
                            @foreach ($reviewableLoans as $loan)
                                @php
                                    $statusColor = $loan->review_status_color;
                                    $statusLabel = $loan->review_status_label;
                                @endphp
                                <div wire:key="loan-{{ $loan->id }}"
                                    class="bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-indigo-300 transition-all">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-file-contract text-indigo-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span
                                                        class="font-bold text-gray-900">{{ $loan->loan_number }}</span>
                                                    <span
                                                        class="px-2 py-0.5 text-xs font-semibold rounded-full
                                                        {{ $statusColor === 'gray' ? 'bg-gray-100 text-gray-700' : '' }}
                                                        {{ $statusColor === 'blue' ? 'bg-blue-100 text-blue-700' : '' }}
                                                        {{ $statusColor === 'amber' ? 'bg-amber-100 text-amber-700' : '' }}
                                                        {{ $statusColor === 'red' ? 'bg-red-100 text-red-700' : '' }}
                                                    ">{{ $statusLabel }}</span>
                                                </div>
                                                <p class="text-sm text-gray-700 font-medium">
                                                    {{ $loan->user->personalData->first_name ?? '' }}
                                                    {{ $loan->user->personalData->last_name ?? '' }}
                                                    <span
                                                        class="text-gray-500 font-normal">({{ $loan->user->user_number ?? 'N/A' }})</span>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    {{ ucfirst($loan->loan_type) }} Loan ·
                                                    ₱{{ number_format($loan->loan_amount, 2) }} ·
                                                    Applied {{ $loan->applied_at?->format('M d, Y') ?? 'N/A' }}
                                                </p>
                                            </div>
                                        </div>
                                        <button wire:click="selectLoanForReview({{ $loan->id }})"
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors flex-shrink-0">
                                            <i class="fas fa-eye mr-1.5"></i>Review
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-16">
                            <div
                                class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-clipboard-check text-indigo-400 text-4xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-2">No Applications to Review</h4>
                            <p class="text-gray-500 mb-6">All loan applications have been processed or are awaiting
                                member submission.</p>
                            <button type="button" wire:click="closeService"
                                class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700">
                                Close
                            </button>
                        </div>
                    @endif

                    {{-- ── LOAN DETAIL VIEW ── --}}
                @else
                    @php
                        $loan = $reviewableLoans->firstWhere('id', $selectedLoanId);
                    @endphp

                    @if (!$loan)
                        <div class="text-center py-12">
                            <p class="text-gray-500">Loan not found. It may have been updated.</p>
                            <button wire:click="cancelLoanReview" class="mt-4 text-indigo-600 hover:underline">← Back
                                to list</button>
                        </div>
                    @else
                        <div class="space-y-6">

                            {{-- Loan Summary Card --}}
                            <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <h4 class="text-lg font-bold text-gray-900">{{ $loan->loan_number }}</h4>
                                        <p class="text-sm text-gray-600 mt-0.5">
                                            {{ $loan->user->personalData->first_name ?? '' }}
                                            {{ $loan->user->personalData->last_name ?? '' }}
                                            ({{ $loan->user->user_number ?? 'N/A' }})
                                        </p>
                                    </div>
                                    @php
                                        $color = $loan->review_status_color;
                                    @endphp
                                    <span
                                        class="px-3 py-1 text-sm font-semibold rounded-full
                                        {{ $color === 'gray' ? 'bg-gray-100 text-gray-700' : '' }}
                                        {{ $color === 'blue' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $color === 'amber' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $color === 'red' ? 'bg-red-100 text-red-700' : '' }}
                                    ">{{ $loan->review_status_label }}</span>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Loan Type</p>
                                        <p class="font-semibold text-gray-900">{{ ucfirst($loan->loan_type) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Amount</p>
                                        <p class="font-semibold text-gray-900">
                                            ₱{{ number_format($loan->loan_amount, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Term / Frequency</p>
                                        <p class="font-semibold text-gray-900">{{ $loan->term_months }}mo ·
                                            {{ ucfirst($loan->payment_frequency) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Applied On</p>
                                        <p class="font-semibold text-gray-900">
                                            {{ $loan->applied_at?->format('M d, Y') ?? '—' }}</p>
                                    </div>
                                </div>

                                {{-- Interview info --}}
                                @if ($loan->interview_schedule)
                                    <div class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-500">Interview Scheduled</p>
                                            <p class="font-semibold text-gray-900">
                                                {{ $loan->interview_schedule->format('M d, Y h:i A') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500">Interview Status</p>
                                            <p class="font-semibold text-gray-900">
                                                {{ ucfirst(str_replace('_', ' ', $loan->interview_status ?? 'pending')) }}
                                            </p>
                                        </div>
                                    </div>
                                @endif

                                {{-- Group members --}}
                                @if ($loan->groupMembers->count() > 0)
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                            Co-makers ({{ $loan->groupMembers->count() }})</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($loan->groupMembers as $member)
                                                <span
                                                    class="px-3 py-1 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-700">
                                                    {{ $member->personalData->first_name ?? '' }}
                                                    {{ $member->personalData->last_name ?? '' }}
                                                    @if ($member->pivot->has_approved)
                                                        <i class="fas fa-check-circle text-green-500 ml-1"></i>
                                                    @else
                                                        <i class="fas fa-clock text-gray-400 ml-1"></i>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Existing review notes --}}
                                @if ($loan->review_notes)
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                            Review Notes</p>
                                        <p class="text-sm text-gray-700">{{ $loan->review_notes }}</p>
                                        @if ($loan->reviewer)
                                            <p class="text-xs text-gray-400 mt-1">
                                                — {{ $loan->reviewer->personalData->first_name ?? 'Officer' }}
                                                {{ $loan->reviewer->personalData->last_name ?? '' }},
                                                {{ $loan->reviewed_at?->format('M d, Y') }}
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- ── ACTION PANEL ── --}}

                            {{-- State: Awaiting Review (null) --}}
                            @if (is_null($loan->review_status))
                                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5">
                                    <p class="text-sm text-indigo-700 mb-4">
                                        <i class="fas fa-info-circle mr-1.5"></i>
                                        This application has not been picked up yet. Start the review to take ownership
                                        and notify the member.
                                    </p>
                                    <div class="flex gap-3">
                                        <button wire:click="cancelLoanReview"
                                            class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                                            Back
                                        </button>
                                        <button wire:click="startLoanReview"
                                            class="flex-1 px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                                            <i class="fas fa-play-circle mr-2"></i>Start Review
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- State: Under Review --}}
                            @if ($loan->review_status === 'under_review')
                                <div class="space-y-4">
                                    {{-- Action selector --}}
                                    <div class="flex gap-3">
                                        @if ($loan->interview_schedule && $loan->interview_status !== 'completed')
                                            <button wire:click="$set('loanReviewAction', 'interview_complete')"
                                                class="flex-1 px-4 py-2.5 rounded-lg font-medium transition-all text-sm
                                                    {{ $loanReviewAction === 'interview_complete' ? 'bg-blue-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-blue-300' }}">
                                                <i class="fas fa-calendar-check mr-1.5"></i>Mark Interview Complete
                                            </button>
                                        @endif
                                        <button wire:click="$set('loanReviewAction', 'endorse')"
                                            class="flex-1 px-4 py-2.5 rounded-lg font-medium transition-all text-sm
                                                {{ $loanReviewAction === 'endorse' ? 'bg-green-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-green-300' }}">
                                            <i class="fas fa-thumbs-up mr-1.5"></i>Endorse for Approval
                                        </button>
                                        <button wire:click="$set('loanReviewAction', 'reject')"
                                            class="flex-1 px-4 py-2.5 rounded-lg font-medium transition-all text-sm
                                                {{ $loanReviewAction === 'reject' ? 'bg-red-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-red-300' }}">
                                            <i class="fas fa-times-circle mr-1.5"></i>Reject
                                        </button>
                                    </div>

                                    @if ($loanReviewAction)
                                        <div
                                            class="bg-gray-50 rounded-xl p-4 border-2
                                            {{ $loanReviewAction === 'reject' ? 'border-red-200' : ($loanReviewAction === 'endorse' ? 'border-green-200' : 'border-blue-200') }}">

                                            {{-- Notes field (required for reject, optional for others) --}}
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                                    {{ $loanReviewAction === 'reject' ? 'Rejection Reason *' : 'Review Notes (optional)' }}
                                                </label>
                                                <textarea wire:model="loanReviewNotes" rows="3"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2
                                                        {{ $loanReviewAction === 'reject' ? 'focus:ring-red-400' : 'focus:ring-indigo-400' }}"
                                                    placeholder="{{ $loanReviewAction === 'reject' ? 'Explain why this application is being rejected (minimum 10 characters)...' : 'Add any notes for the record...' }}"></textarea>
                                                @error('loanReviewNotes')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="flex gap-3">
                                                <button wire:click="$set('loanReviewAction', '')"
                                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">
                                                    Cancel
                                                </button>

                                                @if ($loanReviewAction === 'interview_complete')
                                                    <button wire:click="markLoanInterviewComplete"
                                                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                                        <i class="fas fa-check mr-1.5"></i>Confirm Interview Complete
                                                    </button>
                                                @elseif ($loanReviewAction === 'endorse')
                                                    <button wire:click="endorseLoanForApproval"
                                                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                                                        <i class="fas fa-thumbs-up mr-1.5"></i>Endorse for Approval
                                                    </button>
                                                @elseif ($loanReviewAction === 'reject')
                                                    <button wire:click="rejectLoanInReview"
                                                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
                                                        <i class="fas fa-times mr-1.5"></i>Confirm Rejection
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if (!$loanReviewAction)
                                        <button wire:click="cancelLoanReview"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors text-sm">
                                            Back to List
                                        </button>
                                    @endif
                                </div>
                            @endif

                            {{-- State: Endorsed for Approval --}}
                            @if ($loan->review_status === 'endorsed_for_approval')
                                @php $canFinalApprove = Auth::user()?->hasAnyRole(['admin', 'manager']); @endphp

                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                                    @if ($canFinalApprove)
                                        <p class="text-sm text-amber-800 mb-4">
                                            <i class="fas fa-star mr-1.5"></i>
                                            This application has been endorsed by an officer and is ready for your final
                                            approval.
                                        </p>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes
                                                    (optional)</label>
                                                <textarea wire:model="loanReviewNotes" rows="2"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-400"
                                                    placeholder="Any final notes..."></textarea>
                                            </div>
                                            <div class="flex gap-3">
                                                <button wire:click="cancelLoanReview"
                                                    class="px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">
                                                    Back
                                                </button>
                                                <button wire:click="returnLoanForReview"
                                                    class="px-4 py-2.5 bg-yellow-500 text-white rounded-lg text-sm font-medium hover:bg-yellow-600 transition-colors">
                                                    <i class="fas fa-undo mr-1.5"></i>Return for Review
                                                </button>
                                                <button wire:click="approveLoanFinal"
                                                    class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
                                                    <i class="fas fa-check-circle mr-1.5"></i>Final Approve
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-sm text-amber-800">
                                            <i class="fas fa-clock mr-1.5"></i>
                                            This application has been endorsed and is now awaiting final approval from
                                            the admin or chairman. No further action is required from you.
                                        </p>
                                        <button wire:click="cancelLoanReview"
                                            class="mt-4 w-full px-4 py-2.5 border border-amber-300 text-amber-700 rounded-lg text-sm font-medium hover:bg-amber-100 transition-colors">
                                            Back to List
                                        </button>
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endif
                @endif

            </div>
        </div>
    </div>
@endif
