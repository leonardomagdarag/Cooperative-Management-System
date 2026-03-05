@props(['transaction'])

<div x-data="{ show: false }" x-init="setTimeout(() => show = true, 10)" x-show="show" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black opacity-25 transition-opacity" wire:click="closeEditModal"></div>

    {{-- Modal content --}}
    <div class="relative w-full max-w-2xl max-h-full mx-auto my-6 z-10">
        <div class="relative bg-white rounded-lg shadow">
            {{-- Modal header --}}
            <div class="flex items-start justify-between p-5 border-b border-gray-200 rounded-t">
                <h3 class="text-xl font-semibold text-gray-900">
                    {{ $transaction['id'] ? 'Edit Transaction' : 'Add New Transaction' }}
                </h3>
                <button type="button" wire:click="closeEditModal"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
            {{-- Modal body --}}
            <div class="p-6 space-y-6">
                <form wire:submit.prevent="saveTransaction">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if ($transaction['id'])
                            <x-forms.text-input name="id" label="Transaction ID" model="editForm.id"
                                :disabled="true" :readonly="true" class="bg-gray-100" />
                            <x-forms.text-input name="type_display" label="Type" model="editForm.transaction_type"
                                :disabled="true" :readonly="true" class="bg-gray-100" />
                        @else
                            <div class="col-span-2">
                                <x-forms.select-input name="transaction_type" label="Transaction Type"
                                    model="editForm.transaction_type" :required="true" :options="[
                                        'deposit' => 'Deposit',
                                        'withdrawal' => 'Withdrawal',
                                        'loan_payment' => 'Loan Payment',
                                        'loan_disbursement' => 'Loan Disbursement',
                                        'fee' => 'Fee',
                                    ]" />
                            </div>

                            @if (in_array($transaction['transaction_type'] ?? '', ['deposit', 'withdrawal', 'fee']))
                                <div class="col-span-2">
                                    <x-forms.text-input name="account_id" label="Account ID" model="editForm.account_id"
                                        :required="true" placeholder="Enter Account ID" />
                                </div>
                            @endif

                            @if (in_array($transaction['transaction_type'] ?? '', ['loan_payment', 'loan_disbursement']))
                                <div class="col-span-2">
                                    <x-forms.text-input name="loan_id" label="Loan ID" model="editForm.loan_id"
                                        :required="true" placeholder="Enter Loan ID" />
                                </div>
                            @endif
                        @endif

                        <div class="{{ $transaction['id'] ? '' : 'col-span-2' }}">
                            <x-forms.text-input name="amount" label="Amount" model="editForm.amount" type="number"
                                step="0.01" :required="true" />
                        </div>

                        <div class="col-span-2">
                            <x-forms.textarea-input name="note" label="Note" model="editForm.note" rows="3"
                                placeholder="Optional notes about this transaction" />
                        </div>
                    </div>
                </form>
            </div>
            {{-- Modal footer --}}
            <div class="flex items-center justify-end p-6 space-x-2 border-t border-gray-200 rounded-b">
                <button type="button" wire:click="closeEditModal"
                    class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10">
                    Cancel
                </button>
                <button type="button" wire:click="saveTransaction"
                    @if (!$transaction['id']) @if (in_array($transaction['transaction_type'] ?? '', ['deposit', 'withdrawal', 'fee']))
                            :disabled="!$wire.editForm.amount || !$wire.editForm.account_id"
                        @elseif (in_array($transaction['transaction_type'] ?? '', ['loan_payment', 'loan_disbursement']))
                            :disabled="!$wire.editForm.amount || !$wire.editForm.loan_id"
                        @else
                            :disabled="!$wire.editForm.amount" @endif
                    @endif
                    :class="{
                        'opacity-50 cursor-not-allowed': @if (!$transaction['id']) (@if (in_array($transaction['transaction_type'] ?? '', ['deposit', 'withdrawal', 'fee'])) (!$wire.editForm.amount || !$wire.editForm.account_id) @elseif (in_array($transaction['transaction_type'] ?? '', ['loan_payment', 'loan_disbursement'])) (!$wire.editForm.amount || !$wire.editForm.loan_id) @else !$wire.editForm.amount @endif
                        )
                    @else
                    false @endif
                    }"
                    class="text-white bg-gradient-to-r from-cyan-500 to-blue-500 hover:bg-gradient-to-bl focus:ring-4 focus:outline-none focus:ring-cyan-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                    <span wire:loading.remove wire:target="saveTransaction">
                        {{ $transaction['id'] ? 'Update Transaction' : 'Record Transaction' }}
                    </span>
                    <span wire:loading wire:target="saveTransaction">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Processing...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
