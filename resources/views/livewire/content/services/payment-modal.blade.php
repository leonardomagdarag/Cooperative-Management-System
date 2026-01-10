@if ($activeService === 'payment')
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black opacity-25 transition-opacity" wire:click="closeService"></div>

        <div class="z-10 bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-500 to-cyan-600 p-6 text-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-2xl font-bold">Make Loan Payment</h3>
                    <button wire:click="closeService" class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Step Indicator --}}
                @if ($paymentStep < 99 && $activeLoans && $activeLoans->count() > 0)
                    <div class="flex items-center justify-between">
                        @php $steps = $paymentTransactionMode === 'online' ? 4 : 3; @endphp
                        @for ($i = 1; $i <= $steps; $i++)
                            <div class="flex items-center {{ $i < $steps ? 'flex-1' : '' }}">
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold {{ $paymentStep >= $i ? 'bg-white text-blue-600' : 'bg-blue-400 text-white' }}">
                                    {{ $i }}
                                </div>
                                @if ($i < $steps)
                                    <div class="flex-1 h-1 mx-2 {{ $paymentStep > $i ? 'bg-white' : 'bg-blue-400' }}">
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                @endif
            </div>

            @if ($activeLoans && $activeLoans->count() > 0)
                {{-- Step 1: Payment Details --}}
                @if ($paymentStep === 1)
                    <div class="p-6 space-y-4">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Enter Payment Details</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Loan *</label>
                            <select wire:model.live="paymentLoanId"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required>
                                <option value="">-- Choose a loan --</option>
                                @foreach ($activeLoans as $loan)
                                    <option value="{{ $loan->id }}">
                                        {{ $loan->loan_number }} -
                                        {{ ucfirst(str_replace('_', ' ', $loan->loan_type)) }}
                                        (Balance: ₱{{ number_format($loan->remainingBalance(), 2) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('paymentLoanId')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        @if ($paymentLoanId)
                            @php
                                $selectedLoan = $activeLoans->firstWhere('id', $paymentLoanId);
                            @endphp

                            @if ($selectedLoan)
                                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                                    <h5 class="font-bold text-gray-900 mb-2">Loan Details</h5>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Original Amount:</span>
                                            <span
                                                class="font-semibold">₱{{ number_format($selectedLoan->loan_amount, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Remaining Balance:</span>
                                            <span
                                                class="font-semibold text-blue-700">₱{{ number_format($selectedLoan->remainingBalance(), 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Amount *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                                <input type="number" wire:model="paymentAmount" step="0.01" min="1"
                                    class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="0.00" required>
                            </div>
                            @error('paymentAmount')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Note (Optional)</label>
                            <textarea wire:model="paymentNote" rows="2"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Any additional notes..."></textarea>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="closeService"
                                class="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="button" wire:click="nextPaymentStep"
                                class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 2: Payment Mode Selection --}}
                @if ($paymentStep === 2)
                    <div class="p-6 space-y-4">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Choose Payment Method</h4>

                        <div class="space-y-3">
                            <button type="button" wire:click="$set('paymentTransactionMode', 'counter')"
                                class="w-full p-4 border-2 rounded-lg text-left transition-all {{ $paymentTransactionMode === 'counter' ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-300' }}">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-12 h-12 rounded-full flex items-center justify-center {{ $paymentTransactionMode === 'counter' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                        <i class="fas fa-cash-register text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900">Over the Counter</p>
                                        <p class="text-sm text-gray-600">Visit our office to make payment</p>
                                    </div>
                                    @if ($paymentTransactionMode === 'counter')
                                        <i class="fas fa-check-circle text-blue-500 text-xl"></i>
                                    @endif
                                </div>
                            </button>

                            <button type="button" wire:click="$set('paymentTransactionMode', 'online')"
                                class="w-full p-4 border-2 rounded-lg text-left transition-all {{ $paymentTransactionMode === 'online' ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-300' }}">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-12 h-12 rounded-full flex items-center justify-center {{ $paymentTransactionMode === 'online' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                        <i class="fas fa-mobile-alt text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900">Online/Digital Wallet</p>
                                        <p class="text-sm text-gray-600">GCash, Bank Transfer, or Online Banking</p>
                                    </div>
                                    @if ($paymentTransactionMode === 'online')
                                        <i class="fas fa-check-circle text-blue-500 text-xl"></i>
                                    @endif
                                </div>
                            </button>
                        </div>

                        @error('paymentTransactionMode')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousPaymentStep"
                                class="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </button>
                            <button type="button" wire:click="nextPaymentStep"
                                class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 3: Confirmation --}}
                @if ($paymentStep === 3)
                    @php
                        $selectedLoan = $activeLoans->firstWhere('loan_id', $paymentLoanId);
                    @endphp
                    <div class="p-6 space-y-4">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Confirm Your Payment</h4>

                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            @if ($selectedLoan)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Loan:</span>
                                    <span class="font-semibold text-gray-900">{{ $selectedLoan->loan_number }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment Amount:</span>
                                <span
                                    class="font-semibold text-gray-900">₱{{ number_format($paymentAmount, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment Method:</span>
                                <span class="font-semibold text-gray-900">
                                    {{ $paymentTransactionMode === 'counter' ? 'Over the Counter' : 'Online/Digital Wallet' }}
                                </span>
                            </div>
                            @if ($paymentNote)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Note:</span>
                                    <span class="font-semibold text-gray-900">{{ $paymentNote }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($paymentTransactionMode === 'counter')
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                                <p class="text-sm text-blue-700">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Please visit our office with your payment to complete this transaction.
                                </p>
                            </div>
                        @else
                            <div class="bg-green-50 border-l-4 border-green-400 p-4">
                                <p class="text-sm text-green-700">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Your payment will be processed through our secure online payment portal.
                                </p>
                            </div>
                        @endif

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousPaymentStep"
                                class="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </button>
                            <button type="button" wire:click="submitLoanPayment"
                                class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                                Confirm <i class="fas fa-check ml-2"></i>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 4: Online Payment Portal --}}
                @if ($paymentStep === 4)
                    <div class="p-6 space-y-4">
                        <div class="text-center py-8">
                            <div
                                class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-credit-card text-blue-600 text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">Online Payment Portal</h4>
                            <p class="text-gray-600 mb-6">Complete your payment through your preferred digital wallet
                                or
                                bank transfer</p>

                            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                                <p class="text-sm text-gray-600 mb-4">Payment integration coming soon!</p>
                                <p class="text-2xl font-bold text-gray-900">₱{{ number_format($paymentAmount, 2) }}
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="completeOnlinePaymentPayment"
                            class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Complete Payment <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                @endif

                {{-- Step 5 (Online): Receipt --}}
                @if ($paymentStep === 5 && $completedPaymentTransaction)
                    @include('livewire.content.services.receipt', [
                        'transaction' => $completedPaymentTransaction,
                    ])
                @endif

                {{-- Success Message (Counter) --}}
                @if ($paymentStep === 99)
                    <div class="p-6 text-center">
                        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check text-blue-600 text-4xl"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-2">Payment Submitted!</h4>
                        <p class="text-gray-600 mb-6">
                            @if ($isTreasurerMode && $paymentTransactionMode === 'counter')
                                The loan payment has been processed successfully.
                            @else
                                Please visit our office to complete your loan payment.
                            @endif
                        </p>

                        @if ($completedPaymentTransaction)
                            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                <p class="text-sm text-gray-600 mb-1">Transaction Reference</p>
                                <p class="text-2xl font-mono font-bold text-gray-900">
                                    {{ $completedPaymentTransaction->id }}
                                </p>
                            </div>
                        @endif

                        <button type="button" wire:click="closeService"
                            class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Close
                        </button>
                    </div>
                @endif
            @else
                <div class="p-6 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice-dollar text-gray-400 text-2xl"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">No Active Loans</h4>
                    <p class="text-gray-600 mb-6">
                        @if ($isTreasurerMode && $selectedMember)
                            This member doesn't have any active loans to make payments for.
                        @else
                            You don't have any active loans to make payments for.
                        @endif
                    </p>
                    <button type="button" wire:click="closeService"
                        class="px-6 py-3 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                        Close
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
