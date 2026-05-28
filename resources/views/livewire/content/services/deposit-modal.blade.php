@if ($activeService === 'deposit')
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
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-6 text-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-2xl font-bold">Deposit Money</h3>
                    <button @click="show = false; setTimeout(() => $wire.closeService(), 150)"
                        class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Step Indicator --}}
                @if ($depositStep < 99)
                    <div class="flex items-center justify-between">
                        @php $steps = 3; @endphp
                        @for ($i = 1; $i <= $steps; $i++)
                            <div class="flex items-center {{ $i < $steps ? 'flex-1' : '' }}">
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold {{ $depositStep >= $i ? 'bg-white text-green-600' : 'bg-green-400 text-white' }}">
                                    {{ $i }}
                                </div>
                                @if ($i < $steps)
                                    <div class="flex-1 h-1 mx-2 {{ $depositStep > $i ? 'bg-white' : 'bg-green-400' }}">
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                @endif
            </div>

            {{-- Step 1: Transaction Details --}}
            @if ($depositStep === 1)
                <div class="p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Enter Deposit Details</h4>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <select wire:model="depositAccountType"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="savings">Savings Account</option>
                            <option value="share-capital">Share Capital</option>
                        </select>
                    </div>

                    <x-forms.text-input name="depositAmount" label="Amount" model="depositAmount" type="number"
                        step="0.01" min="100" prefix="₱" :required="true" :validateOnBlur="false" />

                    <x-forms.textarea-input name="depositNote" label="Note (Optional)" model="depositNote"
                        :rows="2" placeholder="Add any notes..." :validateOnBlur="false" />

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="closeService"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" wire:click="nextDepositStep"
                            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 2: Payment Mode Selection --}}
            @if ($depositStep === 2)
                <div class="p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Choose Payment Method</h4>

                    <div class="space-y-3">
                        <button type="button" wire:click="$set('depositTransactionMode', 'counter')"
                            class="w-full p-4 border-2 rounded-lg text-left transition-all {{ $depositTransactionMode === 'counter' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300' }}">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center {{ $depositTransactionMode === 'counter' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                    <i class="fas fa-cash-register text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Over the Counter</p>
                                    <p class="text-sm text-gray-600">Visit our office to complete payment</p>
                                </div>
                                @if ($depositTransactionMode === 'counter')
                                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                @endif
                            </div>
                        </button>

                        <button type="button" wire:click="$set('depositTransactionMode', 'online')"
                            class="w-full p-4 border-2 rounded-lg text-left transition-all {{ $depositTransactionMode === 'online' ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-300' }}">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center {{ $depositTransactionMode === 'online' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                    <i class="fas fa-mobile-alt text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">Online/Digital Wallet</p>
                                    <p class="text-sm text-gray-600">GCash, Bank Transfer, or Online Banking</p>
                                </div>
                                @if ($depositTransactionMode === 'online')
                                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                @endif
                            </div>
                        </button>
                    </div>

                    @error('depositTransactionMode')
                        <span class="text-red-500 text-xs">{{ $message }}</span>
                    @enderror

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="previousDepositStep"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" wire:click="nextDepositStep"
                            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 3: Confirmation --}}
            @if ($depositStep === 3)
                <div class="p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Confirm Your Deposit</h4>

                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Account Type:</span>
                            <span class="font-semibold text-gray-900">
                                {{ $depositAccountType === 'savings' ? 'Savings Account' : 'Share Capital' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-semibold text-gray-900">₱{{ number_format($depositAmount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Method:</span>
                            <span class="font-semibold text-gray-900">
                                {{ $depositTransactionMode === 'counter' ? 'Over the Counter' : 'Online/Digital Wallet' }}
                            </span>
                        </div>
                        @if ($depositNote)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Note:</span>
                                <span class="font-semibold text-gray-900">{{ $depositNote }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($depositTransactionMode === 'counter')
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                Please visit our office with your payment to complete this transaction.
                            </p>
                        </div>
                    @elseif ($depositTransactionMode === 'online')
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Proof of Payment</label>
                            <input type="file" wire:model="depositProof" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/30">
                            <p class="text-xs text-gray-500">Upload a screenshot, receipt, or PDF proof before submitting.</p>
                            @if ($depositProof)
                                <p class="text-xs font-medium text-gray-700">Selected: {{ $depositProof->getClientOriginalName() }}</p>
                            @endif
                            @error('depositProof')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    @endif

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="previousDepositStep"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="button" wire:click="submitDeposit"
                            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                            Confirm <i class="fas fa-check ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Success Message (Counter) --}}
            @if ($depositStep === 99)
                <div class="p-6 text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-green-600 text-4xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-2">Request Submitted!</h4>
                    <p class="text-gray-600 mb-6">
                        @if ($depositTransactionMode === 'online')
                            Your online deposit proof has been uploaded. The transaction will remain pending until an officer verifies the payment.
                        @else
                            Please visit our office to complete your deposit. The request will remain pending until it is approved.
                        @endif
                    </p>

                    @if ($completedDepositTransaction)
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <p class="text-sm text-gray-600 mb-1">Transaction Reference</p>
                            <p class="text-2xl font-mono font-bold text-gray-900">
                                {{ $completedDepositTransaction->id }}
                            </p>
                        </div>
                    @endif

                    <button type="button" wire:click="closeService"
                        class="w-full px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        Close
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
