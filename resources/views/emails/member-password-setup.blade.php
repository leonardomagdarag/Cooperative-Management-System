@extends('emails.layout')

@section('content')
    <h2>Welcome to Settlers Credit Cooperative</h2>

    <p>Hello <strong>{{ $memberName }}</strong>,</p>

    <p>Your membership record has been created at the cooperative. Set your own password to access your account online.</p>

    <a href="{{ $setupUrl }}" class="button">Set Your Password</a>

    <p>This one-time link expires after 30 minutes. If it expires, ask the cooperative office to resend your invitation.</p>
    <p>If you did not visit a cooperative office to create an account, contact the cooperative before opening this link.</p>
@endsection
