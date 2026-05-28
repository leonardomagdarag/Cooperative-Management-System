@props(['loan', 'canUploadDocuments' => false])

<div x-data="{ show: false, activeTab: 'details' }" x-init="setTimeout(() => show = true, 10)" x-show="show" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black opacity-25 transition-opacity" wire:click="closeModal"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-5xl max-h-[90vh] mx-auto my-6 z-10">
        <div class="relative bg-white rounded-lg shadow overflow-hidden">
            {{-- Modal header --}}
            <div
                class="flex items-start justify-between p-5 border-b border-gray-200 rounded-t bg-gradient-to-r from-cyan-500 to-emerald-500">
                <div>
                    <h3 class="text-xl font-semibold text-white">
                        Loan Details
                    </h3>
                    <p class="text-sm text-white/90 mt-1">{{ $loan->loan_number }}</p>
                </div>
                <button type="button" wire:click="closeModal"
                    class="text-white bg-white/20 hover:bg-white/30 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button @click="activeTab = 'details'"
                        :class="activeTab === 'details' ? 'border-cyan-500 text-cyan-600' :
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                        Loan Details
                    </button>
                    <button @click="activeTab = 'schedule'"
                        :class="activeTab === 'schedule' ? 'border-cyan-500 text-cyan-600' :
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                        Payment Schedule
                    </button>
                    <button @click="activeTab = 'history'"
                        :class="activeTab === 'history' ? 'border-cyan-500 text-cyan-600' :
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                        Payment History
                    </button>
                </nav>
            </div>

            {{-- Modal body --}}
            <div class="p-6 space-y-6 max-h-[calc(90vh-180px)] overflow-y-auto">

                @php
                    $amortizationSchedule = $this->generateAmortizationSchedule($loan);
                    $projectedInterest = collect($amortizationSchedule)->sum('interest');
                    $projectedRepayment = (float) $loan->loan_amount + $projectedInterest;
                @endphp

                {{-- Details Tab --}}
                <div x-show="activeTab === 'details'" class="space-y-6">
                    {{-- Loan Overview Cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg">
                            <div class="text-sm text-blue-600 font-medium">Loan Amount</div>
                            <div class="text-2xl font-bold text-blue-900">₱{{ number_format($loan->loan_amount, 2) }}
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg">
                            <div class="text-sm text-green-600 font-medium">Total Paid</div>
                            <div class="text-2xl font-bold text-green-900">₱{{ number_format($loan->totalPaid(), 2) }}
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg">
                            <div class="text-sm text-orange-600 font-medium">Balance Remaining</div>
                            <div class="text-2xl font-bold text-orange-900">
                                ₱{{ number_format($loan->remainingBalance(), 2) }}</div>
                        </div>
                    </div>

                    {{-- Loan Information --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Loan Information</h4>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Loan Type:</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $loan->loan_type_label }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Principal Amount:</dt>
                                    <dd class="text-sm font-medium text-gray-900">₱{{ number_format($loan->loan_amount, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Annual Interest Rate:</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ number_format((float) $loan->interest_rate, 2) }}%
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Repayment Term:</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $loan->term_months }} months</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Payment Frequency:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ ucwords(str_replace('-', ' ', $loan->payment_frequency)) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Estimated Interest Over Term:</dt>
                                    <dd class="text-sm font-medium text-gray-900">₱{{ number_format($projectedInterest, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Estimated Total Repayment:</dt>
                                    <dd class="text-sm font-medium text-gray-900">₱{{ number_format($projectedRepayment, 2) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Status:</dt>
                                    <dd>
                                        @if ($loan->loan_status === 'active')
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        @elseif($loan->loan_status === 'paid_off')
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Paid
                                                Off</span>
                                        @elseif($loan->loan_status === 'approved')
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Approved</span>
                                        @elseif($loan->loan_status === 'pending')
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Pending</span>
                                        @else
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($loan->loan_status) }}</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Important Dates</h4>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Applied:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ $loan->applied_at ? $loan->applied_at->format('M d, Y') : 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Approved:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ $loan->approved_at ? $loan->approved_at->format('M d, Y') : 'N/A' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Due Date:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'N/A' }}</dd>
                                </div>
                                @if ($loan->approvedBy)
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Approved By:</dt>
                                        <dd class="text-sm font-medium text-gray-900">
                                            {{ $loan->approvedBy->personalData->first_name ?? 'N/A' }}
                                            {{ $loan->approvedBy->personalData->last_name ?? '' }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Group Members (if applicable) --}}
                    @if ($loan->groupMembers && $loan->groupMembers->count() > 0)
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Group Loan Members</h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <ul class="space-y-2">
                                    @foreach ($loan->groupMembers as $member)
                                        <li class="flex items-center justify-between">
                                            <span class="text-sm text-gray-900">
                                                {{ $member->personalData->first_name ?? '' }}
                                                {{ $member->personalData->last_name ?? '' }}
                                            </span>
                                            @if ($member->pivot->has_approved)
                                                <span
                                                    class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded">Approved
                                                    {{ $member->pivot->approved_at ? $member->pivot->approved_at->format('M d, Y') : '' }}</span>
                                            @else
                                                <span
                                                    class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Pending
                                                    Approval</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="font-semibold text-gray-900">Documents</h4>
                                <p class="mt-1 text-sm text-gray-500">Loan-related image files and PDFs attached to this record.</p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-200">
                                {{ $loan->documents->count() }} {{ 
                                    Illuminate\Support\Str::plural('file', $loan->documents->count())
                                }}
                            </span>
                        </div>

                        @if ($canUploadDocuments)
                            <form wire:submit="uploadLoanDocument" class="mt-4 rounded-lg border border-dashed border-cyan-200 bg-white p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Upload a supporting document</p>
                                        <p class="mt-1 text-xs text-gray-500">Accepted formats: JPG, PNG, GIF, WEBP, or PDF up to 10 MB.</p>
                                    </div>
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <input type="file" wire:model="loanDocumentUpload" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                                            class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-cyan-700 hover:file:bg-cyan-100 sm:w-auto">
                                        <button type="submit" wire:loading.attr="disabled" wire:target="loanDocumentUpload,uploadLoanDocument"
                                            class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60">
                                            <span wire:loading.remove wire:target="loanDocumentUpload,uploadLoanDocument">Upload</span>
                                            <span wire:loading wire:target="loanDocumentUpload,uploadLoanDocument">Uploading...</span>
                                        </button>
                                    </div>
                                </div>

                                @error('loanDocumentUpload')
                                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </form>
                        @endif

                        @if ($loan->documents->isNotEmpty())
                            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                @foreach ($loan->documents as $document)
                                    @php
                                        $isImage = \Illuminate\Support\Str::startsWith((string) $document->mime_type, 'image/')
                                            || \Illuminate\Support\Str::endsWith(strtolower($document->storage_path), ['.jpg', '.jpeg', '.png', '.gif', '.webp']);
                                        $fileSizeKb = max(1, (int) ceil(((int) $document->file_size) / 1024));
                                    @endphp
                                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" x-data="{ lightbox: false }">
                                        <div class="flex items-start gap-4">
                                            @if ($isImage)
                                                <img src="{{ Storage::url($document->storage_path) }}" alt="{{ $document->original_name }}"
                                                    @click="lightbox = true"
                                                    class="h-20 w-20 cursor-pointer rounded-lg border border-gray-200 object-cover transition-opacity hover:opacity-90">
                                            @else
                                                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-500">
                                                    <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z" />
                                                    </svg>
                                                </div>
                                            @endif

                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-gray-800">{{ $document->original_name }}</p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ strtoupper($isImage ? 'image' : 'pdf') }}
                                                    <span class="mx-1">&bull;</span>
                                                    {{ number_format($fileSizeKb) }} KB
                                                    <span class="mx-1">&bull;</span>
                                                    {{ $document->created_at->format('M d, Y h:i A') }}
                                                </p>
                                                @if ($document->uploadedBy)
                                                    <p class="mt-1 text-xs text-gray-500">
                                                        Uploaded by {{ trim(($document->uploadedBy->personalData->first_name ?? '') . ' ' . ($document->uploadedBy->personalData->last_name ?? '')) ?: ($document->uploadedBy->user_number ?? 'Officer') }}
                                                    </p>
                                                @endif
                                                <a href="{{ Storage::url($document->storage_path) }}" target="_blank"
                                                    class="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7m0 0v7m0-7L10 14"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5v14h14v-5"></path>
                                                    </svg>
                                                    Open document
                                                </a>
                                            </div>
                                        </div>

                                        @if ($isImage)
                                            <div x-show="lightbox" x-cloak @click="lightbox = false"
                                                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4" x-transition>
                                                <img src="{{ Storage::url($document->storage_path) }}" alt="{{ $document->original_name }} full"
                                                    class="max-h-full max-w-full rounded-lg shadow-2xl" @click.stop>
                                                <button @click="lightbox = false"
                                                    class="absolute right-4 top-4 text-2xl text-white/80 hover:text-white">
                                                    &times;
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                                No documents uploaded for this loan yet.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Payment Schedule Tab --}}
                <div x-show="activeTab === 'schedule'" class="space-y-4">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Amortization Schedule</h4>
                        <span class="text-sm text-gray-600">Total Periods:
                            {{ count($amortizationSchedule) }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        #</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Due Date</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Payment</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Principal</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Interest</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($amortizationSchedule as $payment)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            {{ $payment['payment_number'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            {{ $payment['due_date']->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                            ₱{{ number_format($payment['payment_amount'], 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                            ₱{{ number_format($payment['principal'], 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-right">
                                            ₱{{ number_format($payment['interest'], 2) }}</td>
                                        <td
                                            class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                            ₱{{ number_format($payment['balance'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Payment History Tab --}}
                <div x-show="activeTab === 'history'" class="space-y-4">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Payment History</h4>
                        <span class="text-sm text-gray-600">Total Payments:
                            {{ $loan->completedPayments->count() }}</span>
                    </div>

                    @if ($loan->completedPayments && $loan->completedPayments->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Transaction #</th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($loan->completedPayments as $payment)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ $payment->created_at->format('M d, Y') }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ $payment->transaction_number ?? 'N/A' }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                ₱{{ number_format($payment->amount, 2) }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    {{ ucfirst($payment->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No payment history</h3>
                            <p class="mt-1 text-sm text-gray-500">No payments have been made for this loan yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-end p-5 border-t border-gray-200 space-x-2">
                <button type="button" wire:click="closeModal"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
