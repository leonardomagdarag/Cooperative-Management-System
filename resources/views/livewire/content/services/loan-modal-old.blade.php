@if ($activeService === 'loan')
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black opacity-25 transition-opacity" wire:click="closeEditModal"></div>

        <div class="z-10 bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-purple-500 to-pink-600 p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold">Apply for Loan</h3>
                        <p class="text-purple-100 text-sm mt-1">Step {{ $loanStep }} of 4</p>
                    </div>
                    <button wire:click="closeService" class="text-white hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Progress Bar --}}
                <div class="mt-4 flex gap-2">
                    @for ($i = 1; $i <= 4; $i++)
                        <div class="flex-1 h-2 rounded-full {{ $loanStep >= $i ? 'bg-white' : 'bg-purple-300' }}"></div>
                    @endfor
                </div>
            </div>

            <div class="p-6">
                {{-- Step 1: Loan Type Selection --}}
                @if ($loanStep === 1)
                    <div class="space-y-4">
                        <h4 class="text-xl font-bold text-gray-900 mb-4">Choose Loan Type</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($loanTypes as $key => $type)
                                <button wire:click="selectLoanType('{{ $key }}')"
                                    class="text-left p-5 border-2 border-gray-200 rounded-xl hover:border-purple-500 hover:bg-purple-50 transition-all group">
                                    <div class="flex items-start gap-4">
                                        <div
                                            class="w-12 h-12 bg-purple-100 group-hover:bg-purple-500 rounded-lg flex items-center justify-center transition-colors">
                                            <i
                                                class="fas fa-{{ $type['icon'] }} text-purple-600 group-hover:text-white text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h5 class="font-bold text-gray-900 mb-1">{{ $type['name'] }}</h5>
                                            <p class="text-sm text-gray-600 mb-2">{{ $type['description'] }}</p>
                                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                                <span>Rate: {{ $type['interest_rate'] }}%</span>
                                                <span>Max: ₱{{ number_format($type['max_amount']) }}</span>
                                                <span>Up to {{ $type['max_term'] }} months</span>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Step 2: Loan Calculator --}}
                @if ($loanStep === 2)
                    <div class="space-y-6">
                        <h4 class="text-xl font-bold text-gray-900">Loan Details</h4>

                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4">
                            <p class="text-sm text-purple-700">
                                <strong>{{ $loanTypes[$loanType]['name'] }}</strong> - Interest Rate:
                                {{ $loanTypes[$loanType]['interest_rate'] }}%
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Loan Amount *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                                <input type="number" wire:model.live="loanAmount" step="1000" min="1000"
                                    max="{{ $loanTypes[$loanType]['max_amount'] }}"
                                    class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    placeholder="0.00" required>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Maximum:
                                ₱{{ number_format($loanTypes[$loanType]['max_amount']) }}</p>
                            @error('loanAmount')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Loan Term (Months) *</label>
                            <input type="number" wire:model.live="loanTerm" min="1"
                                max="{{ $loanTypes[$loanType]['max_term'] }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                placeholder="12" required>
                            <p class="text-xs text-gray-500 mt-1">Maximum: {{ $loanTypes[$loanType]['max_term'] }}
                                months</p>
                            @error('loanTerm')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        @if ($loanAmount && $loanTerm)
                            <div class="bg-green-50 border-2 border-green-200 rounded-xl p-6">
                                <h5 class="font-bold text-gray-900 mb-3">Loan Summary</h5>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Loan Amount:</span>
                                        <span class="font-semibold">₱{{ number_format($loanAmount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Interest Rate:</span>
                                        <span class="font-semibold">{{ $loanTypes[$loanType]['interest_rate'] }}% per
                                            annum</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Term:</span>
                                        <span class="font-semibold">{{ $loanTerm }} months</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-green-300">
                                        <span class="text-gray-900 font-bold">Estimated Monthly Payment:</span>
                                        <span
                                            class="text-green-700 font-bold text-lg">₱{{ number_format($this->monthlyPayment, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousLoanStep"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" wire:click="nextLoanStep"
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Continue
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 3: Loan Purpose & Co-maker --}}
                @if ($loanStep === 3)
                    <div class="space-y-6">
                        <h4 class="text-xl font-bold text-gray-900">Additional Information</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Purpose of Loan *</label>
                            <textarea wire:model="loanPurpose" rows="3"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                placeholder="Describe how you will use the loan..." required></textarea>
                            @error('loanPurpose')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Frequency *</label>
                            <select wire:model="loanPaymentFrequency"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="weekly">Weekly</option>
                                <option value="bi-weekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                            </select>
                        </div>

                        <div class="border-t pt-6">
                            <h5 class="font-bold text-gray-900 mb-4">Co-Maker Information</h5>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Co-Maker Name *</label>
                                    <input type="text" wire:model="loanCoMaker"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        placeholder="Full name" required>
                                    @error('loanCoMaker')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Co-Maker Contact
                                        *</label>
                                    <input type="text" wire:model="loanCoMakerContact"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        placeholder="Phone number or email" required>
                                    @error('loanCoMakerContact')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousLoanStep"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" wire:click="nextLoanStep"
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Continue
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Step 4: Review & Submit --}}
                @if ($loanStep === 4)
                    <div class="space-y-6">
                        <h4 class="text-xl font-bold text-gray-900">Review Your Application</h4>

                        <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Loan Type</p>
                                <p class="font-semibold text-gray-900">{{ $loanTypes[$loanType]['name'] }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Amount</p>
                                    <p class="font-semibold text-gray-900">₱{{ number_format($loanAmount, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Term</p>
                                    <p class="font-semibold text-gray-900">{{ $loanTerm }} months</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Monthly Payment</p>
                                <p class="font-bold text-purple-600 text-xl">
                                    ₱{{ number_format($this->monthlyPayment, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Purpose</p>
                                <p class="font-semibold text-gray-900">{{ $loanPurpose }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Co-Maker</p>
                                <p class="font-semibold text-gray-900">{{ $loanCoMaker }}
                                    ({{ $loanCoMakerContact }})</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">
                                <strong>Important:</strong> After submission, your application will be reviewed by our
                                loan officers. You will be notified of the decision within 3-5 business days. Please
                                prepare required documents for verification.
                            </p>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" wire:click="previousLoanStep"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                                Back
                            </button>
                            <button type="button" wire:click="submitLoanApplication"
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                                Submit Application
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
