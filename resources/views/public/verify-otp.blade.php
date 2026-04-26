<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Verify Login - Settlers Credit Cooperative</title>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Security Verification</h1>
        <p class="text-sm text-slate-600 mb-6">Enter the 6-digit code sent to your email to continue.</p>

        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('otp.challenge.verify') }}" class="space-y-4">
            @csrf
            <div>
                <label for="otp" class="block text-sm font-medium text-slate-700 mb-2">One-Time Code</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6"
                    pattern="[0-9]{6}" autocomplete="one-time-code"
                    class="w-full rounded-lg border border-gray-300 px-4 py-3 text-lg tracking-[0.3em] focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    placeholder="000000" required>
            </div>

            <button type="submit"
                class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-semibold py-3 rounded-lg transition-colors">
                Verify and Continue
            </button>
        </form>

        <form method="POST" action="{{ route('otp.challenge.resend') }}" class="mt-4">
            @csrf
            <button type="submit" class="w-full text-sm text-emerald-700 hover:text-emerald-800 font-medium">
                Resend code
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full text-sm text-slate-500 hover:text-slate-700">
                Cancel and sign out
            </button>
        </form>
    </div>
</body>

</html>
