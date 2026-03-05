@extends('emails.layout')

@section('content')
    <h2>
        @if ($status === 'approved')
            ✅ Loan Application Approved
        @else
            ❌ Loan Application Status Update
        @endif
    </h2>

    <p>Dear {{ $loan->user->personalData->first_name ?? 'Member' }},</p>

    @if ($status === 'approved')
        <div class="alert-box alert-success">
            <strong>Congratulations!</strong> Your loan application has been approved.
        </div>

        <p>
            We are pleased to inform you that your loan application <strong>{{ $loan->loan_number }}</strong>
            has been approved by our loan committee.
        </p>

        <p><strong>Loan Details:</strong></p>
        <ul>
            <li><strong>Loan Number:</strong> {{ $loan->loan_number }}</li>
            <li><strong>Loan Type:</strong> {{ ucfirst(str_replace('_', ' ', $loan->loan_type)) }}</li>
            <li><strong>Approved Amount:</strong> ₱{{ number_format($loan->loan_amount, 2) }}</li>
            <li><strong>Interest Rate:</strong> {{ $loan->interest_rate }}%</li>
            <li><strong>Term:</strong> {{ $loan->term_months }} months</li>
            <li><strong>Due Date:</strong> {{ $loan->due_date ? $loan->due_date->format('F d, Y') : 'TBA' }}</li>
        </ul>

        <p>
            Please visit our office to complete the final documentation and release of funds.
            Bring a valid ID and sign the loan agreement.
        </p>

        <a href="{{ config('app.url') }}/loans/{{ $loan->id }}" class="button">View Loan Details</a>

        <p>
            Thank you for your trust in Settlers Credit Cooperative. We are committed to helping you
            achieve your financial goals.
        </p>
    @else
        <div class="alert-box alert-warning">
            We regret to inform you that your loan application could not be approved at this time.
        </div>

        <p>
            Your loan application <strong>{{ $loan->loan_number }}</strong> has been carefully reviewed,
            and unfortunately, we are unable to approve it at this time.
        </p>

        <p><strong>Application Details:</strong></p>
        <ul>
            <li><strong>Loan Number:</strong> {{ $loan->loan_number }}</li>
            <li><strong>Loan Type:</strong> {{ ucfirst(str_replace('_', ' ', $loan->loan_type)) }}</li>
            <li><strong>Requested Amount:</strong> ₱{{ number_format($loan->loan_amount, 2) }}</li>
            <li><strong>Applied On:</strong> {{ $loan->applied_at->format('F d, Y') }}</li>
        </ul>

        @if ($reason)
            <p><strong>Reason:</strong> {{ $reason }}</p>
        @endif

        <p>
            You may reapply after addressing the concerns or contact our office for more information
            on improving your application.
        </p>

        <a href="{{ config('app.url') }}/loans" class="button">View Applications</a>
    @endif

    <p>
        If you have any questions, please don't hesitate to contact us.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection
