@extends('emails.layout')

@section('content')
    <h2>Welcome to the Cooperative!</h2>

    <p>Hello <strong>{{ $name }}</strong>,</p>

    <div class="alert-box alert-success">
        <strong>Welcome!</strong> Your member account has been successfully created.
    </div>

    <p>
        We're excited to have you as part of our cooperative family. Your membership gives you
        access to various benefits and services.
    </p>

    <div class="alert-box alert-info">
        <p><strong>Account Details:</strong></p>
        <p><strong>Email:</strong> {{ $email }}</p>
        <p style="color: #475569; font-size: 14px;">
            <em>For your security, your account password is not included in this email. If you need to set or reset your password, you may do so at any time using the password reset page.</em>
        </p>
    </div>

    <p>You can now log in to access your account and explore our services.</p>

    <a href="{{ $setupUrl ?? url('/login') }}" class="button">Access Your Account</a>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection
