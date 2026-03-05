@extends('emails.layout')

@section('content')
    <h2>
        @if ($isOverdue)
            🚨 Loan Payment Overdue
        @else
            ⏰ Loan Payment Due Soon
        @endif
    </h2>

    <p>Dear {{ $loan->user->personalData->first_name ?? 'Member' }},</p>

    @if ($isOverdue)
        <div class="alert-box alert-danger">
            <strong>URGENT:</strong> Your loan payment is now overdue.
        </div>

        <p>
            Your loan payment for <strong>{{ $loan->loan_number }}</strong> was due on
            <strong>{{ $loan->due_date->format('F d, Y') }}</strong> and is now overdue.
        </p>

        <p>
            <strong>Loan Amount:</strong> ₱{{ number_format($loan->loan_amount, 2) }}<br>
            <strong>Days Overdue:</strong> {{ abs($loan->due_date->diffInDays(now())) }} day(s)
        </p>

        <p>
            Please settle your payment as soon as possible to avoid additional penalties and maintain your good standing
            with the cooperative.
        </p>
    @else
        @if ($daysRemaining == 1)
            <div class="alert-box alert-danger">
                <strong>URGENT:</strong> Your loan payment is due tomorrow!
            </div>
        @elseif($daysRemaining == 3)
            <div class="alert-box alert-warning">
                <strong>REMINDER:</strong> Your loan payment is due in 3 days.
            </div>
        @else
            <div class="alert-box alert-info">
                <strong>REMINDER:</strong> Your loan payment is due in {{ $daysRemaining }} days.
            </div>
        @endif

        <p>
            This is a friendly reminder that your loan payment for <strong>{{ $loan->loan_number }}</strong>
            is due on <strong>{{ $loan->due_date->format('F d, Y') }}</strong>.
        </p>

        <p>
            <strong>Loan Amount:</strong> ₱{{ number_format($loan->loan_amount, 2) }}<br>
            <strong>Loan Type:</strong> {{ ucfirst(str_replace('_', ' ', $loan->loan_type)) }}<br>
            <strong>Due Date:</strong> {{ $loan->due_date->format('F d, Y') }}
        </p>

        <p>Please ensure you have sufficient funds for the payment.</p>
    @endif

    <a href="{{ config('app.url') }}/loans" class="button">View Loan Details</a>

    <p>
        If you have any questions or need assistance, please contact our office during business hours.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection
