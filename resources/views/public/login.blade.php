<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/login.js'])
    <title>Login - Settlers Credit Cooperative</title>
</head>

<body class="public-page">
    @php
        $memberRegistrationEnabled = (bool) data_get($publicContent ?? [], 'registration.online_member_registration_enabled', true);
    @endphp

    <!-- Success Toast Notification -->
    @if (session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-[-100%]"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-[-100%]"
            class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full mx-4">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0">
                            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-emerald-700 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 pt-1">
                            <h3 class="text-sm font-semibold text-slate-800 mb-1">Success!</h3>
                            <p class="text-sm text-slate-600">{{ session('success') }}</p>
                        </div>
                        <button @click="show = false" type="button"
                            class="shrink-0 text-slate-400 hover:text-slate-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <!-- Progress bar -->
                <div class="h-1 bg-gray-200">
                    <div class="h-full bg-emerald-700 animate-shrink"></div>
                </div>
            </div>
        </div>
    @endif

    <section class="public-auth-panel flex items-center justify-center px-6 py-8">
        <!-- Login Container -->
        <div class="public-card overflow-hidden max-w-md w-full">
            <!-- Login Form Section -->
            <div class="p-8" id="loginContainer">
                <div class="max-w-md mx-auto">
                    <!-- Header -->
                    <div class="mb-8 text-center">
                        <div class="mb-5 flex justify-center">
                            <img src="{{ asset('images/Logo.png') }}" alt="Settlers Logo"
                                class="h-16 w-16 rounded-full object-cover shadow-sm">
                        </div>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 mb-2">Welcome back</h1>
                        <p class="text-sm md:text-base text-slate-600">
                            Enter your credentials to access your account
                        </p>
                    </div>

                    <!-- Login Form -->
                    <form class="space-y-4" id="loginForm" action="{{ route('login') }}" method="POST">
                        @csrf
                        <!-- Email Input -->
                        <div>
                            <label for="email" class="block text-xs md:text-sm font-medium text-slate-500 mb-2">
                                Email
                            </label>
                            <div class="relative">
                                <input type="email" id="email" name="email"
                                    class="public-input text-sm"
                                    placeholder="Enter your email" required />
                            </div>

                        </div>

                        <!-- Password Input -->
                        <div>
                            <label for="password" class="block text-xs md:text-sm font-medium text-slate-500 mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password"
                                    class="public-input pr-12 text-sm"
                                    placeholder="Enter your password" required />
                                <button type="button" onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Warning Notification -->
                        <div id="warn" class="hidden mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg ">
                            <div class="flex items-center gap-3">
                                <div class="shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-800" id="warnText">Incorrect Credentials</p>
                                </div>
                                <button type="button" onclick="closeWarn()"
                                    class="shrink-0 text-red-400 hover:text-red-600 transition-colors">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between flex-wrap gap-2 pt-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="remember"
                                    class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer" />
                                <label for="remember" class="ml-2 text-sm text-slate-600 cursor-pointer select-none">
                                    Remember me
                                </label>
                            </div>
                            <a href="{{ route('password.request') }}"
                                class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                                Forgot password?
                            </a>
                        </div>



                        <!-- Login Button -->
                        <button type="submit"
                            class="public-btn public-btn-primary w-full">
                            Sign in
                        </button>

                        <!-- Registration Link -->
                        @if ($memberRegistrationEnabled)
                            <div class="text-center pt-6 mt-6 border-t border-gray-200">
                                <p class="text-sm text-slate-600 mb-3">Don't have an account?</p>
                                <a href="{{ route('register_page') }}"
                                    class="inline-flex items-center gap-2 text-emerald-700 hover:text-emerald-800 font-semibold transition-colors">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Create an Account</span>
                                    <i class="fas fa-arrow-right text-sm"></i>
                                </a>
                            </div>
                        @endif

                        <!-- Back Button -->
                        <div class="text-center pt-4">
                            <a href="{{ route('home_page') }}"
                                class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors group">
                                <i
                                    class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                                <span class="font-medium">Back to Home</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

</body>

</html>
