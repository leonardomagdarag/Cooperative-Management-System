{{--
    The maintenance page.

    Deliberately standalone: no @vite, no layout, no session, no database, no
    config() lookups that depend on state a restore would be in the middle of
    replacing. The build manifest can be absent during `artisan down` in
    development, and the database may be mid-import, so anything that reaches
    for either would turn a tidy notice into a 500.

    Expects $maintenance from App\Support\MaintenanceState::describe():
      reason, headline, message, detail, retry_after, started_at, stale
--}}
@php
    $maintenance = $maintenance ?? [
        'reason' => \App\Support\MaintenanceState::REASON_SCHEDULED,
        'headline' => 'Scheduled maintenance',
        'message' => 'The cooperative is carrying out scheduled work on the system. It will be available again shortly.',
        'detail' => null,
        'retry_after' => \App\Support\MaintenanceState::DEFAULT_RETRY_AFTER,
        'started_at' => null,
        'stale' => false,
    ];
    $cooperativeName = config('app.name') ?: 'Settlers Credit Cooperative';
    $retryAfter = max(5, (int) ($maintenance['retry_after'] ?? 15));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $maintenance['headline'] }} &middot; {{ $cooperativeName }}</title>
    @if (! $maintenance['stale'])
        {{-- Come back on our own, so nobody is left staring at a page that has
             already recovered. --}}
        <meta http-equiv="refresh" content="{{ $retryAfter }}">
    @endif
    {{-- Assets are compiled, not built here: the dev manifest may not exist
         while the application is down. --}}
    <link rel="icon" type="image/png" href="/images/Logo.png">
    <style>
        :root {
            --brand-600: #059669;
            --brand-700: #047857;
            --slate-800: #1e293b;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: 'Outfit Variable', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--slate-800);
            background: var(--slate-50);
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 34rem;
            background: #fff;
            border: 1px solid var(--slate-200);
            border-radius: 0.875rem;
            box-shadow: 0 1px 3px rgb(15 23 42 / 0.08), 0 12px 32px -12px rgb(15 23 42 / 0.12);
            overflow: hidden;
        }

        .banner {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.875rem 1.5rem;
            background: var(--brand-600);
            color: #fff;
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .banner svg { flex: none; }

        .body { padding: 2rem 1.5rem 1.75rem; text-align: center; }

        .mark {
            width: 3.5rem;
            height: 3.5rem;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            background: #ecfdf5;
            color: var(--brand-700);
        }

        .mark svg { animation: pulse 2.4s ease-in-out infinite; }

        @keyframes pulse {
            0%, 100% { transform: rotate(0deg); opacity: 1; }
            50% { transform: rotate(-24deg); opacity: 0.65; }
        }

        @media (prefers-reduced-motion: reduce) {
            .mark svg { animation: none; }
        }

        h1 {
            margin: 0 0 0.625rem;
            font-size: 1.375rem;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        @media (min-width: 640px) {
            h1 { font-size: 1.5rem; }
        }

        .message {
            margin: 0;
            color: var(--slate-600);
            font-size: 0.9375rem;
            line-height: 1.65;
        }

        .detail {
            margin: 0.875rem auto 0;
            max-width: 28rem;
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--slate-200);
            border-radius: 0.625rem;
            background: var(--slate-50);
            color: var(--slate-600);
            font-size: 0.8125rem;
            line-height: 1.55;
        }

        .actions {
            margin-top: 1.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.875rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.125rem;
            border: 1px solid transparent;
            border-radius: 0.625rem;
            background: var(--brand-700);
            color: #fff;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 160ms ease, transform 160ms ease;
        }

        .btn:hover { background: var(--brand-600); transform: translateY(-1px); }

        .btn:focus-visible {
            outline: 2px solid var(--brand-600);
            outline-offset: 2px;
        }

        .hint {
            display: flex;
            align-items: center;
            gap: 0.4375rem;
            color: var(--slate-500);
            font-size: 0.75rem;
        }

        .hint .dot {
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 50%;
            background: var(--brand-600);
        }

        footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--slate-200);
            background: var(--slate-50);
            color: var(--slate-500);
            font-size: 0.75rem;
            text-align: center;
        }

        footer strong { color: var(--slate-600); font-weight: 600; }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="banner">
            {{-- A wrench reads as planned work; an alarm would read as an outage. --}}
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
            <span>{{ $maintenance['reason'] === \App\Support\MaintenanceState::REASON_RESTORE ? 'Backup restore in progress' : 'Temporarily unavailable' }}</span>
        </div>

        <div class="body">
            <div class="mark">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
            </div>

            <h1>{{ $maintenance['headline'] }}</h1>
            <p class="message">{{ $maintenance['message'] }}</p>

            @if (! empty($maintenance['detail']))
                <p class="detail">{{ $maintenance['detail'] }}</p>
            @endif

            <div class="actions">
                <a class="btn" href="{{ url()->current() }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>
                    </svg>
                    Try again
                </a>

                @unless ($maintenance['stale'])
                    <p class="hint">
                        <span class="dot"></span>
                        This page checks back every {{ $retryAfter }} seconds
                    </p>
                @endunless
            </div>
        </div>

        <footer>
            <strong>{{ $cooperativeName }}</strong>
            <span> &middot; Please try again shortly. Your data is safe.</span>
        </footer>
    </main>
</body>
</html>
