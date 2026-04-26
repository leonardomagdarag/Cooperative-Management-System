<div>
    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
        <div class="w-full mb-1">
            <div class="mb-4">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Reports</h1>
                <p class="text-sm text-gray-500">View financial summaries and activity reports</p>
            </div>

            {{-- Tabs for switching reports --}}
            <div class="mb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">
                    @foreach ($reports as $key => $label)
                        <li class="mr-2" role="presentation">
                            <button wire:click="$set('selectedReport', '{{ $key }}')"
                                @class([
                                    'inline-block p-4 border-b-2 rounded-t-lg transition-colors',
                                    'text-blue-600 border-blue-600' => $selectedReport === $key,
                                    'border-transparent hover:text-gray-600 hover:border-gray-300' =>
                                        $selectedReport !== $key,
                                ]) type="button" role="tab">
                                {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Filter bar (shared date filters + per-report filters) --}}
            <div class="sm:flex sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <label for="report-range" class="text-xs font-medium text-gray-500">Range</label>
                    <select id="report-range" wire:model.live="rangePreset"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                        @foreach ($rangePresets as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    @if ($rangePreset === 'custom')
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-500">Start</label>
                            <input type="date" wire:model.live="startDate"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2" />
                            <label class="text-xs font-medium text-gray-500">End</label>
                            <input type="date" wire:model.live="endDate"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2" />
                        </div>
                    @endif

                    {{-- Per-report extra filters --}}
                    @if (in_array($selectedReport, ['member_savings', 'member_activity']))
                        <div class="flex items-center gap-2">
                            <label for="report-member" class="text-xs font-medium text-gray-500">Member</label>
                            <select id="report-member" wire:model.live="memberId"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                                <option value="">All Members</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}">
                                        {{ $m->personalData?->first_name }} {{ $m->personalData?->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($selectedReport === 'loans')
                        <div class="flex items-center gap-2">
                            <label for="loan-filter" class="text-xs font-medium text-gray-500">Loan Filter</label>
                            <select id="loan-filter" wire:model.live="loanFilter"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                                <option value="all">All</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    @endif

                    @if ($selectedReport === 'expenses')
                        <div class="flex items-center gap-2">
                            <label for="expense-category" class="text-xs font-medium text-gray-500">Category</label>
                            <select id="expense-category" wire:model.live="expenseCategory"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                                <option value="">All</option>
                                @foreach ($expenseCategories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($selectedReport === 'member_activity')
                        <div class="flex items-center gap-2">
                            <label for="activity-type" class="text-xs font-medium text-gray-500">Type</label>
                            <select id="activity-type" wire:model.live="activityType"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                                <option value="all">All</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="loan_disbursement">Loan</option>
                                <option value="loan_payment">Payment</option>
                                <option value="fee">Fee</option>
                            </select>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    @if ($selectedReport === 'balance_sheet' && $canViewBalanceSheet)
                        <button type="button" wire:click="downloadBalanceSheetPdf"
                            class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 border border-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                            Download PDF
                        </button>
                    @endif

                    <button type="button" wire:click="$refresh"
                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300"
                        title="Refresh data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Content --}}
    @if ($selectedReport === 'financial')
        <div class="p-4 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Financial Summary</h2>
                    <p class="text-xs text-gray-500">
                        {{ $financialSummary['start']->format('M d, Y') }} –
                        {{ $financialSummary['end']->format('M d, Y') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Deposits</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_deposits'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Withdrawals</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_withdrawals'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Loan Releases</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_loan_releases'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Loan Repayments</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_loan_repayments'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Interest Earned (Expected)</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_interest_earned'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Fees</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_fees'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Total Expenses</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['total_expenses'], 2) }}</div>
                </div>
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="text-xs font-medium text-gray-500">Net Balance</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">
                        ₱{{ number_format($financialSummary['net'], 2) }}</div>
                </div>
            </div>

            {{-- Charts (visual summary) --}}
            <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Inflows vs Outflows</h3>
                        <span class="text-xs text-gray-500">Selected range</span>
                    </div>
                    <div class="mt-3 h-64">
                        <canvas id="financialInflowOutflowChart" aria-label="Inflows vs Outflows chart"></canvas>
                    </div>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Totals by Type</h3>
                        <span class="text-xs text-gray-500">Selected range</span>
                    </div>
                    <div class="mt-3 h-64">
                        <canvas id="financialTotalsByTypeChart" aria-label="Totals by type chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($selectedReport === 'balance_sheet' && $canViewBalanceSheet)
        <div class="p-4 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Balance Sheet</h2>
                    <p class="text-xs text-gray-500">
                        As of {{ $balanceSheetData['as_of']->format('M d, Y') }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500">Period Basis</div>
                    <div class="text-sm text-gray-900">
                        {{ $balanceSheetData['start']->format('M d, Y') }} - {{ $balanceSheetData['end']->format('M d, Y') }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-900">Assets</h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($balanceSheetData['assets'] as $line)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700">{{ $line['label'] }}</span>
                                <span class="font-medium text-gray-900">₱{{ number_format($line['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-2 mt-3 border-t border-gray-200 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-900">Total Assets</span>
                        <span class="text-sm font-semibold text-gray-900">₱{{ number_format($balanceSheetData['totals']['assets'], 2) }}</span>
                    </div>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-900">Liabilities and Equity</h3>

                    <div class="mt-3">
                        <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Liabilities</div>
                        <div class="mt-2 space-y-2">
                            @foreach ($balanceSheetData['liabilities'] as $line)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-700">{{ $line['label'] }}</span>
                                    <span class="font-medium text-gray-900">₱{{ number_format($line['amount'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="pt-2 mt-3 border-t border-gray-200 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-900">Total Liabilities</span>
                            <span
                                class="text-sm font-semibold text-gray-900">₱{{ number_format($balanceSheetData['totals']['liabilities'], 2) }}</span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Equity</div>
                        <div class="mt-2 space-y-2">
                            @foreach ($balanceSheetData['equity'] as $line)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-700">{{ $line['label'] }}</span>
                                    <span class="font-medium text-gray-900">₱{{ number_format($line['amount'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="pt-2 mt-3 border-t border-gray-200 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-900">Total Equity</span>
                            <span class="text-sm font-semibold text-gray-900">₱{{ number_format($balanceSheetData['totals']['equity'], 2) }}</span>
                        </div>
                    </div>

                    <div class="pt-2 mt-4 border-t-2 border-gray-300 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-900">Total Liabilities and Equity</span>
                        <span
                            class="text-sm font-semibold text-gray-900">₱{{ number_format($balanceSheetData['totals']['liabilities_and_equity'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($selectedReport === 'member_savings')
        <div class="flex flex-col">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden shadow">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Total
                                        Deposits</th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Total
                                        Withdrawals</th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Current
                                        Savings Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($memberSavingsRows as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $row['member_name'] }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($row['total_deposits'], 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($row['total_withdrawals'], 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($row['current_balance'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-6 text-sm text-gray-500">No savings records found
                                            for the selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-gray-200">
                {{ $memberSavingsRows->links() }}
            </div>
        </div>
    @elseif ($selectedReport === 'loans')
        <div class="flex flex-col">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden shadow">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Loan</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Interest</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Loan Date
                                    </th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Due Date</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Paid</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Remaining
                                    </th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($loanRows as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $row['member_name'] }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($row['loan_amount'], 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ number_format($row['interest_rate'], 2) }}%</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ optional($row['loan_date'])->format('M d, Y') }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('M d, Y') : '—' }}
                                        </td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($row['amount_paid'], 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($row['remaining_balance'], 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            @php
                                                $status = $row['status'] ?? 'Active';
                                            @endphp
                                            @if ($status === 'Completed')
                                                <span
                                                    class="bg-green-100 text-green-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Completed</span>
                                            @elseif ($status === 'Overdue')
                                                <span
                                                    class="bg-red-100 text-red-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Overdue</span>
                                            @else
                                                <span
                                                    class="bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Active</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="p-6 text-sm text-gray-500">No loans found for the
                                            selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-gray-200">
                {{ $loanRows->links() }}
            </div>
        </div>
    @elseif ($selectedReport === 'payments')
        <div class="flex flex-col">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden shadow">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Loan ID</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Payment</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Payment Date
                                    </th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Remaining
                                        Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($paymentRows as $payment)
                                    @php
                                        $pd = $payment->loan?->user?->personalData;
                                        $name = $pd ? trim($pd->first_name . ' ' . $pd->last_name) : 'N/A';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $name }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $payment->loan?->loan_number ?? '—' }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $payment->transaction_date ? $payment->transaction_date->format('M d, Y') : $payment->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format((float) ($payment->loan?->remainingBalance() ?? 0), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-6 text-sm text-gray-500">No loan payments found
                                            for the selected range.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-gray-200">
                {{ $paymentRows->links() }}
            </div>
        </div>
    @elseif ($selectedReport === 'expenses')
        <div class="flex flex-col">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden shadow">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Category</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Description
                                    </th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Amount</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Recorded By
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($expenseRows as $expense)
                                    @php
                                        $pd = $expense->recordedBy?->personalData;
                                        $rec = $pd
                                            ? trim($pd->first_name . ' ' . $pd->last_name)
                                            : $expense->recordedBy?->email ?? '—';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $expense->category }}</td>
                                        <td class="p-4 text-sm text-gray-900">{{ $expense->description ?? '—' }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($expense->amount, 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $expense->expense_date->format('M d, Y') }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">{{ $rec }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-6 text-sm text-gray-500">No expenses found for the
                                            selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-gray-200">
                {{ $expenseRows->links() }}
            </div>
        </div>
    @else
        {{-- Member Activity --}}
        <div class="flex flex-col">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden shadow">
                        <table class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Type</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Amount</th>
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($memberActivityRows as $tx)
                                    @php
                                        $pd = $tx->account?->user?->personalData ?? $tx->loan?->user?->personalData;
                                        $name = $pd ? trim($pd->first_name . ' ' . $pd->last_name) : 'N/A';
                                        $date = $tx->transaction_date ?: $tx->created_at;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $name }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            @switch($tx->transaction_type)
                                                @case('deposit')
                                                    <span
                                                        class="bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Deposit</span>
                                                @break

                                                @case('withdrawal')
                                                    <span
                                                        class="bg-orange-100 text-orange-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Withdrawal</span>
                                                @break

                                                @case('loan_payment')
                                                    <span
                                                        class="bg-green-100 text-green-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Payment</span>
                                                @break

                                                @case('loan_disbursement')
                                                    <span
                                                        class="bg-purple-100 text-purple-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Loan</span>
                                                @break

                                                @case('fee')
                                                    <span
                                                        class="bg-red-100 text-red-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Fee</span>
                                                @break

                                                @default
                                                    <span
                                                        class="bg-gray-100 text-gray-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">{{ ucfirst(str_replace('_', ' ', $tx->transaction_type)) }}</span>
                                            @endswitch
                                        </td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            ₱{{ number_format($tx->amount, 2) }}</td>
                                        <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $date->format('M d, Y') }}</td>
                                    </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="p-6 text-sm text-gray-500">No member activity found
                                                for the selected filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-white border-t border-gray-200">
                    {{ $memberActivityRows->links() }}
                </div>
            </div>
        @endif
    </div>

    @script
        <script>
            const renderFinancialReportsCharts = (payload) => {
                if (!payload || !window.ReportCharts?.renderFinancialCharts) return;
                window.ReportCharts.renderFinancialCharts(payload);
            };

            // Initial render for first page load
            renderFinancialReportsCharts(@js($financialCharts ?? []));

            // Re-render when Livewire updates the Financial Summary
            Livewire.on('reports:financial-charts', (event) => {
                renderFinancialReportsCharts(event.data);
            });
        </script>
    @endscript
