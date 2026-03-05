@extends('emails.layout')

@section('content')
    <h2>📢 New Announcement</h2>

    <div class="alert-box alert-info">
        <strong>{{ $announcement->title }}</strong>
    </div>

    <p>Dear Member,</p>

    <p>We have a new announcement from Settlers Credit Cooperative:</p>

    <div style="background-color: #f8fafc; padding: 20px; border-radius: 6px; margin: 20px 0;">
        {!! nl2br(e($announcement->content)) !!}
    </div>

    <p>
        <strong>Posted on:</strong> {{ $announcement->created_at->format('F d, Y h:i A') }}<br>
        @if ($announcement->creator)
            <strong>Posted by:</strong> {{ $announcement->creator->personalData->first_name ?? 'Admin' }}
            {{ $announcement->creator->personalData->last_name ?? '' }}
        @endif
    </p>

    <a href="{{ config('app.url') }}/announcements" class="button">View All Announcements</a>

    <p>
        Stay updated with the latest news and updates from your cooperative.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
@endsection
