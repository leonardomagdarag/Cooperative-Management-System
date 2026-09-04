@extends('emails.layout')

@section('content')
    <h2>Verify Your Email Address</h2>

    <p>Hello <strong>{{ $user->name }}</strong>,</p>

    <div class="alert-box alert-info">
        <strong>Action required.</strong> Please verify your email address to activate your account and access cooperative services.
    </div>

    <p>
        Click the button below to confirm your email address. This link expires in 60 minutes.
    </p>

    <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>

    <p>
        If you did not create this account, no further action is required. If you believe this is a mistake, please contact support.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection