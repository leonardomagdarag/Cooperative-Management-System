@if ($activeService === 'withdraw')
    <div x-data="{ show: false }" x-init="$nextTick(() => show = true)" x-show="show"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black opacity-25"
            @click="show = false; setTimeout(() => $wire.closeService(), 150)"></div>

        <div class="z-10 bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto"
            x-transition:enter="transition ease-out duration-200 delay-100"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">
            <div class="bg-gradient-to-r from-orange-500 to-red-600 p-6 text-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-2xl font-bold">Withdraw Money</h3>
                    <button @click="show = false; setTimeout(() => $wire.closeService(), 150)"
                        class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Step Indicator --}}
                @if ($withdrawalStep < 99)
                    <div class="flex items-center justify-between">
                        @php $steps = $withdrawalTransactionMode === 'online' ? 4 : 3; @endphp
                        @for ($i = 1; $i <= $steps; $i++)
                            <div class="flex items-center {{ $i < $steps ? 'flex-1' : '' }}">
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold {{ $withdrawalStep >= $i ? 'bg-white text-orange-600' : 'bg-orange-400 text-white' }}">
                                    {{ $i }}
                                </div>
                                @if ($i < $steps)
                                    <div
                                        class="flex-1 h-1 mx-2 {{ $withdrawalStep > $i ? 'bg-white' : 'bg-orange-400' }}">
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                @endif
            </div>

            {{-- Step 1: Withdrawal Details --}}
            @if ($withdrawalStep === 1)
                <div class="p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Enter Withdrawal Details</h4>

                    <div class="bg-green-50 border-l-4 border-green-400 p-4">
                        <p class="text-sm text-green-700">
                            <strong>Current Balance:</strong> ₱{{ number_format($savingsAccount?->balance ?? 0, 2) }}
                        </p>
                        @if ($this->availableBalance < ($savingsAccount?->balance ?? 0))
                            <p class="text-sm text-orange-600 mt-1">
                                <strong>Available Balance:</strong> ₱{{ number_format($this->availableBalance, 2) }}
                                <span class="text-xs">(excluding pending withdrawals)</span>
                            </p>
                        @endif
                    </div>

                    <x-forms.text-input name="withdrawalAmount" label="Amount" model="withdrawalAmount" type="number"
                        step="0.01" min="100" :max="$this->availableBalance" prefix="₱" :required="true"
                        :validateOnBlur="false" />

                    <x-forms.textarea-input name="withdrawalReason" label="Reason" model="withdrawalReason"
                        :rows="2" placeholder="Purpose of withdrawal..." :required="true" :validateOnBlur="false" />

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="closeService"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" wire:click="nextWithdrawalStep"
                            class="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 2: Payment Mode Selection --}}
            @if ($withdrawalStep === 2)
                <div class="p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Choose Collection Method</h4>

                    <div class="space-y-3">
                        <button type="button" wire:click="$set('withdrawalTransactionMode', 'counter')"
                            class="w-full p-4 border-2 rounded-lg text-left transition-all {{ $withdrawalTransactionMode === 'counter' ? 'border-orange-500 bg-orange-50' : 'border-gray-300 hover:border-orange-300' }}">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center {{ $withdrawalTransactionMode === 'counter' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                    <i class="fas fa-cash-register text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Over the Counter</p>
                                    <p class="text-sm text-gray-600">Visit our office to collect cash</p>
                                </div>
                                @if ($withdrawalTransactionMode === 'counter')
                                    <i class="fas fa-check-circle text-orange-500 text-xl"></i>
                                @endif
                            </div>
                        </button>

                        <button type="button" wire:click="$set('withdrawalTransactionMode', 'online')"
                            class="w-full p-4 border-2 rounded-lg text-left transition-all {{ $withdrawalTransactionMode === 'online' ? 'border-orange-500 bg-orange-50' : 'border-gray-300 hover:border-orange-300' }}">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center {{ $withdrawalTransactionMode === 'online' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                    <i class="fas fa-university text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Bank Transfer</p>
                                    <p class="text-sm text-gray-600">Receive funds via online bank transfer</p>
                                </div>
                                @if ($withdrawalTransactionMode === 'online')
                                    <i class="fas fa-check-circle text-orange-500 text-xl"></i>
                                @endif
                            </div>
                        </button>
                    </div>

                    @error('withdrawalTransactionMode')
                        <span class="text-red-500 text-xs">{{ $message }}</span>
                    @enderror

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="previousWithdrawalStep"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" wire:click="nextWithdrawalStep"
                            class="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 3: Confirmation --}}
            @if ($withdrawalStep === 3)
                <div class="p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Confirm Your Withdrawal</h4>

                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-semibold text-gray-900">₱{{ number_format($withdrawalAmount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reason:</span>
                            <span class="font-semibold text-gray-900">{{ $withdrawalReason }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Collection Method:</span>
                            <span class="font-semibold text-gray-900">
                                {{ $withdrawalTransactionMode === 'counter' ? 'Over the Counter' : 'Bank Transfer' }}
                            </span>
                        </div>
                    </div>

                    @if ($withdrawalTransactionMode === 'counter')
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                Your withdrawal request will be reviewed and approved by authorized personnel. You will
                                receive a notification once approved.
                            </p>
                        </div>
                    @else
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                Your withdrawal request will be reviewed and approved by authorized personnel. Bank
                                transfers may take 1-2 business days after approval.
                            </p>
                        </div>
                    @endif

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="previousWithdrawalStep"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" wire:click="submitWithdrawal"
                            class="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                            Confirm <i class="fas fa-check ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 4: Online Payment Processing --}}
            @if ($withdrawalStep === 4)
                <div class="p-6 space-y-4">
                    <div class="text-center py-8">
                        <div
                            class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-university text-orange-600 text-3xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Processing Bank Transfer</h4>
                        <p class="text-gray-600 mb-6">We'll process your bank transfer request</p>

                        <div class="bg-gray-50 rounded-lg p-6 mb-6">
                            <p class="text-sm text-gray-600 mb-4">Funds will be transferred to your registered bank
                                account</p>
                            <p class="text-2xl font-bold text-gray-900">₱{{ number_format($withdrawalAmount, 2) }}</p>
                        </div>
                    </div>

                    <button type="button" wire:click="completeOnlineWithdrawalPayment"
                        class="w-full px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                        Complete <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            @endif

            {{-- Step 5 (Online): Receipt --}}
            @if ($withdrawalStep === 5 && $completedWithdrawalTransaction)
                @include('livewire.content.services.receipt', [
                    'transaction' => $completedWithdrawalTransaction,
                ])
            @endif

            {{-- Success Message (Counter) --}}
            @if ($withdrawalStep === 99)
                <div class="p-6 text-center">
                    <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-clock text-orange-600 text-4xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-2">Withdrawal Request Submitted!</h4>
                    <p class="text-gray-600 mb-6">
                        Your withdrawal request has been submitted for approval. You will receive a notification once it
                        has been reviewed by authorized personnel.
                    </p>

                    @if ($completedWithdrawalTransaction)
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <p class="text-sm text-gray-600 mb-1">Transaction Reference</p>
                            <p class="text-2xl font-mono font-bold text-gray-900">
                                {{ $completedWithdrawalTransaction->id }}
                            </p>
                        </div>
                    @endif

                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            @if ($withdrawalTransactionMode === 'counter')
                                Once approved, you can visit our office with valid ID to collect your withdrawal.
                            @else
                                Once approved, funds will be transferred to your registered bank account within 1-2
                                business days.
                            @endif
                        </p>
                    </div>

                    <button type="button" wire:click="closeService"
                        class="w-full px-4 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700">
                        Close
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
