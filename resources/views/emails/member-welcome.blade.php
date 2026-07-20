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
        <p><strong>Your Login Credentials:</strong></p>
        <p><strong>Email:</strong> {{ $email }}</p>
        <p><strong>Password:</strong> {{ $plainPassword }}</p>
        <p style="color: #dc2626; font-size: 14px;">
            <em>Please change your password after your first login for security.</em>
        </p>
    </div>

    <p>You can now log in to access your account and explore our services.</p>

    <a href="{{ config('app.url') }}/login" class="button">Login to Your Account</a>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection
