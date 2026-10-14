<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Device - {{ config('app.name', 'Settlers Credit Cooperative') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="public-page min-h-screen flex items-center justify-center px-4 py-6">
    <main class="public-card w-full max-w-md p-6 md:p-8">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-50 rounded-2xl mb-3">
                <i class="fas fa-shield-alt text-2xl text-emerald-700"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Verify your device</h1>
            <p class="mt-2 text-sm text-slate-600">Enter the 6-digit code sent to your email to continue.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        @if (in_array(config('app.env'), ['local', 'testing'], true) && session('security.pending_otp_debug_code'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <p class="font-medium">Development code</p>
                <p class="mt-1 font-mono text-2xl tracking-[0.4em]">{{ session('security.pending_otp_debug_code') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('otp.challenge.verify') }}" class="space-y-4">
            @csrf
            <label for="otp" class="block text-sm font-medium text-slate-700">Verification code</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus class="public-input text-center text-xl tracking-[0.4em]" autocomplete="one-time-code">
            <button type="submit" class="public-btn public-btn-primary w-full">Verify and continue</button>
        </form>

        @if (auth()->check() && app(\App\Services\SecurityOtpService::class)->userCanReceiveSms(auth()->user()))
            <div class="mt-3 flex items-center justify-between text-sm">
                <form method="POST" action="{{ route('otp.challenge.resend') }}">
                    @csrf
                    <button type="submit" class="font-medium text-emerald-700 hover:text-emerald-800">Resend email</button>
                </form>
                <form method="POST" action="{{ route('otp.challenge.resend_sms') }}">
                    @csrf
                    <button type="submit" class="font-medium text-emerald-700 hover:text-emerald-800">Send via SMS instead</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('otp.challenge.resend') }}" class="mt-3 text-center">
                @csrf
                <button type="submit" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Resend code</button>
            </form>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-slate-100 pt-4 text-center">
            @csrf
            <button type="submit" class="text-sm font-medium text-slate-500 hover:text-slate-700">Sign out</button>
        </form>
    </main>
</body>
</html>
