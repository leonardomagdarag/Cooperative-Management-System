@extends('emails.layout')

@section('content')
    <h2>{{ $title }}</h2>

    <p>Dear {{ $loan->user?->personalData?->first_name ?? 'Member' }},</p>

    <p>{{ $message }}</p>

    <p><strong>Loan reference:</strong> {{ $loan->loan_number }}</p>
    <p><strong>Requested amount:</strong> ₱{{ number_format((float) $loan->loan_amount, 2) }}</p>

    <p>You can sign in to Settlers Credit Cooperative to review your loan details.</p>

    <p>Best regards,<br><strong>Settlers Credit Cooperative</strong></p>
@endsection
