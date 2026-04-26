@extends('emails.layout')

@section('content')
    <h2>🔒 Security Alert</h2>

    <p>Dear {{ $user->personalData->first_name ?? 'Member' }},</p>

    @if ($alertType === 'failed_login')
        <div class="alert-box alert-danger">
            <strong>SECURITY ALERT:</strong> Multiple failed login attempts detected.
        </div>

        <p>
            We detected <strong>{{ $data['attempts'] ?? 'multiple' }}</strong> failed login attempt(s)
            on your account from the following IP address:
        </p>

        <p><strong>IP Address:</strong> {{ $data['ip_address'] ?? 'Unknown' }}</p>
        <p><strong>Time:</strong> {{ now()->format('F d, Y h:i A') }}</p>

        <p>
            If this wasn't you, your account may be at risk. We strongly recommend that you:
        </p>

        <ul>
            <li>Change your password immediately</li>
            <li>Review your recent account activity</li>
            <li>Enable two-factor authentication if available</li>
            <li>Contact our office if you notice any suspicious activity</li>
        </ul>

        <a href="{{ config('app.url') }}/notifications" class="button">Review Notifications</a>
    @elseif($alertType === 'new_login')
        <div class="alert-box alert-warning">
            <strong>NEW SIGN-IN DETECTED:</strong> A login was detected from a new IP address.
        </div>

        <p>
            We detected a new sign-in to your account from the following IP address:
        </p>

        <p><strong>IP Address:</strong> {{ $data['ip_address'] ?? 'Unknown' }}</p>
        <p><strong>Time:</strong> {{ now()->format('F d, Y h:i A') }}</p>

        <p>
            If this was you, no further action is required.
        </p>

        <p>
            <strong>If this was not you:</strong>
        </p>

        <ul>
            <li>Reset your password immediately</li>
            <li>Review recent account actions</li>
            <li>Contact support right away</li>
        </ul>

        <a href="{{ config('app.url') }}/notifications" class="button">Review Account Activity</a>
    @elseif($alertType === 'password_changed')
        <div class="alert-box alert-warning">
            <strong>PASSWORD CHANGED:</strong> Your account password was recently changed.
        </div>

        <p>
            This email confirms that your account password was successfully changed on
            <strong>{{ now()->format('F d, Y h:i A') }}</strong>.
        </p>

        <p>
            If you made this change, no further action is required. Your account is secure.
        </p>

        <p>
            <strong>If you did NOT make this change:</strong>
        </p>

        <ul>
            <li>Your account may have been compromised</li>
            <li>Contact our office immediately</li>
            <li>We will help you secure your account</li>
        </ul>

        <a href="{{ config('app.url') }}/contact" class="button">Contact Support</a>
    @elseif($alertType === 'email_changed')
        <div class="alert-box alert-warning">
            <strong>EMAIL CHANGED:</strong> Your account email address was recently changed.
        </div>

        <p>
            This email confirms that your account email address was changed to:
        </p>

        <p><strong>New Email:</strong> {{ $data['new_email'] ?? 'Not specified' }}</p>
        <p><strong>Changed On:</strong> {{ now()->format('F d, Y h:i A') }}</p>

        <p>
            If you made this change, no further action is required. Your account is secure.
        </p>

        <p>
            <strong>If you did NOT make this change:</strong>
        </p>

        <ul>
            <li>Your account may have been compromised</li>
            <li>Contact our office immediately</li>
            <li>We will help you recover and secure your account</li>
        </ul>

        <a href="{{ config('app.url') }}/contact" class="button">Contact Support Immediately</a>
    @endif

    <p>
        Your account security is our top priority. If you have any concerns or questions,
        please contact us immediately.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong><br>
        <em>Security Team</em>
    </p>
@endsection
