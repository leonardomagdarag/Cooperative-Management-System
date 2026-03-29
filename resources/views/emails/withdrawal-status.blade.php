@extends('emails.layout')

@section('content')
    <h2>
        @if ($status === 'approved')
            ✅ Withdrawal Request Approved
        @else
            ❌ Withdrawal Request Status Update
        @endif
    </h2>

    <p>Dear {{ $withdrawal->user->personalData->first_name ?? 'Member' }},</p>

    @if ($status === 'approved')
        <div class="alert-box alert-success">
            <strong>Good news!</strong> Your withdrawal request has been approved.
        </div>

        <p>
            We are pleased to inform you that your withdrawal request has been approved and processed.
        </p>

        <p><strong>Withdrawal Details:</strong></p>
        <ul>
            <li><strong>Transaction Number:</strong> {{ $withdrawal->transaction_number ?? 'N/A' }}</li>
            <li><strong>Amount:</strong> ₱{{ number_format($withdrawal->amount, 2) }}</li>
            <li><strong>Requested On:</strong> {{ $withdrawal->created_at->format('F d, Y h:i A') }}</li>
            <li><strong>Approved On:</strong> {{ now()->format('F d, Y h:i A') }}</li>
        </ul>

        @if ($withdrawal->note)
            <p><strong>Note:</strong> {{ $withdrawal->note }}</p>
        @endif

        <p>
            The amount has been deducted from your account. You can now proceed to our office to collect your funds
            or check your bank account if you selected online withdrawal.
        </p>

        <a href="{{ config('app.url') }}/member/history" class="button">View Transaction History</a>

        <p>
            Thank you for being a valued member of Settlers Credit Cooperative.
        </p>
    @else
        <div class="alert-box alert-warning">
            We regret to inform you that your withdrawal request could not be approved at this time.
        </div>

        <p>
            Your withdrawal request has been carefully reviewed, and unfortunately, we are unable to approve it at this
            time.
        </p>

        <p><strong>Withdrawal Details:</strong></p>
        <ul>
            <li><strong>Transaction Number:</strong> {{ $withdrawal->transaction_number ?? 'N/A' }}</li>
            <li><strong>Amount:</strong> ₱{{ number_format($withdrawal->amount, 2) }}</li>
            <li><strong>Requested On:</strong> {{ $withdrawal->created_at->format('F d, Y h:i A') }}</li>
            <li><strong>Status:</strong> Rejected</li>
        </ul>

        @if ($reason)
            <p><strong>Reason:</strong> {{ $reason }}</p>
        @else
            <p><strong>Reason:</strong> Please contact our office for more information.</p>
        @endif

        <p>
            Your account balance remains unchanged. If you have any questions or concerns about this decision,
            please don't hesitate to contact our office.
        </p>

        <a href="{{ config('app.url') }}/member/services" class="button">Submit New Request</a>
    @endif

    <p>
        If you have any questions, please don't hesitate to contact us.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection
