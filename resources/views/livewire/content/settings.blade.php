<div class="space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-2xl font-bold text-gray-900">Settings</h2>
        <p class="text-sm text-gray-600 mt-1">Configure system modules and open specialized management windows.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Loan Product Management</h3>
        <p class="text-sm text-gray-600 mb-4">
            Loan products are now managed in a dedicated content window so admins can focus on loan configuration
            without crowding Settings.
        </p>

        @if ($isAdmin)
            <button type="button" wire:click="openLoanProducts"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-cyan-600 rounded-lg hover:bg-cyan-700">
                Add or Manage Loans
            </button>
        @else
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4">
                Only admins can open Loan Product Management.
            </div>
        @endif
    </div>

    
</div>
