<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css'])
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <title>Forgot Password - Step 1 - Settlers Credit Cooperative</title>
</head>

<body class="public-page min-h-screen">
    <section class="public-auth-panel flex items-center justify-center px-6 py-6">
        <!-- Centered Container -->
        <div class="public-card overflow-hidden max-w-2xl w-full p-6 md:p-8">

            <!-- Header -->
            <div class="text-center mb-5">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-50 rounded-2xl mb-3">
                    <i class="fas fa-envelope text-2xl text-slate-700"></i>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Forgot Password?</h1>
                <p class="text-sm text-slate-600">
                    Enter your email to receive a verification code
                </p>
            </div>

            <!-- Progress Indicator -->
            <div class="mb-5">
                <div class="flex items-center justify-center gap-3">
                    <!-- Step 1 - Active -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center text-sm font-bold mb-2">
                            1
                        </div>
                        <span class="text-xs font-medium text-emerald-700">Email</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-200"></div>

                    <!-- Step 2 - Inactive -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold mb-2">
                            2
                        </div>
                        <span class="text-xs text-gray-500">Verify PIN</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-200"></div>

                    <!-- Step 3 - Inactive -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-10 h-10 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-bold mb-2">
                            3
                        </div>
                        <span class="text-xs text-gray-500">New Password</span>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0">
                            <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Error Message -->
            @if (session('error'))
                <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Form -->
            <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-2">
                        Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-slate-400"></i>
                        </div>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                            class="public-input pl-12 @error('email') ring-2 ring-red-500 @enderror"
                            placeholder="Enter your registered email" required autofocus />
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    @if (config('services.turnstile.site_key'))
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"
                            data-theme="light" data-callback="turnstileCallback"></div>
                    @else
                        <p class="text-sm text-red-600">Captcha is not configured. Please contact support.</p>
                    @endif
                    @error('cf-turnstile-response')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Info Box -->
                <div class="p-4 bg-blue-50 rounded-xl border border-blue-100">
                    <div class="flex gap-3">
                        <div class="shrink-0">
                            <i class="fas fa-info-circle text-blue-500 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm text-blue-800 leading-relaxed">
                                A 6-digit verification code will be sent to your email. The code expires in 30
                                minutes.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="forgotPasswordSubmitBtn"
                    class="public-btn public-btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                    <span class="flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        Send Verification Code
                    </span>
                </button>

                <!-- Back to Login -->
                <div class="text-center pt-1">
                    <a href="{{ route('login_page') }}"
                        class="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors group text-sm font-medium">
                        <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                        Back to Sign In
                    </a>
                </div>
            </form>
        </div>
    </section>

    <script>
        // Forgot password form: enable submit button only when Turnstile succeeds
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('forgotPasswordSubmitBtn');

            if (submitBtn) {
                submitBtn.disabled = true;

                window.turnstileCallback = function() {
                    submitBtn.disabled = false;
                };
            }
        });
    </script>
</body>

</html>
