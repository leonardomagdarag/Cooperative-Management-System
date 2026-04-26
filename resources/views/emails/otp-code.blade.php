@extends('emails.layout')

@section('content')
    <h2>Security Verification Code</h2>

    <p>Dear {{ $user->personalData->first_name ?? 'Member' }},</p>

    @if ($purpose === 'new_device_login')
        <p>We detected a login from a device that is not yet trusted. Enter the one-time code below to continue.</p>
    @elseif ($purpose === 'withdrawal_approval')
        <p>A high-value withdrawal approval is being processed from your account. Enter the one-time code below to continue.
        </p>
    @else
        <p>Use the one-time code below to complete your security verification.</p>
    @endif

    <div class="alert-box alert-warning" style="text-align: center; font-size: 28px; letter-spacing: 6px; font-weight: bold;">
        {{ $otpCode }}
    </div>

    <p>This code expires in {{ \App\Models\SystemSetting::get('security_otp_expiry_minutes', 10) }} minutes and can only be
        used once.</p>

    <p>If you did not request this code, please secure your account immediately and contact support.</p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong><br>
        <em>Security Team</em>
    </p>
@endsection
