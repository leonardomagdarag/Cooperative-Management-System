@if ($activeService === 'approve')
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black opacity-25 transition-opacity" wire:click="closeService"></div>

        <div class="z-10 bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-yellow-500 to-amber-600 p-6 text-white sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold">Approve Transactions</h3>
                        <p class="text-sm text-yellow-100 mt-1">{{ $pendingTransactions->count() }} pending
                            transaction(s)</p>
                    </div>
                    <button wire:click="closeService" class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-6">
                @if ($pendingTransactions->count() > 0)
                    <div class="space-y-4">
                        @foreach ($pendingTransactions as $transaction)
                            <div
                                class="bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-yellow-300 transition-all">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-12 h-12 rounded-full flex items-center justify-center {{ $transaction->transaction_type === 'deposit' ? 'bg-green-100 text-green-600' : ($transaction->transaction_type === 'withdrawal' ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600') }}">
                                            <i
                                                class="fas {{ $transaction->transaction_type === 'deposit' ? 'fa-arrow-down' : ($transaction->transaction_type === 'withdrawal' ? 'fa-arrow-up' : 'fa-money-bill-wave') }} text-xl"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-900 text-lg">
                                                {{ strtoupper(str_replace('_', ' ', $transaction->transaction_type)) }}
                                            </h4>
                                            <p class="text-sm text-gray-600">
                                                {{ $transaction->account->user->personalData->first_name ?? '' }}
                                                {{ $transaction->account->user->personalData->last_name ?? '' }}
                                                ({{ $transaction->account->user->user_number ?? 'N/A' }})
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-gray-900">
                                            ₱{{ number_format($transaction->amount, 2) }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $transaction->created_at->format('M d, Y h:i A') }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                                    <div>
                                        <span class="text-gray-600">Account Type:</span>
                                        <span class="font-semibold text-gray-900 ml-2">
                                            {{ $transaction->account ? ucfirst(str_replace('_', ' ', $transaction->account->account_type)) : 'N/A' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Transaction ID:</span>
                                        <span class="font-mono font-semibold text-gray-900 ml-2">
                                            {{ str_pad($transaction->id, 8, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </div>
                                    @if ($transaction->loan)
                                        <div class="col-span-2">
                                            <span class="text-gray-600">Loan:</span>
                                            <span class="font-semibold text-gray-900 ml-2">
                                                {{ $transaction->loan->loan_number }}
                                            </span>
                                        </div>
                                    @endif
                                    @if ($transaction->note)
                                        <div class="col-span-2">
                                            <span class="text-gray-600">Note:</span>
                                            <p class="text-gray-900 mt-1">{{ $transaction->note }}</p>
                                        </div>
                                    @endif
                                </div>

                                {{-- Action Buttons --}}
                                @if ($selectedTransactionId === $transaction->id)
                                    {{-- Show approval/rejection form --}}
                                    <div class="bg-gray-50 rounded-lg p-4 border-2 border-yellow-200">
                                        <div class="flex gap-3 mb-4">
                                            <button wire:click="$set('approvalAction', 'approve')" type="button"
                                                class="flex-1 px-4 py-2 rounded-lg font-medium transition-all {{ $approvalAction === 'approve' ? 'bg-green-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-green-300' }}">
                                                <i class="fas fa-check mr-2"></i>Approve
                                            </button>
                                            <button wire:click="$set('approvalAction', 'reject')" type="button"
                                                class="flex-1 px-4 py-2 rounded-lg font-medium transition-all {{ $approvalAction === 'reject' ? 'bg-red-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-700 hover:border-red-300' }}">
                                                <i class="fas fa-times mr-2"></i>Reject
                                            </button>
                                        </div>

                                        @if ($approvalAction === 'reject')
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Rejection
                                                    Reason *</label>
                                                <textarea wire:model="rejectionReason" rows="3"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                                    placeholder="Explain why this transaction is being rejected..."></textarea>
                                                @error('rejectionReason')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        @endif

                                        <div class="flex gap-3">
                                            <button wire:click="cancelApproval" type="button"
                                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                                Cancel
                                            </button>
                                            @if ($approvalAction === 'approve')
                                                <button wire:click="approveTransaction" type="button"
                                                    class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                                                    <i class="fas fa-check mr-2"></i>Confirm Approval
                                                </button>
                                            @elseif($approvalAction === 'reject')
                                                <button wire:click="rejectTransaction" type="button"
                                                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700">
                                                    <i class="fas fa-times mr-2"></i>Confirm Rejection
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    {{-- Show initial review button --}}
                                    <button wire:click="viewTransactionForApproval({{ $transaction->id }})"
                                        class="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg font-medium hover:bg-yellow-600 transition-colors">
                                        <i class="fas fa-eye mr-2"></i>Review Transaction
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-gray-400 text-4xl"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-900 mb-2">All Caught Up!</h4>
                        <p class="text-gray-600 mb-6">There are no pending transactions to approve at this time.</p>
                        <button type="button" wire:click="closeService"
                            class="px-6 py-3 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                            Close
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
