<div class="p-6">
    <div class="text-center mb-6">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-receipt text-green-600 text-3xl"></i>
        </div>
        <h4 class="text-xl font-bold text-gray-900 mb-2">Transaction Successful!</h4>
        <p class="text-gray-600">Your transaction has been completed</p>
    </div>

    {{-- Receipt Details --}}
    <div class="bg-gray-50 rounded-lg p-6 mb-4 space-y-3 border-2 border-dashed border-gray-300">
        <div class="text-center pb-3 border-b border-gray-300">
            <h5 class="font-bold text-gray-900">Settlers Credit Cooperative</h5>
            <p class="text-sm text-gray-600">Official Receipt</p>
        </div>

        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Transaction #:</span>
            <span
                class="font-mono font-semibold text-gray-900">{{ str_pad($transaction->id, 8, '0', STR_PAD_LEFT) }}</span>
        </div>

        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Date:</span>
            <span
                class="font-semibold text-gray-900">{{ $transaction->transaction_date->format('M d, Y h:i A') }}</span>
        </div>

        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Type:</span>
            <span
                class="font-semibold text-gray-900">{{ strtoupper(str_replace('_', ' ', $transaction->transaction_type)) }}</span>
        </div>

        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Account:</span>
            <span
                class="font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $transaction->account->account_type)) }}</span>
        </div>

        <div class="flex justify-between text-sm pt-3 border-t border-gray-300">
            <span class="text-gray-600">Amount:</span>
            <span class="text-2xl font-bold text-green-600">₱{{ number_format($transaction->amount, 2) }}</span>
        </div>

        @if ($transaction->note)
            <div class="text-sm pt-3 border-t border-gray-300">
                <span class="text-gray-600">Note:</span>
                <p class="text-gray-900 mt-1">{{ $transaction->note }}</p>
            </div>
        @endif

        <div class="flex justify-between text-sm pt-3 border-t border-gray-300">
            <span class="text-gray-600">Payment Method:</span>
            <span class="font-semibold text-gray-900">
                @if (str_contains($transaction->note, 'Over the counter'))
                    Over the Counter
                @else
                    Online/Digital Wallet
                @endif
            </span>
        </div>

        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Status:</span>
            <span
                class="px-2 py-1 {{ $transaction->status === 'completed' || $transaction->status === 'approved' ? 'bg-green-100 text-green-800' : ($transaction->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }} text-xs font-semibold rounded">
                {{ strtoupper($transaction->status) }}
            </span>
        </div>

        {{-- QR/Barcode Placeholder --}}
        <div class="pt-4 border-t border-gray-300">
            <div class="bg-white rounded p-4 text-center">
                <div class="h-24 flex items-center justify-center border-2 border-dashed border-gray-300 rounded mb-2">
                    <span class="text-gray-400 text-sm">QR/Barcode</span>
                </div>
                <p class="text-xs text-gray-500">Scan for verification</p>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
        <p class="text-sm text-blue-700">
            <i class="fas fa-info-circle mr-2"></i>
            Please save or print this receipt for your records.
        </p>
    </div>

    <button type="button" wire:click="closeService"
        class="w-full px-4 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
        <i class="fas fa-check mr-2"></i> Done
    </button>
</div>
