@extends('emails.layout')

@section('content')
    @if($status === 'approved')
        <h2>Membership Application Approved</h2>

        <p>Hello <strong>{{ $applicantName }}</strong>,</p>

        <div class="alert-box alert-success">
            <strong>Congratulations!</strong> Your membership application with Settlers Credit Cooperative has been approved.
        </div>

        <p>
            To activate your account and start using cooperative services, click the button below.
            This activation link expires in 60 minutes.
        </p>

        <a href="{{ $activationUrl }}" class="button">Activate Your Account</a>

        <p>
            Once activated, you will be able to log in. On your first login, you may be asked to complete
            any missing profile information.
        </p>
    @else
        <h2>Membership Application Status Update</h2>

        <p>Hello <strong>{{ $applicantName }}</strong>,</p>

        <div class="alert-box alert-danger">
            <strong>We're sorry.</strong> Your membership application with Settlers Credit Cooperative was not approved at this time.
        </div>

        @if($reason)
            <p>
                <strong>Reason:</strong> {{ $reason }}
            </p>
        @endif

        <p>
            If you believe this decision was made in error, or if you would like to re-apply in the future,
            please don't hesitate to contact our office.
        </p>

        <p>
            Best regards,<br>
            <strong>Settlers Credit Cooperative</strong>
        </p>
    @endif
@endsection